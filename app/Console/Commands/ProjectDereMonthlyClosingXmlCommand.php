<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ConsumptionReform\DereFinancialNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\JsonFileWriter;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use App\Modules\Fiscal\NFSe\DereNegativeBaseClosingOptionsXmlProjector;
use RuntimeException;
use Throwable;

final class ProjectDereMonthlyClosingXmlCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dere:project-monthly-closing {snapshot} {month}
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.02-17.json}
        {--candidate-output=} {--report=} {--negative-base-request=} {--negative-base-settlement-request=}';

    protected $description = 'Project a local unsigned monthly D1199 candidate from replayed qualified RTC calculations';

    public function handle(): int
    {
        try {
            $snapshot = json_decode((string) file_get_contents((string) $this->argument('snapshot')), true, 512, JSON_THROW_ON_ERROR);
            $allocation = json_decode((string) file_get_contents((string) $this->argument('month')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot) || ! is_array($allocation) || array_is_list($allocation)
                || ! is_array($snapshot['input'] ?? null) || ! is_array($snapshot['knowledge'] ?? null)) {
                throw new RuntimeException('Snapshot and allocation must bind structured input and captured knowledge.');
            }
            $factory = new FiscalEngineFactory;
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            if (($snapshot['knowledge']['content_hashes']['bundle_manifest'] ?? null) !== $knowledge['content_hashes']['bundle_manifest']) {
                throw new RuntimeException('Selected bundle differs from captured snapshot.');
            }
            $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $result = (new RtcScenarioRunService($factory))->evaluate($snapshot['knowledge'], $scenario)->toArray();
            if (CanonicalJson::encode($result) !== CanonicalJson::encode($snapshot['result'] ?? null)
                || ($result['status'] ?? null) !== 'ACTION_REQUIRED'
                || ($result['reason_codes'] ?? null) !== ['RTC_FINANCIAL_DOCUMENT_PENDING']) {
                throw new RuntimeException('Snapshot must reproduce the specific financial document-pending stage; it is not a final integrated decision.');
            }
            $stages = array_column($result['trace'], null, 'stage');
            $policy = $knowledge['documents']['rtc_code_catalog']['dere_monthly_closing_projection'] ?? null;
            if (! is_array($policy) || ! is_array($policy['xsd'] ?? null)) {
                throw new RuntimeException('Bundle does not bind a DeRE projection policy.');
            }
            $monthlyRules = ['closing' => $policy,
                'balance' => $knowledge['documents']['rtc_code_catalog']['dere_trial_balance_projection'] ?? [],
                'deductions' => $knowledge['documents']['rtc_code_catalog']['dere_deduction_projection'] ?? [],
                'pgcc_projection' => $knowledge['documents']['rtc_code_catalog']['dere_pgcc_projection'] ?? [],
                'contributor_projection' => $knowledge['documents']['rtc_code_catalog']['dere_contributor_projection'] ?? []];
            $projection = (new DereMonthlyClosingXmlProjector)->project($stages['FINANCIAL_OPERATION_AND_BASE'], $allocation,
                $scenario->input->context->knowledgeAt, $monthlyRules);
            if ($projection['status'] !== 'PROJECTED' || ! is_string($projection['xml'] ?? null)) {
                $this->outputJson($projection);

                return self::FAILURE;
            }
            $settlementRequestPath = $this->option('negative-base-settlement-request');
            if (is_string($settlementRequestPath) && $settlementRequestPath !== '') {
                $settlementRequest = json_decode((string) file_get_contents($settlementRequestPath), true, 512, JSON_THROW_ON_ERROR);
                $publicOffer = is_array($settlementRequest) && array_key_exists('current_public_offer_return', $settlementRequest);
                $settlementPolicyKey = $publicOffer ? 'dere_public_offer_negative_base_local_settlement' : 'dere_negative_base_local_settlement';
                $settlementPolicy = $knowledge['documents']['rtc_code_catalog'][$settlementPolicyKey] ?? null;
                if (! is_array($settlementPolicy)) {
                    throw new RuntimeException('Selected bundle does not qualify a negative-base settlement request.');
                }
                if (! is_array($settlementRequest) || array_is_list($settlementRequest)
                    || ($settlementRequest['snapshot_hash'] ?? null) !== CanonicalJson::hash($snapshot)
                    || ($settlementRequest['monthly_input_hash'] ?? null) !== CanonicalJson::hash($allocation)
                    || ($settlementRequest['baseline_projection_hash'] ?? null) !== CanonicalJson::hash($projection)) {
                    throw new RuntimeException('Settlement request differs from the replayed snapshot, monthly input or original projection.');
                }
                $settlementPlanner = $publicOffer ? new DereFinancialPublicOfferNegativeBaseSettlementPlanner : new DereFinancialNegativeBaseSettlementPlanner;
                $settlement = $settlementPlanner->plan($stages['FINANCIAL_OPERATION_AND_BASE'],
                    $allocation, $stages['FINANCIAL_GROSS_DEBITS'], $scenario->input->context->knowledgeAt, $monthlyRules,
                    $settlementRequest, $settlementPolicy, resource_path('fiscal/sources/official'));
                if (($settlement['status'] ?? null) !== 'LOCAL_BCN_TAX_SETTLEMENT_PROPOSED') {
                    $this->outputJson($settlement);

                    return self::FAILURE;
                }
            }
            $negativeRequestPath = $this->option('negative-base-request');
            $deriveNegativeOptions = isset($settlementRequest) && (! is_string($negativeRequestPath) || $negativeRequestPath === '');
            if ((is_string($negativeRequestPath) && $negativeRequestPath !== '') || $deriveNegativeOptions) {
                $negativePolicy = $knowledge['documents']['rtc_code_catalog']['dere_negative_base_closing_options_projection'] ?? null;
                if (! is_array($negativePolicy)) {
                    throw new RuntimeException('Selected bundle does not qualify a negative-base closing request.');
                }
                $negativeRequest = $deriveNegativeOptions ? $settlementRequest
                    : json_decode((string) file_get_contents((string) $negativeRequestPath), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($negativeRequest) || array_is_list($negativeRequest)) {
                    throw new RuntimeException('Negative-base request must be a structured source-bound object.');
                }
                if (($negativeRequest['snapshot_hash'] ?? null) !== CanonicalJson::hash($snapshot)
                    || ($negativeRequest['monthly_input_hash'] ?? null) !== CanonicalJson::hash($allocation)) {
                    throw new RuntimeException('Negative-base request differs from the replayed snapshot or monthly input.');
                }
                if (isset($settlementRequest) && (
                    CanonicalJson::hash($negativeRequest['parameters'] ?? null) !== CanonicalJson::hash($settlementRequest['parameters'] ?? null)
                    || CanonicalJson::hash($negativeRequest['prior_closing_events'] ?? null) !== CanonicalJson::hash($settlementRequest['prior_closing_events'] ?? null))) {
                    throw new RuntimeException('Declaration and settlement requests must bind identical parameters and prior closing events.');
                }
                $negativeProjection = (new DereNegativeBaseClosingOptionsXmlProjector)->project($projection,
                    $negativeRequest, $scenario->input->context->knowledgeAt, $negativePolicy, resource_path('fiscal/sources/official'));
                if ($negativeProjection['status'] !== 'PROJECTED' || ! is_string($negativeProjection['xml'] ?? null)) {
                    $this->outputJson($negativeProjection);

                    return self::FAILURE;
                }
                $projection['xml'] = $negativeProjection['xml'];
                unset($negativeProjection['xml']);
                $projection['negative_base_options_projection'] = $negativeProjection;
            }
            $schema = $policy['xsd'];
            foreach (['source_file', 'signature_source_file'] as $key) {
                if (! is_string($schema[$key] ?? null) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $schema[$key])) {
                    throw new RuntimeException('Invalid bound schema filename.');
                }
            }
            $components = ['D1101' => $projection['balance'], 'D1199' => $projection];
            if ($projection['deductions'] !== null && ($projection['deduction_event_required'] ?? true)) {
                $components['D1121'] = $projection['deductions'];
            }
            if (isset($projection['pgcc_projection'])) {
                $components['D1011'] = $projection['pgcc_projection'];
            }
            if (isset($projection['contributor_projection'])) {
                $components['D1001'] = $projection['contributor_projection'];
            }
            if (isset($projection['debt_no_movement_projection'])) {
                $components['D2101'] = $projection['debt_no_movement_projection'];
            }
            if (isset($projection['debt_principal_movement_projection'])) {
                $components['D2101'] = $projection['debt_principal_movement_projection'];
            }
            if (isset($projection['debt_yield_projection'])) {
                $components['D2101'] = $projection['debt_yield_projection'];
            }
            if (isset($settlement['composition']['active_version_binding'])) {
                $binding = $settlement['composition']['active_version_binding'];
                $active = $binding['active_event'] ?? null;
                if (! is_array($active) || ! is_string($active['origin_xml'] ?? null) || ! isset($components['D2101'])) {
                    throw new RuntimeException('Active public document output is not bound to a qualified monthly component.');
                }
                $components['D2101']['xml'] = $active['origin_xml'];
                $projection['active_public_event_output'] = ['sequence' => $active['sequence'], 'receipt' => $active['receipt'],
                    'origin_xml_sha256' => hash('sha256', $active['origin_xml']),
                    'native_origin_xml_sha256' => $binding['native_origin_xml_sha256'],
                    'business_payload_hash' => $binding['business_payload_hash'], 'government_return_authenticated' => false];
            }
            $validations = [];
            foreach ($components as $name => $component) {
                $debtKey = isset($projection['debt_yield_projection']) ? 'debt_yield_projection'
                    : (isset($projection['debt_principal_movement_projection']) ? 'debt_principal_movement_projection' : 'debt_no_movement_projection');
                $componentSchema = $name === 'D2101' ? ($policy[$debtKey]['xsd'] ?? null) : $schema;
                if (! is_array($componentSchema)) {
                    throw new RuntimeException('Missing bound component schema.');
                }
                foreach (['source_file', 'signature_source_file'] as $key) {
                    if (! is_string($componentSchema[$key] ?? null) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $componentSchema[$key])) {
                        throw new RuntimeException('Invalid component schema filename.');
                    }
                }
                $validations[$name] = (new DereXmlSchemaValidator)->validate($component['xml'], resource_path('fiscal/sources/official/'.$componentSchema['source_file']),
                    $componentSchema['source_sha256'], $component['schema'],
                    resource_path('fiscal/sources/official/'.$componentSchema['signature_source_file']), $componentSchema['signature_source_sha256']);
            }
            $validation = $validations['D1199'];
            $allChecked = array_all($validations, static fn (array $value): bool => $value['reason'] === 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE');
            $candidate = $this->option('candidate-output');
            $outputs = [];
            if (is_string($candidate) && $candidate !== '' && $allChecked) {
                foreach ($components as $name => $component) {
                    $outputs[$name] = $name === 'D1199' ? $candidate : $candidate.'.'.$name.'.xml';
                    if (file_exists($outputs[$name])) {
                        throw new RuntimeException('Refusing to overwrite candidate XML.');
                    }
                }
                $reportPath = $this->option('report');
                if (is_string($reportPath) && $reportPath !== '' && file_exists($reportPath)) {
                    throw new RuntimeException('Refusing to overwrite monthly report.');
                }
                foreach ($components as $name => $component) {
                    $outputPath = $outputs[$name] ?? throw new RuntimeException('Missing monthly candidate output path.');
                    $stream = @fopen($outputPath, 'x');
                    if ($stream === false) {
                        throw new RuntimeException('Unable to create local monthly candidate.');
                    }
                    try {
                        if (fwrite($stream, $component['xml']) !== strlen($component['xml']) || ! fflush($stream)) {
                            throw new RuntimeException('Unable to write local monthly candidate.');
                        }
                    } finally {
                        fclose($stream);
                    }
                }
            }
            unset($projection['xml'], $projection['balance']['xml'], $projection['deductions']['xml']);
            if (isset($projection['pgcc_projection'])) {
                unset($projection['pgcc_projection']['xml']);
            }
            if (isset($projection['contributor_projection'])) {
                unset($projection['contributor_projection']['xml']);
            }
            if (isset($projection['debt_no_movement_projection'])) {
                unset($projection['debt_no_movement_projection']['xml']);
            }
            if (isset($projection['debt_principal_movement_projection'])) {
                unset($projection['debt_principal_movement_projection']['xml']);
            }
            if (isset($projection['debt_yield_projection'])) {
                unset($projection['debt_yield_projection']['xml']);
            }
            $report = ['status' => $allChecked ? 'ACTION_REQUIRED' : 'INVALID', 'reason' => $allChecked ? 'RTC_DERE_UNSIGNED_LOCAL_MONTHLY_CANDIDATES' : 'RTC_DERE_MONTHLY_SCHEMA_VALIDATION_FAILED', 'production_ready' => false,
                'scope' => 'LOCAL_UNSIGNED_D1199_CANDIDATE_CADASTRO_PGCC_REFERENCE_AND_FULL_HOMOLOGATION_PENDING',
                'bundle_id' => $knowledge['rule_bundle_id'], 'snapshot_hash' => CanonicalJson::hash($snapshot),
                'decision_hash' => $result['decision_hash'], 'monthly_input_hash' => CanonicalJson::hash($allocation),
                'projection' => array_diff_key($projection, ['xml' => true]), 'schema_validation' => $validation, 'event_validations' => $validations, 'event_outputs' => $outputs,
                'candidate_output' => is_string($candidate) && $candidate !== '' ? $candidate : null];
            if (isset($negativeRequest)) {
                $report['negative_base_request_hash'] = CanonicalJson::hash($negativeRequest);
                $report['negative_base_request_input'] = $negativeRequest;
            }
            if (isset($settlement, $settlementRequest)) {
                $report['negative_base_settlement'] = $settlement;
                $report['negative_base_settlement_request_hash'] = CanonicalJson::hash($settlementRequest);
                $report['negative_base_settlement_request_input'] = $settlementRequest;
                $report['negative_base_options_request_origin'] = $deriveNegativeOptions ? 'DERIVED_FROM_SETTLEMENT_REQUEST' : 'EXPLICIT_MATCHING_REQUEST';
            }
            $path = $this->option('report');
            if (is_string($path) && $path !== '') {
                (new JsonFileWriter)->write($path, $report);
            }
            $this->outputJson($report);

            return $allChecked ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
