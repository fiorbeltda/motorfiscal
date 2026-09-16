<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iss_location_rules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('catalog_version_id')->constrained('fiscal_catalog_versions')->cascadeOnDelete();
            $table->string('ctribnac_code', 16);
            $table->string('strategy', 64)->nullable();
            $table->string('import_override', 64)->nullable();
            $table->string('required_detail_group', 32)->nullable();
            $table->jsonb('payload');
            $table->unique(['catalog_version_id', 'ctribnac_code']);
            $table->index('ctribnac_code');
        });

        Schema::create('municipal_iss_rates', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('source_version_id')->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->string('municipality_ibge', 7);
            $table->char('uf', 2);
            $table->string('municipality_name');
            $table->string('service_code', 32);
            $table->string('incidence_raw')->nullable();
            $table->decimal('rate', 9, 6)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('validity_key', 64);
            $table->string('raw_row_hash', 64);
            $table->timestampsTz();
            $table->unique(['source_version_id', 'validity_key'], 'municipal_iss_rate_version_unique');
            $table->index(['municipality_ibge', 'service_code', 'effective_from'], 'municipal_iss_rate_lookup');
        });

        Schema::create('municipal_parameter_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('municipality_ibge', 7);
            $table->string('service_code', 32);
            $table->date('competence_date');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('source_type');
            $table->foreignUuid('source_version_id')->nullable()->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->timestampTz('fetched_at')->nullable();
            $table->jsonb('payload');
            $table->string('payload_hash', 64);
            $table->timestampsTz();
            $table->unique(['municipality_ibge', 'service_code', 'competence_date', 'payload_hash'], 'municipal_snapshot_unique');
            $table->index(['municipality_ibge', 'service_code', 'competence_date'], 'municipal_snapshot_lookup');
        });

        Schema::create('municipal_coverage_statuses', function (Blueprint $table): void {
            $table->string('municipality_ibge', 7)->primary();
            $table->boolean('rates_available')->default(false);
            $table->boolean('api_available')->default(false);
            $table->boolean('retention_available')->default(false);
            $table->boolean('regimes_available')->default(false);
            $table->boolean('benefits_available')->default(false);
            $table->string('status');
            $table->timestampTz('last_checked_at')->nullable();
            $table->jsonb('notes')->default('[]');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_coverage_statuses');
        Schema::dropIfExists('municipal_parameter_snapshots');
        Schema::dropIfExists('municipal_iss_rates');
        Schema::dropIfExists('iss_location_rules');
    }
};
