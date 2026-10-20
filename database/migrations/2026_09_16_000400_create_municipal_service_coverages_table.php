<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_service_coverages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('source_version_id')->constrained('fiscal_source_versions')->cascadeOnDelete();
            $table->string('municipality_ibge', 7);
            $table->string('service_code', 32);
            $table->unsignedBigInteger('row_count');
            $table->unsignedBigInteger('unknown_rate_count');
            $table->date('earliest_effective_from');
            $table->date('latest_effective_to')->nullable();
            $table->string('status');
            $table->timestampsTz();
            $table->unique(['source_version_id', 'municipality_ibge', 'service_code'], 'municipal_service_coverage_unique');
            $table->index(['municipality_ibge', 'service_code'], 'municipal_service_coverage_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_service_coverages');
    }
};
