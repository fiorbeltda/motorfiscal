<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipal_iss_rates', function (Blueprint $table): void {
            $table->string('ctribnac_code', 6)->nullable()->after('service_code');
            $table->string('municipal_service_code', 3)->nullable()->after('ctribnac_code');
            $table->index(['municipality_ibge', 'ctribnac_code', 'effective_from'], 'municipal_iss_rate_ctribnac_lookup');
        });

        DB::table('municipal_iss_rates')->orderBy('id')->chunkById(1000, function ($rows): void {
            foreach ($rows as $row) {
                $serviceCode = (string) $row->service_code;
                if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})\.(\d{3})$/', $serviceCode, $matches) !== 1) {
                    continue;
                }
                DB::table('municipal_iss_rates')->where('id', $row->id)->update([
                    'ctribnac_code' => $matches[1].$matches[2].$matches[3],
                    'municipal_service_code' => $matches[4],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('municipal_iss_rates', function (Blueprint $table): void {
            $table->dropIndex('municipal_iss_rate_ctribnac_lookup');
            $table->dropColumn(['ctribnac_code', 'municipal_service_code']);
        });
    }
};
