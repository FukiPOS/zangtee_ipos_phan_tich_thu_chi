<?php

namespace App\Console\Commands;

use App\Models\GmailToken;
use App\Services\GmailService;
use Illuminate\Console\Command;

class CrawlGmailInvoices extends Command
{
    protected $signature = 'app:crawl-gmail-invoices
        {--days=1 : Number of days to look back for emails}
        {--email= : Only process a specific Gmail account}
        {--download-attachments : Download attachments to storage}
        {--dry-run : List emails without downloading attachments}';

    protected $description = 'Crawl Gmail for invoice/receipt emails and download attachments';

    private GmailService $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $specificEmail = $this->option('email');
        $downloadAttachments = $this->option('download-attachments');
        $dryRun = $this->option('dry-run');

        $afterDate = now()->subDays($days)->format('Y/m/d');

        // Load token(s)
        $query = GmailToken::query();
        if ($specificEmail) {
            $query->where('email', $specificEmail);
        }

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->warn('No Gmail accounts found. Please authorize via /gmail/auth first.');

            return self::SUCCESS;
        }

        $this->info("Searching for invoice emails since {$afterDate}...");
        $this->newLine();

        foreach ($tokens as $token) {
            $this->processAccount($token, $afterDate, $downloadAttachments, $dryRun);
        }

        return self::SUCCESS;
    }

    private function processAccount(GmailToken $token, string $afterDate, bool $downloadAttachments, bool $dryRun): void
    {
        $this->info("📧 Processing account: {$token->email}");

        try {
            // Step 1: Ensure access token is fresh
            if ($token->isTokenExpired()) {
                $this->comment('  ↻ Refreshing access token...');
                $this->gmailService->refreshAccessToken($token);
                $this->comment('  ✓ Token refreshed');
            }

            // Step 2: List invoice emails
            $messages = $this->gmailService->listInvoiceEmails($token, $afterDate);

            if (empty($messages)) {
                $this->info('  No invoice emails found.');
                $this->newLine();

                return;
            }

            $this->info("  Found " . count($messages) . ' invoice email(s)');
            $this->newLine();

            // Step 3: Process each message
            foreach ($messages as $index => $msg) {
                $this->processMessage($token, $msg['id'], $index + 1, $downloadAttachments, $dryRun);
            }

        }
        catch (\Exception $e) {
            $this->error("  ✗ Error processing {$token->email}: {$e->getMessage()}");
        }

        $this->newLine();
    }

    private function processMessage(GmailToken $token, string $messageId, int $number, bool $downloadAttachments, bool $dryRun): void
    {
        try {
            $message = $this->gmailService->getMessage($token, $messageId);
            $headers = $this->gmailService->extractHeaders($message);
            $attachments = $this->gmailService->extractAttachments($message);

            $subject = $headers['Subject'] ?? '(no subject)';
            $from = $headers['From'] ?? '(unknown)';
            $date = $headers['Date'] ?? '(unknown date)';

            $this->line("  [{$number}] {$subject}");
            $this->line("      From: {$from}");
            $this->line("      Date: {$date}");
            $this->line('      Attachments: ' . count($attachments));

            if ($dryRun) {
                foreach ($attachments as $att) {
                    $size = $this->formatBytes($att['size']);
                    $this->line("        - {$att['filename']} ({$att['mimeType']}, {$size})");
                }

                return;
            }

            // Download attachments if requested
            if ($downloadAttachments && !empty($attachments)) {
                foreach ($attachments as $att) {
                    $this->comment("        ↓ Downloading {$att['filename']}...");

                    $path = $this->gmailService->downloadAttachment(
                        $token,
                        $messageId,
                        $att['attachmentId'],
                        $att['filename']
                    );

                    $this->info("        ✓ Saved to {$path}");
                }
            }

        }
        catch (\Exception $e) {
            $this->error("  ✗ Error processing message {$messageId}: {$e->getMessage()}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}