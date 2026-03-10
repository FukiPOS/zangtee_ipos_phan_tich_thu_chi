<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gmail_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->text('access_token');
            $table->text('refresh_token'); // stored encrypted via model cast
            $table->timestamp('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_tokens');
    }
};