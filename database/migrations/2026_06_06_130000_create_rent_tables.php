<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('license_plate', 64)->nullable()->index();
            $table->string('chassis_vin', 190)->nullable();
            $table->string('brand', 128)->nullable();
            $table->string('model_name', 128)->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->string('tire_type', 128)->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->date('insurance_end_date')->nullable();
            $table->boolean('insurance_12_months')->default(false);
            $table->date('itp_date')->nullable();
            $table->date('itp_expiry_date')->nullable();
            $table->integer('current_km')->nullable();
            $table->decimal('monthly_rent_price', 12, 2)->nullable();
            $table->decimal('daily_rent_price', 12, 2)->nullable();
            $table->decimal('warranty_standard', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32)->default('available')->index();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_vehicles_source_external_unique');
            $table->index('itp_expiry_date', 'rent_vehicles_itp_expiry_index');
            $table->index('insurance_end_date', 'rent_vehicles_insurance_end_index');
        });
        DB::statement("ALTER TABLE rent_vehicles ADD CONSTRAINT rent_vehicles_status_check CHECK (status IN ('available','rented','service'))");

        Schema::create('rent_vehicle_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rent_vehicle_id')->constrained('rent_vehicles')->cascadeOnDelete();
            $table->text('path');
            $table->string('caption')->nullable();
            $table->timestampsTz();
        });

        Schema::create('rent_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('name')->nullable()->index();
            $table->string('phone', 64)->nullable();
            $table->string('normalized_phone', 64)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('identity_document', 190)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_clients_source_external_unique');
        });

        Schema::create('rent_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64)->default('manual');
            $table->string('external_id', 190)->nullable();
            $table->string('contract_code', 190)->nullable();
            $table->foreignId('rent_vehicle_id')->nullable()->constrained('rent_vehicles')->nullOnDelete();
            $table->foreignId('rent_client_id')->nullable()->constrained('rent_clients')->nullOnDelete();
            $table->string('usage_type', 32);
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->integer('km_at_handover')->nullable();
            $table->integer('km_at_return')->nullable();
            $table->decimal('daily_price', 12, 2)->nullable();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('warranty_collected', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['source', 'external_id'], 'rent_contracts_source_external_unique');
            $table->index(['rent_vehicle_id', 'status'], 'rent_contracts_vehicle_status_index');
            $table->index(['status', 'end_date'], 'rent_contracts_status_end_index');
        });
        DB::statement("ALTER TABLE rent_contracts ADD CONSTRAINT rent_contracts_usage_type_check CHECK (usage_type IN ('rent','uber','bolt'))");
        DB::statement("ALTER TABLE rent_contracts ADD CONSTRAINT rent_contracts_status_check CHECK (status IN ('active','completed','cancelled'))");

        Schema::create('rent_maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rent_vehicle_id')->constrained('rent_vehicles')->cascadeOnDelete();
            $table->timestampTz('service_at')->nullable()->index();
            $table->integer('mileage_at_service')->nullable();
            $table->string('intervention_type', 190)->nullable();
            $table->integer('next_service_km')->nullable();
            $table->text('details')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_maintenance_records');
        Schema::dropIfExists('rent_contracts');
        Schema::dropIfExists('rent_clients');
        Schema::dropIfExists('rent_vehicle_images');
        Schema::dropIfExists('rent_vehicles');
    }
};
