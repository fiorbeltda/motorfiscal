<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_catalog_versions', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_catalog_versions', function (Blueprint $table): void {
            $table->date('effective_from')->nullable(false)->change();
        });
    }
};
