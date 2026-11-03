<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\EntityDomicile151;
use RuntimeException;
use Throwable;

final class QualifyEntityDomicile151Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:qualify-entity-domicile {operation : Bound entity domicile evidence JSON}';

    protected $description = 'Qualify party domicile from bound registry, establishment or material address evidence';

    public function handle(): int
    {
        try {
            $raw = file_get_contents((string) $this->argument('operation'));
            if ($raw === false) {
                throw new RuntimeException('Readable operation JSON required.');
            }
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input)) {
                throw new RuntimeException('Operation JSON object required.');
            }
            $codes = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json')), true, 512, JSON_THROW_ON_ERROR);
            $qualifier = new EntityDomicile151($codes['observed_geographic_reference'], $codes['observed_geographic_reference_policy']);
            $report = $qualifier->qualify($input);
            $replayed = isset($report['report_hash']) && $qualifier->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replayed]);

            return in_array($report['status'], ['LOCAL_ENTITY_DOMICILE_QUALIFIED', 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED', 'LOCAL_PARTY_DOMICILE_QUALIFIED'], true)
                && $replayed ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'production_ready' => false]);

            return self::FAILURE;
        }
    }
}
