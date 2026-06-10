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
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('shift_type'); // 'zi' or 'noapte'
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('raw_employee_name')->nullable();
            $table->timestamps();

            $table->unique(['date', 'shift_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
