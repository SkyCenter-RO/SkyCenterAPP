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
        Schema::create('marketing_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 64); // google|booking|facebook|tripadvisor|airbnb
            $table->string('vertical', 32)->nullable(); // hotel|parcare|rent|all
            $table->decimal('score', 3, 2);
            $table->integer('review_count')->nullable();
            $table->date('recorded_on')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_reviews');
    }
};
