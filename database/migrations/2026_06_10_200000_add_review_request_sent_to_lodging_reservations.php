<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodging_reservations', function (Blueprint $table): void {
            $table->boolean('review_request_sent')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('lodging_reservations', function (Blueprint $table): void {
            $table->dropColumn('review_request_sent');
        });
    }
};
