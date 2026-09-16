<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_key')->unique();
            $table->string('name');
            $table->string('authority_type');
            $table->string('status');
            $table->timestampsTz();
        });

        Schema::create('fiscal_source_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_id')->constrained('fiscal_sources')->restrictOnDelete();
            $table->string('version');
            $table->string('sha256', 64);
            $table->string('original_name');
            $table->text('storage_path');
            $table->date('published_on')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestampTz('obtained_at');
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
            $table->unique(['source_id', 'version']);
            $table->unique(['source_id', 'sha256']);
        });

        Schema::create('fiscal_import_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_version_id')->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->string('importer');
            $table->string('status');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('manifest')->default('{}');
            $table->jsonb('report')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('fiscal_catalogs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('catalog_key')->unique();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('fiscal_catalog_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_id')->constrained('fiscal_catalogs')->restrictOnDelete();
            $table->foreignUuid('source_version_id')->nullable()->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->string('version');
            $table->string('content_hash', 64);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampTz('superseded_at')->nullable();
            $table->string('status');
            $table->unique(['catalog_id', 'version']);
        });

        Schema::create('fiscal_catalog_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('catalog_version_id')->constrained('fiscal_catalog_versions')->cascadeOnDelete();
            $table->string('entry_key');
            $table->jsonb('payload');
            $table->string('entry_hash', 64);
            $table->unique(['catalog_version_id', 'entry_key']);
            $table->index('entry_key');
        });

        Schema::create('fiscal_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('rule_key')->unique();
            $table->string('kind');
            $table->timestampsTz();
        });

        Schema::create('fiscal_rule_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('fiscal_rules')->restrictOnDelete();
            $table->foreignUuid('source_version_id')->nullable()->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->string('version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('recorded_at');
            $table->timestampTz('superseded_at')->nullable();
            $table->string('status');
            $table->string('content_hash', 64);
            $table->jsonb('payload');
            $table->unique(['rule_id', 'version']);
        });

        Schema::create('fiscal_rule_relations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('dominant_rule_version_id')->constrained('fiscal_rule_versions')->cascadeOnDelete();
            $table->foreignUuid('subordinate_rule_version_id')->constrained('fiscal_rule_versions')->cascadeOnDelete();
            $table->string('relation_type');
            $table->foreignUuid('source_version_id')->nullable()->constrained('fiscal_source_versions')->restrictOnDelete();
            $table->unique(['dominant_rule_version_id', 'subordinate_rule_version_id', 'relation_type'], 'fiscal_rule_relation_unique');
        });

        Schema::create('fiscal_rule_bundles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('bundle_key');
            $table->string('version');
            $table->string('content_hash', 64)->unique();
            $table->string('status');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['bundle_key', 'version']);
        });

        Schema::create('fiscal_rule_bundle_items', function (Blueprint $table): void {
            $table->foreignUuid('bundle_id')->constrained('fiscal_rule_bundles')->cascadeOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('fiscal_rule_versions')->restrictOnDelete();
            $table->primary(['bundle_id', 'rule_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_rule_bundle_items');
        Schema::dropIfExists('fiscal_rule_bundles');
        Schema::dropIfExists('fiscal_rule_relations');
        Schema::dropIfExists('fiscal_rule_versions');
        Schema::dropIfExists('fiscal_rules');
        Schema::dropIfExists('fiscal_catalog_entries');
        Schema::dropIfExists('fiscal_catalog_versions');
        Schema::dropIfExists('fiscal_catalogs');
        Schema::dropIfExists('fiscal_import_runs');
        Schema::dropIfExists('fiscal_source_versions');
        Schema::dropIfExists('fiscal_sources');
    }
};
