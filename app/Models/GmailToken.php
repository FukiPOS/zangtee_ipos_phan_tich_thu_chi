<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GmailToken extends Model
{
    protected $fillable = [
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
    ];

    protected $casts = [
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Check if the access token has expired.
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        // Consider expired 5 minutes before actual expiry for safety margin
        return $this->token_expires_at->subMinutes(5)->isPast();
    }
}