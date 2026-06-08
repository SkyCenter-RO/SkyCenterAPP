<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('service', 32);
            $table->string('name', 190);
            $table->string('kind', 16);
            $table->string('frequency', 16);
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_service_check CHECK (service IN ('hotel','parcare','rent','general'))");
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_kind_check CHECK (kind IN ('expense','income'))");
        DB::statement("ALTER TABLE budget_categories ADD CONSTRAINT budget_categories_frequency_check CHECK (frequency IN ('daily','weekly','monthly','quarterly','yearly','once'))");

        Schema::create('budget_raw_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 64);
            $table->string('message_id', 64);
            $table->text('text');
            $table->boolean('parsed')->default(false);
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampsTz();
            $table->unique(['chat_id', 'message_id'], 'budget_raw_messages_chat_message_unique');
        });

        Schema::create('budget_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('type', 16);
            $table->foreignId('category_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->string('service', 32)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RON');
            $table->date('occurred_on');
            $table->text('description')->nullable();
            $table->string('telegram_chat', 32)->nullable();
            $table->foreignId('raw_message_id')->nullable()->constrained('budget_raw_messages')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'budget_transactions_source_external_unique');
            $table->index(['type', 'occurred_on'], 'budget_transactions_type_occurred_index');
            $table->index(['service', 'occurred_on'], 'budget_transactions_service_occurred_index');
        });
        DB::statement("ALTER TABLE budget_transactions ADD CONSTRAINT budget_transactions_type_check CHECK (type IN ('income','expense'))");

        // Close the circular link now that budget_transactions exists.
        Schema::table('budget_raw_messages', function (Blueprint $table): void {
            $table->foreign('transaction_id')->references('id')->on('budget_transactions')->nullOnDelete();
        });

        Schema::create('salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RON');
            $table->date('period_month');
            $table->timestampTz('paid_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE salaries ADD CONSTRAINT salaries_status_check CHECK (status IN ('pending','paid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
        Schema::table('budget_raw_messages', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
        });
        Schema::dropIfExists('budget_transactions');
        Schema::dropIfExists('budget_raw_messages');
        Schema::dropIfExists('budget_categories');
    }
};
