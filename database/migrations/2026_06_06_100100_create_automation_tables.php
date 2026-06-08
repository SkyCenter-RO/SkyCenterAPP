<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('endpoint', 255);
            $table->string('idempotency_key', 191)->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->default(0);
            $table->string('event_type', 120)->nullable();
            $table->string('service', 32)->nullable();
            $table->string('external_id', 190)->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['endpoint', 'idempotency_key'], 'automation_webhook_logs_endpoint_idem_index');
            $table->index(['status', 'received_at'], 'automation_webhook_logs_status_received_index');
        });

        Schema::create('automation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_log_id')->nullable()->constrained('automation_webhook_logs')->nullOnDelete();
            $table->string('event_type', 120);
            $table->string('service', 32)->nullable();
            $table->string('external_id', 190)->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->string('status', 32)->default('received');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['service', 'event_type'], 'automation_events_service_event_index');
            $table->index('occurred_at', 'automation_events_occurred_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('automation_webhook_logs');
    }
};
