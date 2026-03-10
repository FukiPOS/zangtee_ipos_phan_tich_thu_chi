# How to Use Gmail Invoice Reader
1. Set up Google Cloud credentials
Go to Google Cloud Console → Credentials https://console.cloud.google.com/apis/credentials
Create an OAuth 2.0 Client ID (Web application)
Set redirect URI to: http://localhost:8000/gmail/callback
Enable Gmail API in your project
Fill in 
.env
:
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret

Dont forget to enable Gmail API here
https://console.cloud.google.com/apis/library/gmail.googleapis.com?project=zangtee-aacb8

2. Authorize Gmail
Visit http://localhost:8000/gmail/auth in your browser → authorize with Google → you'll be redirected back.

3. Run the crawler
bash
# Dry run — just list emails
docker compose exec laravel php artisan app:crawl-gmail-invoices --dry-run
# Download attachments for last 7 days
docker compose exec laravel php artisan app:crawl-gmail-invoices --days=7 --download-attachments
# Automatic: runs hourly via schedule
docker compose exec laravel php artisan schedule:work