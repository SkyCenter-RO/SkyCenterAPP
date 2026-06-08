<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('template_key', 190);
            $table->string('service', 64)->nullable();
            $table->string('channel', 64);
            $table->string('locale', 16)->default('ro');
            $table->string('label')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['source', 'external_id'], 'message_templates_source_external_unique');
            $table->index(['service', 'channel'], 'message_templates_service_channel_index');
            $table->index(['channel', 'locale'], 'message_templates_channel_locale_index');
        });
        DB::statement("ALTER TABLE message_templates ADD CONSTRAINT message_templates_channel_check CHECK (channel IN ('whatsapp','telegram','viber','email','sms'))");

        Schema::create('outbound_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 32);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('channel', 64);
            $table->string('template_key', 190)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('scheduled_at');
            $table->timestampTz('sent_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestampsTz();

            $table->index(['status', 'scheduled_at'], 'outbound_messages_status_scheduled_index');
        });
        DB::statement("ALTER TABLE outbound_messages ADD CONSTRAINT outbound_messages_status_check CHECK (status IN ('pending','sent','failed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('message_templates');
    }
};
