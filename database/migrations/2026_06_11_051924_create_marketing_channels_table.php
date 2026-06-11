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
        Schema::create('marketing_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('channel_type', 64); // ads|seo|social|listing|affiliate|email
            $table->string('status', 32)->default('setup_needed'); // active|setup_needed|paused|monitoring|blocked
            $table->text('url')->nullable();
            $table->string('account_id', 255)->nullable();
            $table->decimal('monthly_budget_eur', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_channels');
    }
};
