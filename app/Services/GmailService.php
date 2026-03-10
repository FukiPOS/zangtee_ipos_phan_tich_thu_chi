<?php

namespace App\Services;

use App\Models\GmailToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GmailService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const GMAIL_API_BASE = 'https://gmail.googleapis.com/gmail/v1';

    private const SCOPES = 'https://www.googleapis.com/auth/gmail.readonly';

    private string $clientId;

    private string $clientSecret;

    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
    }

    // ──────────────────────────────────────────────
    // OAuth2 Flow
    // ──────────────────────────────────────────────

    /**
     * Build the Google OAuth2 authorization URL.
     */
    public function getAuthUrl(?string $state = null): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens and store them.
     */
    public function handleCallback(string $code): GmailToken
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            Log::error('Gmail OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to exchange authorization code: ' . $response->body());
        }

        $data = $response->json();

        // Get user email from the access token
        $email = $this->getUserEmail($data['access_token']);

        return GmailToken::updateOrCreate(
        ['email' => $email],
        [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'scopes' => $data['scope'] ?? self::SCOPES,
        ]
        );
    }

    /**
     * Get the authenticated user's email address.
     */
    private function getUserEmail(string $accessToken): string
    {
        $response = Http::withToken($accessToken)
            ->get(self::GMAIL_API_BASE . '/users/me/profile');

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch Gmail profile: ' . $response->body());
        }

        return $response->json('emailAddress');
    }

    // ──────────────────────────────────────────────
    // Token Management
    // ──────────────────────────────────────────────

    /**
     * Get a valid access token, refreshing if necessary.
     */
    public function getAccessToken(GmailToken $token): string
    {
        if ($token->isTokenExpired()) {
            $this->refreshAccessToken($token);
        }

        return $token->access_token;
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken(GmailToken $token): void
    {
        Log::info("Refreshing Gmail access token for {$token->email}");

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $token->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::error('Gmail token refresh failed', [
                'email' => $token->email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Failed to refresh token for {$token->email}: " . $response->body());
        }

        $data = $response->json();

        $token->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        Log::info("Gmail access token refreshed successfully for {$token->email}");
    }

    // ──────────────────────────────────────────────
    // Gmail API – Email Retrieval
    // ──────────────────────────────────────────────

    /**
     * List invoice-related emails.
     *
     * @param  string|null  $afterDate  Format: YYYY/MM/DD
     * @return array List of message metadata (id, threadId)
     */
    public function listInvoiceEmails(GmailToken $token, ?string $afterDate = null, int $maxResults = 50): array
    {
        $accessToken = $this->getAccessToken($token);

        $query = 'subject:(invoice OR receipt OR bill OR "hóa đơn") has:attachment';

        if ($afterDate) {
            $query .= " after:{$afterDate}";
        }

        $messages = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'maxResults' => min($maxResults - count($messages), 100),
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->get(self::GMAIL_API_BASE . '/users/me/messages', $params);

            if ($response->failed()) {
                Log::error('Gmail list messages failed', [
                    'email' => $token->email,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Failed to list Gmail messages: ' . $response->body());
            }

            $data = $response->json();

            if (!empty($data['messages'])) {
                $messages = array_merge($messages, $data['messages']);
            }

            $pageToken = $data['nextPageToken'] ?? null;

        } while ($pageToken && count($messages) < $maxResults);

        return $messages;
    }

    /**
     * Get full message details by ID.
     */
    public function getMessage(GmailToken $token, string $messageId, string $format = 'full'): array
    {
        $accessToken = $this->getAccessToken($token);

        $response = Http::withToken($accessToken)
            ->get(self::GMAIL_API_BASE . "/users/me/messages/{$messageId}", [
            'format' => $format,
        ]);

        if ($response->failed()) {
            Log::error('Gmail get message failed', [
                'email' => $token->email,
                'messageId' => $messageId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException("Failed to get message {$messageId}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get attachment data by message ID and attachment ID.
     */
    public function getAttachment(GmailToken $token, string $messageId, string $attachmentId): array
    {
        $accessToken = $this->getAccessToken($token);

        $response = Http::withToken($accessToken)
            ->get(self::GMAIL_API_BASE . "/users/me/messages/{$messageId}/attachments/{$attachmentId}");

        if ($response->failed()) {
            Log::error('Gmail get attachment failed', [
                'email' => $token->email,
                'messageId' => $messageId,
                'attachmentId' => $attachmentId,
            ]);
            throw new \RuntimeException("Failed to get attachment {$attachmentId}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Download and save attachment to storage.
     *
     * @return string The relative storage path of the saved file
     */
    public function downloadAttachment(
        GmailToken $token,
        string $messageId,
        string $attachmentId,
        string $filename
        ): string
    {
        $attachmentData = $this->getAttachment($token, $messageId, $attachmentId);

        // Gmail returns base64url-encoded data
        $data = $attachmentData['data'];
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $decodedData = base64_decode($data);

        $path = "gmail_attachments/{$token->email}/{$messageId}/{$filename}";
        Storage::disk('local')->put($path, $decodedData);

        Log::info("Gmail attachment saved", ['path' => $path]);

        return $path;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Extract useful header values from a message.
     */
    public function extractHeaders(array $message): array
    {
        $headers = $message['payload']['headers'] ?? [];
        $result = [];

        $keysOfInterest = ['From', 'To', 'Subject', 'Date'];

        foreach ($headers as $header) {
            if (in_array($header['name'], $keysOfInterest)) {
                $result[$header['name']] = $header['value'];
            }
        }

        return $result;
    }

    /**
     * Extract attachments info from a message payload.
     *
     * @return array List of ['filename', 'mimeType', 'attachmentId', 'size']
     */
    public function extractAttachments(array $message): array
    {
        $attachments = [];
        $this->findAttachmentParts($message['payload'] ?? [], $attachments);

        return $attachments;
    }

    /**
     * Recursively find attachment parts in the message payload.
     */
    private function findAttachmentParts(array $part, array &$attachments): void
    {
        if (!empty($part['filename']) && !empty($part['body']['attachmentId'])) {
            $attachments[] = [
                'filename' => $part['filename'],
                'mimeType' => $part['mimeType'] ?? 'application/octet-stream',
                'attachmentId' => $part['body']['attachmentId'],
                'size' => $part['body']['size'] ?? 0,
            ];
        }

        if (!empty($part['parts'])) {
            foreach ($part['parts'] as $subPart) {
                $this->findAttachmentParts($subPart, $attachments);
            }
        }
    }
}