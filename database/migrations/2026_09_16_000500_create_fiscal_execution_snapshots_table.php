<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_execution_snapshots', function (Blueprint $table): void {
            $table->foreignUuid('decision_run_id')->primary()->constrained('fiscal_decision_runs')->cascadeOnDelete();
            $table->jsonb('payload');
            $table->string('payload_hash', 64)->index();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_execution_snapshots');
    }
};
