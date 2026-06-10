<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 64)->index();
            $table->string('user_id', 64)->nullable();
            $table->string('username', 128)->nullable();
            $table->string('group_type', 16);
            $table->string('state', 64);
            $table->jsonb('data')->nullable();
            $table->unsignedInteger('wizard_message_id')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['chat_id', 'user_id'], 'telegram_sessions_chat_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_sessions');
    }
};
