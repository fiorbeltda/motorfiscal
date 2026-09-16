<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scenario_key')->unique();
            $table->string('name');
            $table->string('family');
            $table->jsonb('input');
            $table->jsonb('expected')->nullable();
            $table->string('input_hash', 64);
            $table->timestampsTz();
        });

        Schema::create('fiscal_decision_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('scenario_id')->nullable()->constrained('fiscal_scenarios')->nullOnDelete();
            $table->foreignUuid('rule_bundle_id')->nullable()->constrained('fiscal_rule_bundles')->restrictOnDelete();
            $table->string('status');
            $table->date('competence_date');
            $table->timestampTz('knowledge_at');
            $table->string('engine_version');
            $table->string('input_hash', 64);
            $table->string('decision_hash', 64)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('fiscal_fact_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('decision_run_id')->constrained('fiscal_decision_runs')->cascadeOnDelete();
            $table->string('fact_key');
            $table->string('value_type');
            $table->jsonb('value');
            $table->string('source_type');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampTz('recorded_at');
            $table->jsonb('evidence')->nullable();
            $table->unique(['decision_run_id', 'fact_key']);
        });

        Schema::create('fiscal_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('decision_run_id')->unique()->constrained('fiscal_decision_runs')->cascadeOnDelete();
            $table->string('status');
            $table->jsonb('payload');
            $table->string('payload_hash', 64);
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('fiscal_decision_traces', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('decision_run_id')->constrained('fiscal_decision_runs')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->unique(['decision_run_id', 'sequence']);
        });

        Schema::create('fiscal_question_answers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('decision_run_id')->constrained('fiscal_decision_runs')->cascadeOnDelete();
            $table->string('fact_key');
            $table->jsonb('answer');
            $table->timestampTz('answered_at');
            $table->index(['decision_run_id', 'fact_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_question_answers');
        Schema::dropIfExists('fiscal_decision_traces');
        Schema::dropIfExists('fiscal_decisions');
        Schema::dropIfExists('fiscal_fact_snapshots');
        Schema::dropIfExists('fiscal_decision_runs');
        Schema::dropIfExists('fiscal_scenarios');
    }
};
