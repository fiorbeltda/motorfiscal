<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\ServiceLocality149;
use RuntimeException;
use Throwable;

final class QualifyServiceLocality149Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:rtc:qualify-service-locality {operation : Bound service locality evidence JSON}';

    protected $description = 'Qualify material special-service locality without selecting catalog codes or tax rates';

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
            $qualifier = new ServiceLocality149($codes['observed_geographic_reference'], $codes['observed_geographic_reference_policy']);
            $report = $qualifier->qualify($input);
            $replayed = isset($report['report_hash']) && $qualifier->replay($report);
            $this->outputJson(['report' => $report, 'replay_matches' => $replayed]);

            return in_array($report['status'], ['LOCAL_SERVICE_LOCATION_QUALIFIED', 'LOCAL_TERRITORIAL_LOCATION_QUALIFIED', 'LOCAL_SERVICE_ENTITY_DOMICILE_QUALIFIED', 'LOCAL_SERVICE_CENTRALIZED_DOMICILE_QUALIFIED', 'LOCAL_SERVICE_PARTY_DOMICILE_QUALIFIED'], true)
                && $replayed ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'production_ready' => false]);

            return self::FAILURE;
        }
    }
}
