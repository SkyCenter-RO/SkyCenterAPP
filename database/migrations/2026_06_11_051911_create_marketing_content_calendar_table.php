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
        Schema::create('marketing_content_calendar', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('platform', 64); // facebook|instagram|tiktok|all
            $table->string('vertical', 32)->nullable();
            $table->string('content_type', 64); // photo|reel|story|carousel|text
            $table->string('language', 8)->default('ro');
            $table->string('status', 32)->default('idea'); // idea|in_progress|ready|scheduled|published|cancelled
            $table->date('scheduled_at')->nullable();
            $table->date('published_at')->nullable();
            $table->text('copy_text')->nullable();
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
        Schema::dropIfExists('marketing_content_calendar');
    }
};
