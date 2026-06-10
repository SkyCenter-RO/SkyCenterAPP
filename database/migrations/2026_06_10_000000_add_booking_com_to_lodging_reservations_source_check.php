<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lodging_reservations DROP CONSTRAINT lodging_reservations_source_check');
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_source_check CHECK (source IN ('manual','booking','booking_com','airbnb','direct','gmail'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lodging_reservations DROP CONSTRAINT lodging_reservations_source_check');
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_source_check CHECK (source IN ('manual','booking','airbnb','direct','gmail'))");
    }
};
