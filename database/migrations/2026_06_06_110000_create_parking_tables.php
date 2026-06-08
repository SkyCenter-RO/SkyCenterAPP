<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->integer('total_spaces')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parking_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->string('code', 16);
            $table->integer('capacity')->default(0);
            $table->timestampsTz();
            $table->unique(['lot_id', 'code'], 'parking_zones_lot_code_unique');
        });

        Schema::create('parking_customers', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_customers_source_external_unique');
        });

        Schema::create('parking_spaces', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('parking_zones')->nullOnDelete();
            $table->string('label', 64)->nullable();
            $table->boolean('requires_keys')->default(false);
            $table->string('vehicle_type_suitability', 128)->nullable();
            $table->unsignedBigInteger('blocks_space_id')->nullable();
            $table->unsignedBigInteger('blocked_by_space_id')->nullable();
            $table->text('xy_map_location')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_spaces_source_external_unique');
        });

        Schema::create('parking_prices', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('vehicle_type', 64)->index();
            $table->integer('min_days')->nullable();
            $table->integer('max_days')->nullable();
            $table->decimal('price_per_day', 10, 2)->nullable();
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_prices_source_external_unique');
        });
        DB::statement("ALTER TABLE parking_prices ADD CONSTRAINT parking_prices_vehicle_type_check CHECK (vehicle_type IN ('autoturism','SUV','dubă'))");

        Schema::create('parking_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('parking_customers')->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('parking_lots')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('parking_zones')->nullOnDelete();
            $table->foreignId('parking_space_id')->nullable()->constrained('parking_spaces')->nullOnDelete();
            $table->string('status', 32)->default('pending_approval')->index();
            $table->string('plate', 64)->nullable();
            $table->string('normalized_plate', 64)->nullable()->index();
            $table->string('vehicle_type', 64)->nullable();
            $table->timestampTz('check_in_at')->nullable()->index();
            $table->timestampTz('check_out_at')->nullable()->index();
            $table->decimal('days', 6, 2)->nullable();
            $table->integer('adults')->nullable();
            $table->integer('children')->nullable();
            $table->boolean('keys_left')->default(false);
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('quoted_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->boolean('paid')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('review_request_sent')->default(false);
            $table->timestampTz('source_created_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'parking_reservations_source_external_unique');
            $table->index(['customer_id', 'status'], 'parking_reservations_customer_status_index');
            $table->index(['status', 'check_in_at'], 'parking_reservations_status_checkin_index');
            $table->index(['status', 'check_out_at'], 'parking_reservations_status_checkout_index');
            $table->index(['vehicle_type'], 'parking_reservations_vehicle_type_index');
        });
        DB::statement("ALTER TABLE parking_reservations ADD CONSTRAINT parking_reservations_status_check CHECK (status IN ('pending_approval','booked','parked','departed','cancelled'))");
        DB::statement("ALTER TABLE parking_reservations ADD CONSTRAINT parking_reservations_vehicle_type_check CHECK (vehicle_type IS NULL OR vehicle_type IN ('autoturism','SUV','dubă'))");

        Schema::create('parking_reservation_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parking_reservation_id')->constrained('parking_reservations')->cascadeOnDelete();
            $table->text('path');
            $table->string('caption')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parking_status_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parking_reservation_id')->constrained('parking_reservations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->timestampTz('changed_at')->useCurrent();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_status_audits');
        Schema::dropIfExists('parking_reservation_images');
        Schema::dropIfExists('parking_reservations');
        Schema::dropIfExists('parking_prices');
        Schema::dropIfExists('parking_spaces');
        Schema::dropIfExists('parking_customers');
        Schema::dropIfExists('parking_zones');
        Schema::dropIfExists('parking_lots');
    }
};
