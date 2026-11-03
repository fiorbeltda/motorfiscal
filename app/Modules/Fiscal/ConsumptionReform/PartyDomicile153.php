<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Private material domicile profiles; the host validates the operation and supplies bound-record guards. */
trait PartyDomicile153
{
    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function partyDomicile153(array $input, array $scope, string $party, string $basis, DateTimeImmutable $known, string $on): array
    {
        $qualification = $input['party_domicile_qualification'];
        if (! is_array($qualification) || ($qualification['contract'] ?? null) !== self::PARTY_CONTRACT
            || ! in_array($qualification['method'] ?? null, ['REGISTERED_NATURAL_PERSON', 'UNREGISTERED_COMBINED_CRITERIA', 'UNREGISTERED_DECLARED_FALLBACK'], true)) {
            return $this->partyStop153('METHOD_CONTRACT_REQUIRED', ['party_domicile_qualification']);
        }
        $registry = $this->record($qualification['registration_evidence'] ?? null, $input, $known, $on);
        if ($registry === null || ($registry['role'] ?? null) !== 'PARTY_REGISTRATION_STATE'
            || ($registry['party_id'] ?? null) !== $party || ($registry['country'] ?? null) !== 'BR'
            || ! in_array($registry['party_kind'] ?? null, ['NATURAL_PERSON', 'LEGAL_ENTITY', 'UNINCORPORATED_ENTITY'], true)
            || ! is_bool($registry['regularly_registered'] ?? null) || ! $this->id($registry['document_id'] ?? null)
            || ! $this->covers($registry, $on)) {
            return $this->partyStop153('REGISTRATION_STATE_BINDING_REQUIRED', ['party_domicile_qualification.registration_evidence']);
        }
        if ($qualification['method'] === 'REGISTERED_NATURAL_PERSON') {
            return $this->naturalPerson153($input, $scope, $party, $basis, $qualification, $registry, $known, $on);
        }
        if ($registry['regularly_registered'] !== false) {
            return $this->partyStop153('REGISTERED_PARTY_CANNOT_USE_UNREGISTERED_CRITERIA', ['party_domicile_qualification.registration_evidence']);
        }
        $rows = $qualification['criteria'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return $this->partyStop153('CRITERIA_RECORDS_REQUIRED', ['party_domicile_qualification.criteria']);
        }
        $criteria = $rawIds = [];
        foreach ($rows as $envelope) {
            $id = is_array($envelope) && is_array($envelope['record'] ?? null) ? ($envelope['record']['criterion_id'] ?? null) : null;
            if (! $this->id($id) || isset($rawIds['ID:'.$id])) {
                return $this->partyStop153('CRITERION_ID_AMBIGUOUS', ['party_domicile_qualification.criteria']);
            }
            $rawIds['ID:'.$id] = $id;
            $criteria['ID:'.$id] = $this->criterion153($envelope, $input, $party, $known, $on);
        }
        if ($qualification['method'] === 'UNREGISTERED_DECLARED_FALLBACK') {
            return $this->declaredFallback153($input, $scope, $party, $basis, $qualification, $registry, $criteria, $known, $on);
        }
        $selection = $this->record($qualification['selection_evidence'] ?? null, $input, $known, $on);
        $ids = $selection['selected_criterion_ids'] ?? null;
        if ($selection === null || ($selection['role'] ?? null) !== 'SUPPLIER_DOMICILE_CRITERIA_SELECTION'
            || ($selection['party_id'] ?? null) !== $party || ($selection['supplier_selection_confirmed'] ?? null) !== true
            || ! $this->id($selection['document_id'] ?? null) || ! is_array($ids) || ! array_is_list($ids) || count($ids) < 2) {
            return $this->partyStop153('SUPPLIER_CRITERIA_SELECTION_REQUIRED', ['party_domicile_qualification.selection_evidence']);
        }
        $chosen = $kinds = [];
        $address = null;
        foreach ($ids as $id) {
            if (! $this->id($id) || isset($chosen['ID:'.$id]) || ! isset($criteria['ID:'.$id])) {
                return $this->partyStop153('SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE', ['party_domicile_qualification.selection_evidence']);
            }
            $criterion = $criteria['ID:'.$id];
            if ($address !== null && ! $this->compatibleAddresses153($address, $criterion['resolved_address'])) {
                return $this->partyStop153('SELECTED_ADDRESSES_CONFLICT', ['party_domicile_qualification.criteria']);
            }
            $address = $address === null ? $criterion['resolved_address'] : $this->mergeAddresses153($address, $criterion['resolved_address']);
            $chosen['ID:'.$id] = $criterion;
            $kinds[$criterion['kind']] = true;
        }
        if (count($kinds) < 2 || $address === null) {
            return $this->partyStop153('TWO_DISTINCT_CRITERIA_REQUIRED', ['party_domicile_qualification.selection_evidence']);
        }
        $notSelected = [];
        foreach ($rawIds as $key => $id) {
            if (! isset($chosen[$key])) {
                $criterion = $criteria[$key];
                $notSelected[] = ['criterion_id' => $id, 'status' => $criterion === null ? 'NOT_SELECTED_UNQUALIFIED'
                    : ($this->compatibleAddresses153($address, $criterion['resolved_address']) ? 'NOT_SELECTED_CONSISTENT' : 'NOT_SELECTED_CONFLICTING')];
            }
        }
        ksort($chosen, SORT_STRING);
        $ids = array_column(array_values($chosen), 'criterion_id');
        $kindList = array_keys($kinds);
        sort($kindList, SORT_STRING);
        usort($notSelected, static fn (array $a, array $b): int => strcmp($a['criterion_id'], $b['criterion_id']));

        return $this->partyResult153($input, $scope, $party, $basis, $address, 'SELECTED_NONCONFLICTING_CRITERIA',
            ['registration' => $registry, 'supplier_selection' => $selection, 'selected_criteria' => array_values($chosen)],
            ['selected_criterion_ids' => $ids, 'selected_criterion_kinds' => $kindList, 'nonselected_criteria' => $notSelected]);
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $qualification
     * @param  array<string, mixed>  $registry
     * @return array<string, mixed>
     */
    private function naturalPerson153(array $input, array $scope, string $party, string $basis, array $qualification, array $registry, DateTimeImmutable $known, string $on): array
    {
        if ($registry['regularly_registered'] !== true || $registry['party_kind'] !== 'NATURAL_PERSON'
            || ($registry['unique_registration'] ?? null) !== true) {
            return $this->partyStop153('REGULAR_NATURAL_PERSON_REGISTRATION_REQUIRED', ['party_domicile_qualification.registration_evidence']);
        }
        if (($registry['natural_person_subject_to_registration105'] ?? null) !== false) {
            return $this->partyStop153('NATURAL_PERSON_ART105_ESTABLISHMENT_QUALIFICATION_REQUIRED', ['party_domicile_qualification.registration_evidence.record.natural_person_subject_to_registration105']);
        }
        $registeredAddress = $this->address153($registry['domicile_address'] ?? null);
        $material = $this->record($qualification['material_evidence'] ?? null, $input, $known, $on);
        if ($registeredAddress === null || $material === null || ($material['role'] ?? null) !== 'PERSONAL_DOMICILE_MATERIAL_FACTS'
            || ($material['party_id'] ?? null) !== $party || ($material['complete'] ?? null) !== true
            || ! $this->id($material['document_id'] ?? null) || ! is_bool($material['no_permanent_home'] ?? null)) {
            return $this->partyStop153('PERSONAL_DOMICILE_MATERIAL_BINDING_REQUIRED', ['party_domicile_qualification.material_evidence']);
        }
        $homes = $material['permanent_homes'] ?? null;
        if (! is_array($homes) || ! array_is_list($homes)) {
            return $this->partyStop153('COMPLETE_PERMANENT_HOME_SET_REQUIRED', ['party_domicile_qualification.material_evidence.record.permanent_homes']);
        }
        $seen = $active = [];
        foreach ($homes as $home) {
            $address = is_array($home) ? $this->address153($home['address'] ?? null) : null;
            if (! is_array($home) || ! $this->id($home['id'] ?? null) || isset($seen['ID:'.$home['id']]) || $address === null
                || $this->date($home['valid_from'] ?? null) === null || ! array_key_exists('valid_until', $home)
                || ($home['valid_until'] !== null && ($this->date($home['valid_until']) === null || $home['valid_until'] < $home['valid_from']))) {
                return $this->partyStop153('PERMANENT_HOME_ID_ADDRESS_OR_INTERVAL_INVALID', ['party_domicile_qualification.material_evidence.record.permanent_homes']);
            }
            $seen['ID:'.$home['id']] = true;
            if ($this->covers($home, $on)) {
                $active[] = ['id' => $home['id'], 'address' => $address];
            }
        }
        if ($material['no_permanent_home'] !== ($active === [])) {
            return $this->partyStop153('PERMANENT_HOME_ABSENCE_CONTRADICTION', ['party_domicile_qualification.material_evidence']);
        }
        if (count($active) === 1) {
            $address = $active[0]['address'];
            $domicileBasis = 'PERMANENT_HOME';
        } else {
            $address = $this->address153($material['economic_relation_location'] ?? null);
            if ($address === null || ($material['economic_relevance_qualified'] ?? null) !== true
                || ! $this->id($material['economic_relevance_qualification_reference'] ?? null)) {
                return $this->partyStop153('MAIN_ECONOMIC_RELATIONS_QUALIFICATION_REQUIRED', ['party_domicile_qualification.material_evidence']);
            }
            $domicileBasis = 'MAIN_ECONOMIC_RELATIONS';
        }
        if (CanonicalJson::hash($address) !== CanonicalJson::hash($registeredAddress)) {
            return $this->partyStop153('REGISTERED_AND_MATERIAL_DOMICILE_CONFLICT', ['party_domicile_qualification.registration_evidence', 'party_domicile_qualification.material_evidence']);
        }

        return $this->partyResult153($input, $scope, $party, $basis, $address, $domicileBasis,
            ['registration' => $registry, 'personal_domicile' => $material], ['active_permanent_home_count' => count($active)]);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function criterion153(mixed $envelope, array $input, string $party, DateTimeImmutable $known, string $on): ?array
    {
        $record = $this->record($envelope, $input, $known, $on);
        if ($record === null || ! $this->id($record['criterion_id'] ?? null) || ($record['party_id'] ?? null) !== $party
            || ($record['role'] ?? null) !== 'DOMICILE_CRITERION' || ! $this->id($record['document_id'] ?? null)
            || ! in_array($record['kind'] ?? null, ['DECLARED_ADDRESS', 'COMMERCIAL_INFO', 'PAYMENT_ARRANGEMENT', 'IP_GEOLOCATION'], true)) {
            return null;
        }
        $address = $this->address153($record['address'] ?? null);
        if ($address === null || ($record['kind'] !== 'IP_GEOLOCATION' && $address['granularity'] === 'MUNICIPALITY')
            || ($record['kind'] === 'DECLARED_ADDRESS' && ($record['declared_to_supplier'] ?? null) !== true)
            || ($record['kind'] === 'COMMERCIAL_INFO' && ($record['collected_in_operation'] ?? null) !== true)
            || ($record['kind'] === 'PAYMENT_ARRANGEMENT' && ($party !== $input['acquirer_id'] || ! $this->id($record['payment_arrangement_id'] ?? null)))
            || ($record['kind'] === 'IP_GEOLOCATION' && (! in_array($record['device_location_method'] ?? null, ['IP', 'GEOLOCATION'], true)
                || ! $this->id($record['acquisition_device_evidence_id'] ?? null)))) {
            return null;
        }

        return [...$record, 'resolved_address' => $address];
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $qualification
     * @param  array<string, mixed>  $registry
     * @param  array<string, array<string, mixed>|null>  $criteria
     * @return array<string, mixed>
     */
    private function declaredFallback153(array $input, array $scope, string $party, string $basis, array $qualification, array $registry, array $criteria, DateTimeImmutable $known, string $on): array
    {
        $proof = $this->record($qualification['impossibility_evidence'] ?? null, $input, $known, $on);
        if ($proof === null || ($proof['role'] ?? null) !== 'CRITERIA_COMBINATION_IMPOSSIBILITY'
            || ($proof['party_id'] ?? null) !== $party || ($proof['criteria_coverage_complete'] ?? null) !== true
            || ! $this->id($proof['document_id'] ?? null) || ! is_array($proof['availability'] ?? null)
            || ! array_is_list($proof['availability']) || count($proof['availability']) !== 4) {
            return $this->partyStop153('COMPLETE_IMPOSSIBILITY_PROOF_REQUIRED', ['party_domicile_qualification.impossibility_evidence']);
        }
        $byKind = [];
        foreach ($criteria as $criterion) {
            if ($criterion === null) {
                return $this->partyStop153('UNQUALIFIED_CRITERION_PREVENTS_IMPOSSIBILITY_PROOF', ['party_domicile_qualification.criteria']);
            }
            $byKind[$criterion['kind']][] = $criterion['criterion_id'];
        }
        $kinds = [];
        foreach ($proof['availability'] as $row) {
            if (! is_array($row) || ! in_array($row['kind'] ?? null, ['DECLARED_ADDRESS', 'COMMERCIAL_INFO', 'PAYMENT_ARRANGEMENT', 'IP_GEOLOCATION'], true)
                || isset($kinds[$row['kind']]) || ! is_bool($row['available'] ?? null)
                || ! is_array($row['available_criterion_ids'] ?? null) || ! array_is_list($row['available_criterion_ids'])) {
                return $this->partyStop153('FOUR_CRITERIA_AVAILABILITY_REQUIRED', ['party_domicile_qualification.impossibility_evidence']);
            }
            $expected = $byKind[$row['kind']] ?? [];
            $ids = $row['available_criterion_ids'];
            foreach ($ids as $id) {
                if (! $this->id($id)) {
                    return $this->partyStop153('AVAILABILITY_CRITERION_ID_INVALID', ['party_domicile_qualification.impossibility_evidence']);
                }
            }
            sort($ids, SORT_STRING);
            sort($expected, SORT_STRING);
            if ($ids !== $expected || $row['available'] !== ($expected !== [])
                || (! $row['available'] && (! $this->id($row['unavailability_reason'] ?? null) || ! $this->id($row['unavailability_evidence_id'] ?? null)))) {
                return $this->partyStop153('AVAILABILITY_RECORD_COVERAGE_CONFLICT', ['party_domicile_qualification.impossibility_evidence']);
            }
            $kinds[$row['kind']] = true;
        }
        $available = array_values($criteria);
        for ($i = 0; $i < count($available); $i++) {
            for ($j = $i + 1; $j < count($available); $j++) {
                if ($available[$i]['kind'] !== $available[$j]['kind']
                    && $this->compatibleAddresses153($available[$i]['resolved_address'], $available[$j]['resolved_address'])) {
                    return $this->partyStop153('NONCONFLICTING_PAIR_EXISTS_FALLBACK_FORBIDDEN', ['party_domicile_qualification.criteria']);
                }
            }
        }
        $declared = array_values(array_filter($available, static fn (array $row): bool => $row['kind'] === 'DECLARED_ADDRESS'));
        if (count($declared) !== 1) {
            return $this->partyStop153('UNIQUE_DECLARED_ADDRESS_REQUIRED', ['party_domicile_qualification.criteria']);
        }

        return $this->partyResult153($input, $scope, $party, $basis, $declared[0]['resolved_address'], 'DECLARED_ADDRESS_PROVEN_IMPOSSIBILITY',
            ['registration' => $registry, 'impossibility' => $proof, 'available_criteria' => $available],
            ['selected_criterion_ids' => [$declared[0]['criterion_id']], 'selected_criterion_kinds' => ['DECLARED_ADDRESS'],
                'nonconflicting_pairs_available' => 0]);
    }

    /** Explicit address components only; no geocoding, case folding, whitespace repair or street inference.
     * @return array<string, mixed>|null
     */
    private function address153(mixed $address): ?array
    {
        if (! is_array($address) || ! is_bool($address['in_brazil'] ?? null)
            || array_diff(array_keys($address), ['in_brazil', 'uf', 'municipality_ibge', 'granularity', 'components', 'foreign_address_id']) !== []) {
            return null;
        }
        if ($address['in_brazil'] === false) {
            return ($address['granularity'] ?? null) === 'FOREIGN_ADDRESS' && $this->id($address['foreign_address_id'] ?? null)
                && ! array_key_exists('uf', $address) && ! array_key_exists('municipality_ibge', $address)
                && ($address['components'] ?? []) === []
                ? ['in_brazil' => false, 'granularity' => 'FOREIGN_ADDRESS', 'foreign_address_id' => $address['foreign_address_id']] : null;
        }
        $components = $address['components'] ?? [];
        if (array_key_exists('foreign_address_id', $address) || ! is_string($address['uf'] ?? null) || ! is_string($address['municipality_ibge'] ?? null)
            || ! in_array($address['granularity'] ?? null, ['ADDRESS', 'MUNICIPALITY'], true) || ! is_array($components)
            || array_diff(array_keys($components), ['street', 'number', 'unit', 'postal_code', 'district']) !== []
            || ($address['granularity'] === 'MUNICIPALITY' && $components !== [])
            || ($address['granularity'] === 'ADDRESS' && (! $this->id($components['street'] ?? null) || ! $this->id($components['number'] ?? null)))) {
            return null;
        }
        foreach ($components as $value) {
            if (! $this->id($value)) {
                return null;
            }
        }
        if (isset($components['postal_code']) && preg_match('/\A[0-9]{8}\z/', $components['postal_code']) !== 1) {
            return null;
        }
        ksort($components, SORT_STRING);

        return ['in_brazil' => true, 'uf' => $address['uf'], 'municipality_ibge' => $address['municipality_ibge'],
            'granularity' => $address['granularity'], 'components' => $components];
    }

    /** @param array<string, mixed> $left
     * @param  array<string, mixed>  $right
     */
    private function compatibleAddresses153(array $left, array $right): bool
    {
        if ($left['in_brazil'] !== $right['in_brazil']) {
            return false;
        }
        if ($left['in_brazil'] === false) {
            return $left['foreign_address_id'] === $right['foreign_address_id'];
        }
        if ($left['uf'] !== $right['uf'] || $left['municipality_ibge'] !== $right['municipality_ibge']) {
            return false;
        }
        foreach ($left['components'] as $key => $value) {
            if (array_key_exists($key, $right['components']) && $right['components'][$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function mergeAddresses153(array $left, array $right): array
    {
        if ($left['in_brazil'] === false) {
            return $left;
        }
        $components = [...$left['components'], ...$right['components']];
        ksort($components, SORT_STRING);

        return [...$left, 'granularity' => $left['granularity'] === 'ADDRESS' || $right['granularity'] === 'ADDRESS' ? 'ADDRESS' : 'MUNICIPALITY',
            'components' => $components];
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $scope
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $records
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function partyResult153(array $input, array $scope, string $party, string $basis, array $address, string $domicileBasis, array $records, array $extra): array
    {
        if ($address['in_brazil'] !== true) {
            return $this->partyStop153('SELECTED_DOMICILE_NOT_DOMESTIC', ['party_domicile_qualification']);
        }
        $reference = (new ObservedGeographicReference115)->resolve($this->geography,
            $address['municipality_ibge'], $address['uf'], new DateTimeImmutable((string) $input['knowledge_at']), $this->geographyPolicy);
        if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
            return $this->partyStop153($reference['reason'], ['party_domicile_qualification']);
        }
        $method = $input['party_domicile_qualification']['method'];
        $paragraph = match ($method) {
            'REGISTERED_NATURAL_PERSON' => 'I a', 'UNREGISTERED_COMBINED_CRITERIA' => 'II', default => 'III',
        };
        $report = ['contract' => self::PARTY_CONTRACT, 'status' => 'LOCAL_PARTY_DOMICILE_QUALIFIED',
            'reason' => 'DOMICILE153_MATERIAL_PARTY_DOMICILE_BOUND', 'source_article' => $scope['source_article'].'; §3 '.$paragraph,
            'qualification_method' => $method, 'domicile_basis' => $domicileBasis, 'destination_basis' => $basis,
            'selected_party_id' => $party, 'selected_establishment_id' => null, 'headquarters_selected' => false,
            'locality' => ['uf' => $address['uf'], 'municipality_ibge' => $address['municipality_ibge']],
            'domicile_address' => $address, 'geographic_reference' => $reference,
            'source' => ['url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
                'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
                'available_at' => ServiceLocality149::SOURCE_AVAILABLE_AT],
            'cbs_regulation_source' => ['url' => 'https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html',
                'sha256' => '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235',
                'source_article' => $method === 'REGISTERED_NATURAL_PERSON' ? 'Decreto12955 art.12 §3 I a e art.107 II' : 'Decreto12955 art.12 §3 '.$paragraph],
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'material_records' => ['scope' => $scope, ...$records], ...$extra, 'missing_fields' => [], ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function partyStop153(string $reason, array $missing): array
    {
        return ['contract' => self::PARTY_CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => 'DOMICILE153_'.$reason,
            'missing_fields' => $missing, 'locality' => null, ...$this->limits()];
    }
}
