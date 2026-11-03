<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Material locality from bound records; catalog classification and documentary projection are separate. */
final readonly class ServiceLocality149
{
    public const CONTRACT = 'RTC-SERVICE-MATERIAL-LOCALITY-2026.11.03-A149';

    public const MEASURED_CONTRACT = 'RTC-MEASURED-TERRITORIAL-LOCALITY-2026.11.03-A150';

    public const ENTITY_DOMICILE_CONTRACT = 'RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151';

    public const CENTRALIZED_DOMICILE_CONTRACT = 'RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152';

    public const PARTY_DOMICILE_CONTRACT = 'RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153';

    public const SOURCE_AVAILABLE_AT = '2026-11-03T11:18:57+00:00';

    private const RULES = [
        'IMMOVABLE_RELATED' => ['II', 'PROPERTY_LOCATION'],
        'PERSONAL_PRESENTIAL' => ['III', 'PERSONAL_PERFORMANCE'],
        'EVENT_ORGANIZATION' => ['IV', 'EVENT_LOCATION'],
        'MOVABLE_PHYSICAL_SERVICE' => ['V', 'PHYSICAL_PERFORMANCE'],
        'PORT_SERVICE' => ['V', 'PORT_LOCATION'],
        'PASSENGER_TRANSPORT' => ['VI', 'TRANSPORT_START'],
        'CARGO_TRANSPORT' => ['VII', 'DOCUMENTED_DELIVERY'],
        'FIXED_COMMUNICATION' => ['IX', 'TERMINAL_INSTALLATION'],
    ];

    /** @param array<string, mixed> $geography
     * @param  array<string, mixed>  $geographyPolicy
     */
    public function __construct(private array $geography, private array $geographyPolicy) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function qualify(array $input): array
    {
        $knownAt = $this->instant($input['knowledge_at'] ?? null);
        $on = $this->date($input['operation_date'] ?? null);
        if ($knownAt === null || $knownAt < new DateTimeImmutable(self::SOURCE_AVAILABLE_AT)) {
            return $this->stop('LOCALITY149_SOURCE_KNOWLEDGE_UNPROVEN', ['knowledge_at']);
        }
        if ($on === null || $on < '2026-01-01' || $on > '2033-12-31'
            || $on > $knownAt->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d')) {
            return $this->stop('LOCALITY149_OPERATION_DATE_UNPROVEN', ['operation_date']);
        }
        foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
            if (! $this->id($input[$key] ?? null)) {
                return $this->stop('LOCALITY149_OPERATION_IDENTITY_REQUIRED', [$key]);
            }
        }
        $category = $input['service_category'] ?? null;
        if (array_key_exists('territorial_measurements', $input)) {
            if (! in_array($category, ['IMMOVABLE_RELATED', 'ROAD_EXPLOITATION'], true)) {
                return $this->measuredStop('LOCALITY150_CATEGORY_MEASUREMENT_CONFLICT', ['service_category']);
            }

            return $this->measured($input, $knownAt, $on);
        }
        if ($category === 'ROAD_EXPLOITATION') {
            return $this->stop('LOCALITY149_ROAD_EXTENT_ALLOCATION_REQUIRED', ['territorial_lengths']);
        }
        if (! is_string($category) || ! isset(self::RULES[$category])) {
            return $this->stop('LOCALITY149_SERVICE_SCOPE_UNPROVEN', ['service_category']);
        }
        [$inciso, $role] = self::RULES[$category];
        $article = 'LC214 art.11 '.$inciso;
        $scope = $this->record($input['scope_evidence'] ?? null, $input, $on, $knownAt);
        if ($scope === null || ($scope['service_category'] ?? null) !== $category
            || ($scope['source_article'] ?? null) !== $article || ($scope['scope_confirmed'] ?? null) !== true) {
            return $this->stop('LOCALITY149_SCOPE_RECORD_UNPROVEN', ['scope_evidence']);
        }
        if ($category === 'PERSONAL_PRESENTIAL') {
            if (! in_array($scope['delivery_mode'] ?? null, ['PRESENTIAL', 'REMOTE', 'DIGITAL', 'MIXED'], true)) {
                return $this->stop('LOCALITY149_DELIVERY_MODE_UNPROVEN', ['scope_evidence.record.delivery_mode']);
            }
            if (! is_bool($scope['partially_remote'] ?? null)) {
                return $this->stop('LOCALITY149_REMOTE_EXTENT_UNPROVEN', ['scope_evidence.record.partially_remote']);
            }
            if (($scope['delivery_mode'] ?? null) !== 'PRESENTIAL' || $scope['partially_remote'] === true) {
                if (array_key_exists('entity_domicile_qualification', $input)) {
                    return $this->entityDomicile($input, $scope);
                }

                return $this->stop('LOCALITY149_RESIDUAL_DOMICILE_REQUIRED_ART11_PAR5', ['domicile_qualification']);
            }
        }
        if ($category === 'FIXED_COMMUNICATION') {
            if (! is_bool($scope['centralized_purchase'] ?? null)) {
                return $this->stop('LOCALITY149_CENTRALIZATION_STATE_UNPROVEN', ['scope_evidence.record.centralized_purchase']);
            }
            if ($scope['centralized_purchase'] === false && array_key_exists('centralized_purchase_qualification', $input)) {
                return $this->stop('LOCALITY152_CENTRALIZATION_SCOPE_BINDING_CONFLICT', ['centralized_purchase_qualification', 'scope_evidence']);
            }
            if ($scope['centralized_purchase'] === true) {
                if (array_key_exists('centralized_purchase_qualification', $input)) {
                    return $this->entityDomicile($input, $scope, true);
                }

                return $this->stop('LOCALITY149_CENTRALIZED_PURCHASE_QUALIFICATION_REQUIRED_ART11_PAR4', ['centralized_purchase_qualification']);
            }
        }
        $locations = $input['locations'] ?? null;
        if (! is_array($locations) || ! array_is_list($locations) || $locations === []) {
            return $this->stop('LOCALITY149_LOCATION_RECORD_REQUIRED', ['locations']);
        }
        if (count($locations) !== 1) {
            return $this->stop($category === 'IMMOVABLE_RELATED' ? 'LOCALITY149_PROPERTY_AREA_QUALIFICATION_REQUIRED'
                : 'LOCALITY149_MULTIPLE_LOCALITIES_REQUIRE_SEPARATE_QUALIFICATION', ['locations']);
        }
        $location = $locations[0];
        if (! is_array($location) || ! $this->id($location['id'] ?? null)
            || ! is_string($location['municipality_ibge'] ?? null) || ! is_string($location['uf'] ?? null)) {
            return $this->stop('LOCALITY149_LOCATION_IDENTITY_UNPROVEN', ['locations']);
        }
        $evidence = $this->record($location['evidence'] ?? null, $input, $on, $knownAt);
        if ($evidence === null || ($evidence['location_id'] ?? null) !== $location['id']
            || ($evidence['role'] ?? null) !== $role
            || ($evidence['municipality_ibge'] ?? null) !== $location['municipality_ibge']
            || ($evidence['uf'] ?? null) !== $location['uf'] || ! $this->id($evidence['document_id'] ?? null)) {
            return $this->stop('LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN', ['locations.0.evidence']);
        }
        if ($category === 'CARGO_TRANSPORT'
            && (! $this->id($input['transport_document_id'] ?? null) || $evidence['document_id'] !== $input['transport_document_id'])) {
            return $this->stop('LOCALITY149_CARGO_DOCUMENT_BINDING_UNPROVEN', ['transport_document_id']);
        }
        $reference = (new ObservedGeographicReference115)->resolve($this->geography,
            $location['municipality_ibge'], $location['uf'], $knownAt, $this->geographyPolicy);
        if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
            return $this->stop('LOCALITY149_'.$reference['reason'], ['locations.0.municipality_ibge', 'locations.0.uf']);
        }
        $result = ['contract' => self::CONTRACT, 'status' => 'LOCAL_SERVICE_LOCATION_QUALIFIED',
            'reason' => 'LOCALITY149_SPECIAL_SERVICE_MATERIAL_LOCATION_BOUND',
            'source_article' => $article, 'location_role' => $role,
            'source' => ['url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
                'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
                'available_at' => self::SOURCE_AVAILABLE_AT],
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'locality' => ['uf' => $location['uf'], 'municipality_ibge' => $location['municipality_ibge']],
            'geographic_reference' => $reference, 'material_records' => ['scope' => $scope, 'location' => $evidence],
            'missing_fields' => [], ...$this->limits()];

        return [...$result, 'report_hash' => CanonicalJson::hash($result)];
    }

    /** @param array<string, mixed> $result */
    public function replay(array $result): bool
    {
        if (! is_array($result['operation_input'] ?? null) || ! is_string($result['report_hash'] ?? null)) {
            return false;
        }
        $saved = $result;
        unset($saved['report_hash']);

        return hash_equals($result['report_hash'], CanonicalJson::hash($saved))
            && CanonicalJson::hash($result) === CanonicalJson::hash($this->qualify($result['operation_input']));
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function entityDomicile(array $input, array $scope, bool $fixedCentralized = false): array
    {
        $field = $fixedCentralized ? 'centralized_purchase_qualification' : 'entity_domicile_qualification';
        $prefix = $fixedCentralized ? 'LOCALITY152' : 'LOCALITY151';
        $qualification = $input[$field];
        $qualifier = new EntityDomicile151($this->geography, $this->geographyPolicy);
        if (! is_array($qualification) || ! $qualifier->replay($qualification)
            || ! in_array($qualification['status'] ?? null, ['LOCAL_ENTITY_DOMICILE_QUALIFIED', 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED', 'LOCAL_PARTY_DOMICILE_QUALIFIED'], true)
            || ($fixedCentralized && $qualification['status'] !== 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED')
            || ($qualification['operation_input']['supply_category'] ?? null) !== ($fixedCentralized ? 'FIXED_COMMUNICATION' : 'PERSONAL_REMOTE')) {
            return $this->stop($prefix.'_ENTITY_DOMICILE_REPORT_UNPROVEN', [$field]);
        }
        $centralized = $qualification['status'] === 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED';
        $personalParty = $qualification['status'] === 'LOCAL_PARTY_DOMICILE_QUALIFIED';
        if (($centralized && ($scope['centralized_purchase'] ?? null) !== true)
            || (! $centralized && ($scope['centralized_purchase'] ?? null) === true)) {
            return $this->stop('LOCALITY152_CENTRALIZATION_SCOPE_BINDING_CONFLICT', [$field, 'scope_evidence']);
        }
        foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id', 'operation_date', 'knowledge_at'] as $key) {
            if ($qualification['operation_input'][$key] !== $input[$key]) {
                return $this->stop($prefix.'_ENTITY_DOMICILE_OPERATION_BINDING_CONFLICT', [$field.'.operation_input.'.$key]);
            }
        }
        $report = [...$qualification, 'contract' => $centralized ? self::CENTRALIZED_DOMICILE_CONTRACT
            : ($personalParty ? self::PARTY_DOMICILE_CONTRACT : self::ENTITY_DOMICILE_CONTRACT),
            'status' => $centralized ? 'LOCAL_SERVICE_CENTRALIZED_DOMICILE_QUALIFIED'
                : ($personalParty ? 'LOCAL_SERVICE_PARTY_DOMICILE_QUALIFIED' : 'LOCAL_SERVICE_ENTITY_DOMICILE_QUALIFIED'),
            'reason' => $centralized ? 'LOCALITY152_SERVICE_CENTRALIZED_ACQUIRER_HEADQUARTERS_BOUND'
                : ($personalParty ? 'LOCALITY153_PERSONAL_REMOTE_PARTY_DOMICILE_BOUND' : 'LOCALITY151_PERSONAL_REMOTE_REGISTERED_ENTITY_ESTABLISHMENT_BOUND'),
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'service_scope_record' => $scope, 'entity_domicile_report' => $qualification];
        unset($report['report_hash']);

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** Complete measured territory set; areas select one municipality, road lengths retain exact shares.
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function measured(array $input, DateTimeImmutable $knownAt, string $on): array
    {
        $property = $input['service_category'] === 'IMMOVABLE_RELATED';
        $baseArticle = $property ? 'LC214 art.11 II' : 'LC214 art.11 VIII';
        $article = $property ? 'LC214 art.11 II e §2' : $baseArticle;
        $role = $property ? 'PROPERTY_AREA' : 'ROAD_EXTENT';
        $unit = $property ? 'M2' : 'M';
        $measurement = $input['territorial_measurements'];
        if (! is_array($measurement) || ($measurement['contract'] ?? null) !== self::MEASURED_CONTRACT
            || ! $this->id($measurement['asset_id'] ?? null) || ($measurement['unit'] ?? null) !== $unit
            || ! $this->measure($measurement['total'] ?? null)) {
            return $this->measuredStop('LOCALITY150_MEASUREMENT_CONTRACT_UNIT_OR_TOTAL_UNPROVEN', ['territorial_measurements']);
        }
        if (($measurement['complete'] ?? null) !== true) {
            return $this->measuredStop('LOCALITY150_COMPLETE_TERRITORY_SET_REQUIRED', ['territorial_measurements.complete']);
        }
        $scope = $this->record($input['scope_evidence'] ?? null, $input, $on, $knownAt);
        $coverage = $this->record($measurement['evidence'] ?? null, $input, $on, $knownAt);
        if ($scope === null || ($scope['service_category'] ?? null) !== $input['service_category']
            || ($scope['source_article'] ?? null) !== $baseArticle || ($scope['scope_confirmed'] ?? null) !== true
            || ($scope['asset_id'] ?? null) !== $measurement['asset_id']) {
            return $this->measuredStop('LOCALITY150_ASSET_SCOPE_RECORD_UNPROVEN', ['scope_evidence']);
        }
        if (! $property && ($scope['paid_exploitation'] ?? null) !== true) {
            return $this->measuredStop('LOCALITY150_PAID_ROAD_EXPLOITATION_UNPROVEN', ['scope_evidence.record.paid_exploitation']);
        }
        if ($coverage === null || ($coverage['asset_id'] ?? null) !== $measurement['asset_id']
            || ($coverage['role'] ?? null) !== 'TERRITORIAL_MEASUREMENT_COVERAGE'
            || ($coverage['unit'] ?? null) !== $unit || ($coverage['total'] ?? null) !== $measurement['total']
            || ($coverage['complete'] ?? null) !== true || ! $this->id($coverage['document_id'] ?? null)) {
            return $this->measuredStop('LOCALITY150_COVERAGE_RECORD_UNPROVEN', ['territorial_measurements.evidence']);
        }
        $locations = $input['locations'] ?? null;
        $expected = $coverage['location_ids'] ?? null;
        if (! is_array($locations) || ! array_is_list($locations) || $locations === []
            || ! is_array($expected) || ! array_is_list($expected) || count($expected) !== count($locations)) {
            return $this->measuredStop('LOCALITY150_LOCATION_COVERAGE_REQUIRED', ['locations', 'territorial_measurements.evidence.record.location_ids']);
        }
        $expectedSet = [];
        foreach ($expected as $id) {
            if (! $this->id($id) || isset($expectedSet['ID:'.$id])) {
                return $this->measuredStop('LOCALITY150_EXPECTED_LOCATION_IDS_INVALID', ['territorial_measurements.evidence.record.location_ids']);
            }
            $expectedSet['ID:'.$id] = true;
        }
        $seen = $groups = $references = $material = [];
        $total = DecimalValue::fromString('0');
        foreach ($locations as $location) {
            if (! is_array($location) || ! $this->id($location['id'] ?? null)
                || ! isset($expectedSet['ID:'.$location['id']]) || isset($seen['ID:'.$location['id']])
                || ! $this->measure($location['measure'] ?? null)
                || ! is_string($location['uf'] ?? null) || ! is_string($location['municipality_ibge'] ?? null)) {
                return $this->measuredStop('LOCALITY150_LOCATION_ID_MEASURE_OR_COVERAGE_INVALID', ['locations']);
            }
            $seen['ID:'.$location['id']] = true;
            $record = $this->record($location['evidence'] ?? null, $input, $on, $knownAt);
            if ($record === null || ($record['asset_id'] ?? null) !== $measurement['asset_id']
                || ($record['location_id'] ?? null) !== $location['id'] || ($record['role'] ?? null) !== $role
                || ($record['unit'] ?? null) !== $unit || ($record['measure'] ?? null) !== $location['measure']
                || ($record['uf'] ?? null) !== $location['uf'] || ($record['municipality_ibge'] ?? null) !== $location['municipality_ibge']
                || ! $this->id($record['document_id'] ?? null)) {
                return $this->measuredStop('LOCALITY150_MEASURED_LOCATION_BINDING_UNPROVEN', ['locations']);
            }
            $reference = (new ObservedGeographicReference115)->resolve($this->geography,
                $location['municipality_ibge'], $location['uf'], $knownAt, $this->geographyPolicy);
            if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
                return $this->measuredStop('LOCALITY150_'.$reference['reason'], ['locations']);
            }
            $key = $location['uf'].':'.$location['municipality_ibge'];
            $groups[$key] ??= ['uf' => $location['uf'], 'municipality_ibge' => $location['municipality_ibge'],
                'measure' => '0', 'location_ids' => []];
            $groups[$key]['measure'] = DecimalValue::fromString($groups[$key]['measure'])
                ->plus(DecimalValue::fromString($location['measure']))->value();
            $groups[$key]['location_ids'][] = $location['id'];
            $references[$key] = $reference;
            $material[$location['id']] = $record;
            $total = $total->plus(DecimalValue::fromString($location['measure']));
        }
        if (count($seen) !== count($expectedSet) || $total->compareTo(DecimalValue::fromString($measurement['total'])) !== 0) {
            return $this->measuredStop('LOCALITY150_TOTAL_MEASURE_DOES_NOT_RECONCILE', ['territorial_measurements.total', 'locations']);
        }
        ksort($groups, SORT_STRING);
        ksort($references, SORT_STRING);
        ksort($material, SORT_STRING);
        foreach ($groups as &$group) {
            sort($group['location_ids'], SORT_STRING);
        }
        unset($group);
        $locality = null;
        $allocations = $states = [];
        if ($property) {
            $largest = [];
            $maximum = DecimalValue::fromString('0');
            foreach ($groups as $key => $group) {
                $comparison = DecimalValue::fromString($group['measure'])->compareTo($maximum);
                if ($comparison > 0) {
                    $maximum = DecimalValue::fromString($group['measure']);
                    $largest = [$key];
                } elseif ($comparison === 0) {
                    $largest[] = $key;
                }
            }
            if (count($largest) !== 1) {
                return [...$this->measuredStop('LOCALITY150_PROPERTY_LARGEST_AREA_TIE', ['property_largest_area_qualification']),
                    'measured_territories' => array_values($groups)];
            }
            $winner = $groups[$largest[0]];
            $locality = ['uf' => $winner['uf'], 'municipality_ibge' => $winner['municipality_ibge']];
            $allocations[] = [...$winner, 'share' => ['numerator' => '1', 'denominator' => '1']];
            $states[] = ['uf' => $winner['uf'], 'share' => ['numerator' => '1', 'denominator' => '1']];
        } else {
            foreach ($groups as $group) {
                $allocations[] = [...$group, 'share' => ['numerator' => $group['measure'], 'denominator' => $total->value()]];
                $uf = $group['uf'];
                $states[$uf] ??= ['uf' => $uf, 'measure' => '0'];
                $states[$uf]['measure'] = DecimalValue::fromString($states[$uf]['measure'])
                    ->plus(DecimalValue::fromString($group['measure']))->value();
            }
            ksort($states, SORT_STRING);
            foreach ($states as &$state) {
                $state['share'] = ['numerator' => $state['measure'], 'denominator' => $total->value()];
            }
            unset($state);
        }
        usort($locations, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        $input['locations'] = $locations;
        $report = ['contract' => self::MEASURED_CONTRACT, 'status' => 'LOCAL_TERRITORIAL_LOCATION_QUALIFIED',
            'reason' => $property ? 'LOCALITY150_PROPERTY_UNIQUE_LARGEST_MUNICIPAL_AREA' : 'LOCALITY150_ROAD_EXACT_TERRITORIAL_LENGTH_SHARES',
            'source_article' => $article, 'method' => $property ? 'LARGEST_MUNICIPAL_AREA_SINGLE_DESTINATION' : 'EXACT_ROAD_LENGTH_PROPORTIONS',
            'source' => ['url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
                'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
                'available_at' => self::SOURCE_AVAILABLE_AT],
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'asset_id' => $measurement['asset_id'], 'unit' => $unit, 'total_measure' => $total->value(),
            'locality' => $locality, 'measured_territories' => array_values($groups),
            'jurisdiction_allocations' => $allocations, 'state_allocations' => array_values($states),
            'allocation_semantics' => 'SAME_SUPPLY_JURISDICTION_WEIGHTS_NOT_ADDITIONAL_SUPPLIES',
            'geographic_references' => array_values($references),
            'material_records' => ['scope' => $scope, 'coverage' => $coverage, 'locations' => array_values($material)],
            'missing_fields' => [], ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    private function measure(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/', $value) === 1
            && DecimalValue::fromString($value)->compareTo(DecimalValue::fromString('0')) > 0;
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function measuredStop(string $reason, array $missing): array
    {
        return [...$this->stop($reason, $missing), 'contract' => self::MEASURED_CONTRACT];
    }

    /** Validate integrity and operation/date binding of a supplied material record, not its external authenticity.
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function record(mixed $envelope, array $input, string $on, DateTimeImmutable $knownAt): ?array
    {
        if (! is_array($envelope) || ! $this->id($envelope['source_id'] ?? null)
            || ! is_array($envelope['record'] ?? null) || ! is_string($envelope['record_hash'] ?? null)
            || ! hash_equals($envelope['record_hash'], CanonicalJson::hash($envelope['record']))) {
            return null;
        }
        $record = $envelope['record'];
        $observed = $this->instant($record['observed_at'] ?? null);
        if (($record['operation_id'] ?? null) !== $input['operation_id']
            || ($record['supply_id'] ?? null) !== $input['supply_id']
            || ($record['supplier_id'] ?? null) !== $input['supplier_id']
            || ($record['recipient_id'] ?? null) !== $input['recipient_id']
            || ($record['applicable_on'] ?? null) !== $on || $observed === null || $observed > $knownAt
            || $observed->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') < $on) {
            return null;
        }

        return $record;
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value
            && preg_match('//u', $value) === 1 && preg_match('/[\x00-\x1f\x7f]|\A\p{Z}|\p{Z}\z/u', $value) === 0;
    }

    private function date(mixed $value): ?string
    {
        try {
            return is_string($value) ? LocalDate::fromString($value)->value() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)
            || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-](?:[01][0-9]|2[0-3]):[0-5][0-9])\z/', $value) !== 1
            || str_ends_with($value, '-00:00')) {
            return null;
        }
        try {
            $instant = new DateTimeImmutable($value);

            return DateTimeImmutable::getLastErrors() === false ? $instant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, bool|string> */
    private function limits(): array
    {
        return ['classification_catalog_integration' => 'PENDING_AGENT_B', 'cIndOp_selected' => false,
            'tax_rate_selected' => false, 'credit_right_determined' => false, 'source_record_authenticity_proven' => false,
            'historical_juridical_validity_proven' => false, 'document_obligation_complete' => false,
            'posting_authorized' => false, 'production_ready' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing): array
    {
        return ['contract' => self::CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => $reason,
            'missing_fields' => $missing, 'locality' => null, ...$this->limits()];
    }
}
