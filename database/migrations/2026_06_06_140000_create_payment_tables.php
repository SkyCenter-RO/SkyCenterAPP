<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('service', 32);
            $table->foreignId('parking_reservation_id')->nullable()->constrained('parking_reservations')->nullOnDelete();
            $table->foreignId('lodging_reservation_id')->nullable()->constrained('lodging_reservations')->nullOnDelete();
            $table->foreignId('rent_contract_id')->nullable()->constrained('rent_contracts')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('RON');
            $table->string('method', 32);
            $table->timestampTz('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['service', 'paid_at'], 'payments_service_paid_index');
            $table->index('method', 'payments_method_index');
            $table->index(['source', 'external_id'], 'payments_source_external_index');
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_service_check CHECK (service IN ('parking','lodging','rent'))");

        Schema::create('payment_change_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('changed_fields')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_change_audits');
        Schema::dropIfExists('payments');
    }
};
