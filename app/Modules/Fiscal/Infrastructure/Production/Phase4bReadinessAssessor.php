<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Production;

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\DpsForeignAcquirerCandidate;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;
use JsonException;
use RuntimeException;

final readonly class Phase4bReadinessAssessor
{
    private const REQUIRED_CONTROLS = [
        '4B.1.TEMPORALITY', '4B.2.INCIDENCE_AND_DESTINATION', '4B.2.EXPORT_IMPORT',
        '4B.3.SUBJECTS_AND_COLLECTION', '4B.3.SIMPLES_MEI', '4B.4.BASE_AND_SPECIAL_REGIMES',
        '4B.5.CREDITS', '4B.6.CLASSIFICATION', '4B.7.DOCUMENT', '4B.8.CORPUS',
        'INTEGRATED.ISS_MUNICIPAL', 'INTEGRATED.4A',
    ];

    public function __construct(private FiscalEngineFactory $factory) {}

    /** @return array<string, mixed> */
    public function assess(string $resourceRoot, string $bundlePath, string $controlsPath, ?string $approvalPath = null, ?string $trustedReviewerKeyPath = null): array
    {
        $knowledge = $this->factory->captureKnowledge($resourceRoot, $bundlePath);
        $documents = $knowledge['documents'];
        $manifest = $documents['bundle_manifest'];
        $controls = $this->readJson($controlsPath);
        if (($controls['scope'] ?? null) !== 'INTEGRATED_ISS_4A_4B' || ! is_array($controls['controls'] ?? null)) {
            throw new RuntimeException('Phase 4B production controls are invalid.');
        }

        $blockers = [];
        $controlResults = [];
        $seenControlIds = [];
        foreach ($controls['controls'] as $control) {
            if (! is_array($control) || ! is_string($control['id'] ?? null) || ! is_string($control['state'] ?? null)) {
                throw new RuntimeException('Phase 4B production control row is invalid.');
            }
            if (isset($seenControlIds[$control['id']])) {
                throw new RuntimeException('Phase 4B production control id is duplicated.');
            }
            $seenControlIds[$control['id']] = true;
            $approved = $control['state'] === 'APPROVED' && is_array($control['evidence'] ?? null)
                && $control['evidence'] !== [];
            $controlResults[] = ['id' => $control['id'], 'state' => $control['state'], 'approved' => $approved,
                'gap' => $approved ? null : ($control['gap'] ?? null)];
            if (! $approved) {
                $blockers[] = 'CONTROL_NOT_APPROVED:'.$control['id'];
            }
        }
        if ($controlResults === []) {
            $blockers[] = 'CONTROL_MATRIX_EMPTY';
        }
        foreach (self::REQUIRED_CONTROLS as $requiredId) {
            if (! isset($seenControlIds[$requiredId])) {
                $blockers[] = 'REQUIRED_CONTROL_MISSING:'.$requiredId;
            }
        }

        $sourceResults = [];
        foreach ($manifest['official_sources'] ?? [] as $source) {
            if (! is_array($source) || ! is_string($source['id'] ?? null) || ! is_string($source['file'] ?? null)
                || ! is_string($source['sha256'] ?? null) || basename($source['file']) !== $source['file']) {
                throw new RuntimeException('Official source inventory is invalid.');
            }
            $path = rtrim($resourceRoot, '/\\').DIRECTORY_SEPARATOR.'sources'.DIRECTORY_SEPARATOR.'official'.DIRECTORY_SEPARATOR.$source['file'];
            $valid = is_file($path) && hash_equals(strtolower($source['sha256']), (string) hash_file('sha256', $path));
            $sourceResults[] = ['id' => $source['id'], 'hash_valid' => $valid];
            if (! $valid) {
                $blockers[] = 'OFFICIAL_SOURCE_MISSING_OR_CHANGED:'.$source['id'];
            }
        }
        if ($sourceResults === []) {
            $blockers[] = 'OFFICIAL_SOURCE_INVENTORY_EMPTY';
        }

        if (($documents['municipal_parameters']['source_type'] ?? null) === 'FIXTURE') {
            $blockers[] = 'INTEGRATED_MUNICIPAL_PARAMETERS_ARE_FIXTURES';
        }
        foreach (['rtc_rate_catalog', 'rtc_code_catalog', 'rtc_transition_catalog'] as $catalogKey) {
            if (($documents[$catalogKey]['status'] ?? null) !== 'PRODUCTION_APPROVED') {
                $blockers[] = 'RTC_CATALOG_NOT_PRODUCTION_APPROVED:'.$catalogKey;
            }
        }
        if (is_array($documents['rtc_code_catalog']['partial_export_iss_result_policy'] ?? null)) {
            // The current contract only binds local service-result evidence; it never grants legal/document approval.
            $blockers[] = 'ISS_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY';
        }
        if (is_array($documents['rtc_code_catalog']['partial_export_sp_result_policy'] ?? null)) {
            $blockers[] = 'SP_SOFTWARE_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY';
        }
        if (array_key_exists('partial_export_dps_identity_policy', $documents['rtc_code_catalog'])) {
            $blockers[] = 'DPS_IDENTITY_LOCAL_MAPPING_ONLY_NOT_ISSUANCE_APPROVAL';
        }
        if (array_key_exists('partial_export_dated_documents_policy', $documents['rtc_code_catalog'])) {
            $blockers[] = 'DATED_ISS_DPS_LOCAL_EVIDENCE_ONLY_NOT_LEGAL_OR_DOCUMENT_APPROVAL';
        }
        if (array_key_exists('partial_export_document_prices_policy', $documents['rtc_code_catalog'])) {
            $blockers[] = 'DOCUMENT_PRICE_COMPOSITION_LOCAL_ONLY_NOT_ISS_BASE_FX_OR_ISSUANCE_APPROVAL';
        }
        if (isset($documents['rtc_code_catalog']['cclass_reference_policy']) || isset($documents['rtc_code_catalog']['cst_reference_rows'])) {
            $blockers[] = 'CCLASSTRIB_REFERENCE_LOCAL_ONLY_NOT_CLASSIFICATION_APPROVAL';
        }
        if (CIndOpReference126::required($documents['rtc_code_catalog'] ?? [])) {
            $blockers[] = 'CINDOP126_REFERENCE_LOCAL_ONLY_NOT_OFFICIAL_ENVIRONMENT_ACTIVATION';
        }
        foreach ($documents['rtc_code_catalog']['engine_decision_rules'] ?? [] as $id => $rule) {
            if (! is_array($rule) || ($rule['review_status'] ?? null) !== 'APPROVED') {
                $blockers[] = 'CLASSIFICATION_RULE_NOT_APPROVED:'.$id;
            }
        }
        foreach ($documents['rtc_code_catalog']['document_layouts'] ?? [] as $layout) {
            if (is_array($layout) && ($layout['status'] ?? null) === 'FUTURE_UNDEPLOYED') {
                $blockers[] = 'DOCUMENT_LAYOUT_DEPLOYMENT_NOT_PROVEN:'.($layout['version'] ?? 'UNKNOWN');
            }
        }
        $dpsXsd = $documents['rtc_code_catalog']['dps_xsd'] ?? null;
        if (is_array($dpsXsd)) {
            $xsdHash = $dpsXsd['source_sha256'] ?? null;
            $xsdSourceId = $documents['rtc_code_catalog']['sources']['dps_xsd']['source_id'] ?? 'NFSE-RTC-XSD';
            $officialXsd = null;
            foreach ($manifest['official_sources'] ?? [] as $source) {
                if (is_array($source) && ($source['id'] ?? null) === $xsdSourceId) {
                    $officialXsd = $source['sha256'] ?? null;
                    break;
                }
            }
            if (! is_string($xsdHash) || $xsdHash !== $officialXsd
                || ! is_array($documents['rtc_code_catalog']['dps_xsd_fields'] ?? null)
                || $documents['rtc_code_catalog']['dps_xsd_fields'] === []) {
                $blockers[] = 'RTC_DPS_XSD_CATALOG_NOT_BOUND_TO_OFFICIAL_SOURCE';
            }
            foreach ($documents['rtc_code_catalog']['document_layouts'] ?? [] as $layout) {
                if (is_array($layout) && ($layout['status'] ?? null) === 'ACTIVE'
                    && ($layout['xsd_source_hash'] ?? null) !== $xsdHash) {
                    $blockers[] = 'RTC_DPS_XSD_LAYOUT_BINDING_MISSING:'.($layout['version'] ?? 'UNKNOWN');
                }
            }
        }
        if (($manifest['status'] ?? null) !== 'PRODUCTION_APPROVED') {
            $blockers[] = 'BUNDLE_NOT_PRODUCTION_APPROVED';
        }
        if (($documents['rtc_code_catalog']['local_xml_projection']['known_source_conflict'] ?? null) !== null) {
            $blockers[] = 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT';
        }
        if (in_array($documents['rtc_code_catalog']['local_xml_projection']['contract_version'] ?? null, [DpsRestrictedSchemaSelection::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT, DpsLayoutPolicy127::CONTRACT, DpsForeignPolicy128::CONTRACT], true)) {
            $blockers[] = 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY';
        }
        if (DpsForeignPolicy128::required($documents['rtc_code_catalog'] ?? [])) {
            $blockers[] = 'DPS128_FOREIGN_PARTY_COMEXT_LOCAL_ONLY_NOT_DOCUMENT_OBLIGATION_APPROVAL';
        }
        foreach ($documents['rtc_transition_catalog']['simples_option_supersession_notices'] ?? [] as $notice) {
            if (is_array($notice) && ($notice['full_act_verified'] ?? false) !== true) {
                $blockers[] = 'RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED';
            }
        }

        $manifestHash = CanonicalJson::hash($manifest);
        $controlsHash = CanonicalJson::hash($controls);
        if (($manifest['production_controls']['sha256'] ?? null) !== $controlsHash) {
            $blockers[] = 'PRODUCTION_CONTROLS_NOT_BOUND_TO_BUNDLE';
        }
        if (! $this->hasVerifiedApproval($approvalPath, $trustedReviewerKeyPath, $manifestHash, $controlsHash)) {
            $blockers[] = 'LEGAL_APPROVAL_SIGNATURE_MISSING_OR_INVALID';
        }

        $developmentBlockers = array_values(array_filter($blockers, static fn (string $blocker): bool => $blocker === 'CONTROL_MATRIX_EMPTY'
            || str_starts_with($blocker, 'REQUIRED_CONTROL_MISSING:')
            || str_starts_with($blocker, 'OFFICIAL_SOURCE_MISSING_OR_CHANGED:')
            || $blocker === 'OFFICIAL_SOURCE_INVENTORY_EMPTY'
            || $blocker === 'RTC_DPS_XSD_CATALOG_NOT_BOUND_TO_OFFICIAL_SOURCE'
            || str_starts_with($blocker, 'RTC_DPS_XSD_LAYOUT_BINDING_MISSING:')
            || $blocker === 'PRODUCTION_CONTROLS_NOT_BOUND_TO_BUNDLE'
        ));
        $externalDependencies = $this->externalDependencies($documents);

        return [
            'ready' => $blockers === [],
            'eligible_for_local_validation' => $developmentBlockers === [],
            'development_blockers' => $developmentBlockers,
            'external_dependencies' => $externalDependencies,
            'known_parameters' => [
                'rates' => $documents['rtc_rate_catalog']['rates'] ?? [],
                'transition' => $documents['rtc_transition_catalog']['rows'] ?? [],
            ],
            'bundle_id' => $manifest['bundle_id'],
            'bundle_manifest_hash' => $manifestHash,
            'controls_version' => $controls['version'] ?? null,
            'controls_hash' => $controlsHash,
            'controls' => $controlResults,
            'official_sources_checked' => count($sourceResults),
            'official_source_hashes_valid' => count(array_filter($sourceResults, static fn (array $source): bool => $source['hash_valid'])),
            'blockers' => $blockers,
        ];
    }

    /** @param array<string, mixed> $documents
     * @return list<array{code: string, consequence: string}>
     */
    private function externalDependencies(array $documents): array
    {
        $rates = $documents['rtc_rate_catalog']['rates'] ?? [];
        $hasActiveRate = static function (string $tax, string $date) use ($rates): bool {
            foreach ($rates as $row) {
                if (($row['tax'] ?? null) === $tax && ($row['status'] ?? null) === 'ACTIVE'
                    && ($row['valid_from'] ?? '9999-12-31') <= $date
                    && ($row['valid_to'] ?? '0000-01-01') >= $date) {
                    return true;
                }
            }

            return false;
        };

        $dependencies = [];
        if (! $hasActiveRate('CBS', '2027-01-01')) {
            $dependencies[] = ['code' => 'CBS_RATE_2027_UNAVAILABLE', 'consequence' => 'ACTION_REQUIRED for calculations requiring this rate'];
        }
        if (! $hasActiveRate('IBS_STATE', '2029-01-01') || ! $hasActiveRate('IBS_MUNICIPAL', '2029-01-01')) {
            $dependencies[] = ['code' => 'IBS_JURISDICTIONAL_RATES_2029_UNAVAILABLE', 'consequence' => 'ACTION_REQUIRED for calculations requiring these rates'];
        }
        $dependencies[] = ['code' => 'ISS_BASE_RATE_2028_FUTURE', 'consequence' => '2029-2032 ISS needs the municipal rate effective on 2028-12-31'];
        $transition = $documents['rtc_transition_catalog'] ?? [];
        if (($transition['collection_qualification'] ?? false) === true && ($transition['split_deployments'] ?? []) === []) {
            $dependencies[] = ['code' => 'SPLIT_OPERATIONAL_DEPLOYMENT_UNPROVEN',
                'consequence' => 'Split collection stays ACTION_REQUIRED until official deployment is bound to a dated catalog row'];
        }
        foreach ($documents['rtc_code_catalog']['document_layouts'] ?? [] as $layout) {
            if (is_array($layout) && ($layout['status'] ?? null) === 'FUTURE_UNDEPLOYED') {
                $dependencies[] = ['code' => 'DOCUMENT_LAYOUT_DEPLOYMENT_UNPROVEN:'.($layout['version'] ?? 'UNKNOWN'),
                    'consequence' => 'Use only the layout proven effective for the target environment'];
            }
        }
        if (($documents['rtc_code_catalog']['local_xml_projection']['known_source_conflict'] ?? null) !== null) {
            $dependencies[] = ['code' => 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT',
                'consequence' => 'Ordinary numeric series remains in the local candidate; schema acceptance needs an authoritative corrected artifact or proven validation semantics'];
        }
        if (in_array($documents['rtc_code_catalog']['local_xml_projection']['contract_version'] ?? null, [DpsRestrictedSchemaSelection::CONTRACT, DpsForeignAcquirerCandidate::CONTRACT], true)) {
            $dependencies[] = ['code' => 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY',
                'consequence' => 'Observed restricted schema permits local structural validation only; production requires a separately proven environment contract'];
        }
        foreach ($transition['simples_option_supersession_notices'] ?? [] as $notice) {
            if (is_array($notice) && ($notice['full_act_verified'] ?? false) !== true) {
                $dependencies[] = ['code' => 'RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED',
                    'consequence' => 'Withdraw stale positive qualification; new announced filing and cancellation dates are not activated before full act verification'];
            }
        }

        return $dependencies;
    }

    private function hasVerifiedApproval(?string $approvalPath, ?string $trustedReviewerKeyPath, string $manifestHash, string $controlsHash): bool
    {
        if ($approvalPath === null || $trustedReviewerKeyPath === null || ! is_file($trustedReviewerKeyPath)) {
            return false;
        }
        $approval = $this->readJson($approvalPath);
        $payload = $approval['payload'] ?? null;
        $signature = $approval['signature_base64'] ?? null;
        if (! is_array($payload) || ! is_string($signature)
            || ($payload['decision'] ?? null) !== 'APPROVED'
            || ($payload['scope'] ?? null) !== 'INTEGRATED_ISS_4A_4B'
            || ($payload['bundle_manifest_hash'] ?? null) !== $manifestHash
            || ($payload['controls_hash'] ?? null) !== $controlsHash
            || ! is_string($payload['reviewer'] ?? null) || trim($payload['reviewer']) === ''
            || ! is_string($payload['approved_at'] ?? null) || trim($payload['approved_at']) === '') {
            return false;
        }
        $signatureBytes = base64_decode($signature, true);
        if ($signatureBytes === false) {
            return false;
        }
        $publicKey = file_get_contents($trustedReviewerKeyPath);
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify(CanonicalJson::encode($payload), $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Production evidence file not found: {$path}");
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Production evidence JSON is invalid: {$path}", previous: $exception);
        }
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException("Production evidence must be an object: {$path}");
        }

        return $decoded;
    }
}
