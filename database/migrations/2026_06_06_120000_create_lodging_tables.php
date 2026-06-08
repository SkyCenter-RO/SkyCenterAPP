<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lodging_properties', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name');
            $table->string('slug', 190)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'lodging_properties_source_external_unique');
        });

        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('property_id')->constrained('lodging_properties')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rooms_source_external_unique');
            $table->index(['property_id', 'name'], 'rooms_property_name_index');
        });

        Schema::create('lodging_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('status', 32)->nullable()->index();
            $table->date('check_in')->nullable()->index();
            $table->date('check_out')->nullable()->index();
            $table->integer('nights')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('direct_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->timestampTz('source_created_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'lodging_reservations_source_external_unique');
            $table->index(['room_id', 'status'], 'lodging_reservations_room_status_index');
            $table->index(['room_id', 'check_in'], 'lodging_reservations_room_checkin_index');
            $table->index(['room_id', 'check_out'], 'lodging_reservations_room_checkout_index');
        });
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_status_check CHECK (status IS NULL OR status IN ('pending','confirmed','checked_in','checked_out','cancelled'))");
        DB::statement("ALTER TABLE lodging_reservations ADD CONSTRAINT lodging_reservations_source_check CHECK (source IN ('manual','booking','airbnb','direct','gmail'))");

        Schema::create('lodging_sync_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('lodging_properties')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('channel', 32);
            $table->text('ical_url');
            $table->timestampTz('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE lodging_sync_links ADD CONSTRAINT lodging_sync_links_channel_check CHECK (channel IN ('booking','airbnb'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('lodging_sync_links');
        Schema::dropIfExists('lodging_reservations');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('lodging_properties');
    }
};
