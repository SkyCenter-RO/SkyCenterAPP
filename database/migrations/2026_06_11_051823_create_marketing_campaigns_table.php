<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform', 64); // google|facebook|instagram|tiktok|bing|other
            $table->string('vertical', 32); // parcare|hotel|rent|bundle|general
            $table->string('status', 32)->default('active'); // active|paused|completed|draft
            $table->decimal('budget_eur', 10, 2)->nullable();
            $table->decimal('spend_eur', 10, 2)->nullable();
            $table->integer('conversions')->nullable();
            $table->decimal('cpc_eur', 8, 4)->nullable();
            $table->decimal('roas', 8, 2)->nullable();
            $table->date('period_month');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
