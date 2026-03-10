<?php

namespace App\Http\Controllers;

use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GmailAuthController extends Controller
{
    private GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
    }

    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('gmail_oauth_state', $state);

        $authUrl = $this->gmailService->getAuthUrl($state);

        return redirect()->away($authUrl);
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(Request $request)
    {
        // Verify state parameter to prevent CSRF
        $expectedState = $request->session()->pull('gmail_oauth_state');

        if (!$expectedState || $request->input('state') !== $expectedState) {
            echo 'Gmail OAuth state missmatch';
            exit();
            Log::warning('Gmail OAuth state mismatch');

            return redirect()->route('dashboard')->with('error', 'Invalid OAuth state. Please try again.');
        }

        // Check for error response from Google
        if ($request->has('error')) {
            echo 'Gmail OAuth error',
                print_r($request->input('error'));
            exit();
            Log::warning('Gmail OAuth error', ['error' => $request->input('error')]);

            return redirect()->route('dashboard')->with('error', 'Gmail authorization was denied: ' . $request->input('error'));
        }

        $code = $request->input('code');

        if (!$code) {
            return redirect()->route('dashboard')->with('error', 'No authorization code received.');
        }

        try {
            $token = $this->gmailService->handleCallback($code);

            Log::info("Gmail OAuth completed for {$token->email}");

            return redirect()->route('dashboard')->with('success', "Gmail account {$token->email} connected successfully!");
        }
        catch (\Exception $e) {
            Log::error('Gmail OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('dashboard')->with('error', 'Failed to connect Gmail: ' . $e->getMessage());
        }
    }
}