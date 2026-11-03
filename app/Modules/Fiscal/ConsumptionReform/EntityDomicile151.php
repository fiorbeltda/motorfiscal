<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Bound registry and actual supply evidence for art.11 X / §3 I b; no implicit headquarters. */
final readonly class EntityDomicile151
{
    use PartyDomicile153;

    public const CONTRACT = 'RTC-REGISTERED-ENTITY-DOMICILE-2026.11.03-A151';

    public const CENTRALIZED_CONTRACT = 'RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152';

    public const PARTY_CONTRACT = 'RTC-MATERIAL-PARTY-DOMICILE-2026.11.03-A153';

    /** @param array<string, mixed> $geography
     * @param  array<string, mixed>  $geographyPolicy
     */
    public function __construct(private array $geography, private array $geographyPolicy) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function qualify(array $input): array
    {
        $known = $this->instant($input['knowledge_at'] ?? null);
        $on = $this->date($input['operation_date'] ?? null);
        if ($known === null || $known < new DateTimeImmutable(ServiceLocality149::SOURCE_AVAILABLE_AT)) {
            return $this->stop('SOURCE_KNOWLEDGE_UNPROVEN', ['knowledge_at']);
        }
        if ($on === null || $on < '2026-01-01' || $on > '2033-12-31'
            || $on > $known->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d')) {
            return $this->stop('OPERATION_DATE_UNPROVEN', ['operation_date']);
        }
        foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
            if (! $this->id($input[$key] ?? null)) {
                return $this->stop('OPERATION_IDENTITY_REQUIRED', [$key]);
            }
        }
        if (! is_bool($input['onerous'] ?? null) || ! is_bool($input['recipient_domiciled_in_brazil'] ?? null)) {
            return $this->stop('PARTY_RESIDENCE_OR_CONSIDERATION_UNPROVEN', ['onerous', 'recipient_domiciled_in_brazil']);
        }
        if ($input['onerous'] === true) {
            if (! $this->id($input['acquirer_id'] ?? null) || ! is_bool($input['acquirer_domiciled_in_brazil'] ?? null)) {
                return $this->stop('ACQUIRER_IDENTITY_OR_RESIDENCE_UNPROVEN', ['acquirer_id', 'acquirer_domiciled_in_brazil']);
            }
        } elseif (! array_key_exists('acquirer_id', $input) || ! array_key_exists('acquirer_domiciled_in_brazil', $input)
            || $input['acquirer_id'] !== null || $input['acquirer_domiciled_in_brazil'] !== null) {
            return $this->stop('NONONEROUS_ACQUIRER_CONTRADICTION', ['acquirer_id', 'acquirer_domiciled_in_brazil']);
        }
        if ($input['acquirer_id'] === $input['recipient_id']
            && $input['acquirer_domiciled_in_brazil'] !== $input['recipient_domiciled_in_brazil']) {
            return $this->stop('SAME_PARTY_RESIDENCE_CONTRADICTION', ['acquirer_domiciled_in_brazil', 'recipient_domiciled_in_brazil']);
        }
        $acquirer = $input['onerous'] === true && $input['acquirer_domiciled_in_brazil'] === true;
        if (! $acquirer && $input['recipient_domiciled_in_brazil'] !== true) {
            return $this->stop('NO_DOMESTIC_PARTY_UNDER_ART11_X', ['recipient_domiciled_in_brazil']);
        }
        $party = $acquirer ? $input['acquirer_id'] : $input['recipient_id'];
        $basis = $acquirer ? 'ACQUIRER_DOMICILE' : 'RECIPIENT_DOMICILE';
        $scope = $this->record($input['scope_evidence'] ?? null, $input, $known, $on);
        $category = $input['supply_category'] ?? null;
        if (array_key_exists('centralized_purchase_evidence', $input)) {
            if (array_key_exists('party_domicile_qualification', $input)) {
                return $this->partyStop153('INCOMPATIBLE_DOMICILE_PROFILES', ['party_domicile_qualification', 'centralized_purchase_evidence']);
            }

            return $this->centralized($input, $scope, $known, $on);
        }
        if ($scope === null || ! in_array($category, ['RESIDUAL_SERVICE', 'PERSONAL_REMOTE'], true)
            || ($scope['supply_category'] ?? null) !== $category || ($scope['scope_confirmed'] ?? null) !== true) {
            return $this->stop('RESIDUAL_SCOPE_RECORD_UNPROVEN', ['scope_evidence']);
        }
        $article = $category === 'PERSONAL_REMOTE' ? 'LC214 art.11 X e §5' : 'LC214 art.11 X';
        if (($scope['source_article'] ?? null) !== $article
            || ($category === 'RESIDUAL_SERVICE' && ($scope['outside_inciso_I_to_IX'] ?? null) !== true)
            || ($category === 'PERSONAL_REMOTE' && (($scope['normally_personal_physical'] ?? null) !== true
                || ($scope['remotely_performed_at_least_partly'] ?? null) !== true))) {
            return $this->stop('RESIDUAL_SCOPE_EXCEPTION_UNPROVEN', ['scope_evidence']);
        }
        if (($scope['centralized_purchase'] ?? null) !== false) {
            return $this->stop('CENTRALIZED_PURCHASE_SEPARATE_QUALIFICATION_REQUIRED', ['scope_evidence.record.centralized_purchase']);
        }
        if (array_key_exists('party_domicile_qualification', $input)) {
            if (array_key_exists('registry_evidence', $input) || array_key_exists('supply_establishment_evidence', $input)) {
                return $this->partyStop153('INCOMPATIBLE_DOMICILE_PROFILES', ['party_domicile_qualification', 'registry_evidence', 'supply_establishment_evidence']);
            }

            return $this->partyDomicile153($input, $scope, (string) $party, $basis, $known, $on);
        }
        $registry = $this->record($input['registry_evidence'] ?? null, $input, $known, $on);
        if ($registry === null || ($registry['role'] ?? null) !== 'UNIQUE_REGISTRATION_ESTABLISHMENTS'
            || ($registry['entity_id'] ?? null) !== $party || ($registry['country'] ?? null) !== 'BR'
            || ! $this->id($registry['document_id'] ?? null)) {
            return $this->stop('SELECTED_PARTY_REGISTRY_BINDING_UNPROVEN', ['registry_evidence']);
        }
        if (($registry['regularly_registered'] ?? null) !== true || ($registry['unique_registration'] ?? null) !== true) {
            return $this->stop('UNREGISTERED_DOMICILE_SEPARATE_QUALIFICATION_REQUIRED', ['registry_evidence.record.regularly_registered']);
        }
        if (! in_array($registry['party_kind'] ?? null, ['LEGAL_ENTITY', 'UNINCORPORATED_ENTITY'], true)) {
            return $this->stop('NATURAL_PERSON_SEPARATE_QUALIFICATION_REQUIRED', ['registry_evidence.record.party_kind']);
        }
        $delivery = $this->record($input['supply_establishment_evidence'] ?? null, $input, $known, $on);
        if ($delivery === null || ($delivery['role'] ?? null) !== 'ACTUAL_SUPPLY_ESTABLISHMENTS'
            || ($delivery['entity_id'] ?? null) !== $party || ($delivery['complete'] ?? null) !== true
            || ! $this->id($delivery['document_id'] ?? null)) {
            return $this->stop('ACTUAL_SUPPLY_ESTABLISHMENT_BINDING_UNPROVEN', ['supply_establishment_evidence']);
        }
        $supplied = $delivery['supplied_establishment_ids'] ?? null;
        if (! is_array($supplied) || ! array_is_list($supplied) || count($supplied) !== 1 || ! $this->id($supplied[0] ?? null)) {
            return $this->stop('SINGLE_SUPPLIED_ESTABLISHMENT_REQUIRED', ['supply_establishment_evidence.record.supplied_establishment_ids']);
        }
        $entries = $registry['establishments'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || $entries === []
            || ($registry['complete_for_supplied_establishments'] ?? null) !== true) {
            return $this->stop('REGISTRY_ESTABLISHMENT_COVERAGE_REQUIRED', ['registry_evidence.record.establishments']);
        }
        $seen = [];
        $selected = null;
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! $this->id($entry['id'] ?? null) || isset($seen['ID:'.$entry['id']])
                || ($entry['entity_id'] ?? null) !== $party || ! is_string($entry['uf'] ?? null)
                || ! is_string($entry['municipality_ibge'] ?? null) || ! is_bool($entry['headquarters'] ?? null)
                || $this->date($entry['valid_from'] ?? null) === null || ! array_key_exists('valid_until', $entry)
                || ($entry['valid_until'] !== null && ($this->date($entry['valid_until']) === null || $entry['valid_until'] < $entry['valid_from']))) {
                return $this->stop('REGISTRY_ESTABLISHMENT_INVALID_OR_DUPLICATE', ['registry_evidence.record.establishments']);
            }
            $seen['ID:'.$entry['id']] = true;
            if ($entry['id'] === $supplied[0]) {
                $selected = $entry;
            }
        }
        if ($selected === null) {
            return $this->stop('SUPPLIED_ESTABLISHMENT_ABSENT_FROM_REGISTRY', ['registry_evidence.record.establishments']);
        }
        // Registry interval is explicit and inclusive; never substitute an expired branch with the headquarters.
        if ($selected['valid_from'] > $on || ($selected['valid_until'] !== null && $selected['valid_until'] < $on)) {
            return $this->stop('SUPPLIED_ESTABLISHMENT_OUTSIDE_RECORDED_VALIDITY', ['registry_evidence.record.establishments']);
        }
        $reference = (new ObservedGeographicReference115)->resolve($this->geography,
            $selected['municipality_ibge'], $selected['uf'], $known, $this->geographyPolicy);
        if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
            return $this->stop($reference['reason'], ['registry_evidence.record.establishments']);
        }
        $report = ['contract' => self::CONTRACT, 'status' => 'LOCAL_ENTITY_DOMICILE_QUALIFIED',
            'reason' => 'DOMICILE151_REGISTERED_ACTUAL_SUPPLY_ESTABLISHMENT_BOUND',
            'source_article' => $article.'; §3 I b', 'destination_basis' => $basis,
            'selected_party_id' => $party, 'selected_establishment_id' => $selected['id'],
            'headquarters_selected' => $selected['headquarters'],
            'locality' => ['uf' => $selected['uf'], 'municipality_ibge' => $selected['municipality_ibge']],
            'registry_interval_semantics' => 'EXPLICIT_INCLUSIVE_DATES',
            'geographic_reference' => $reference,
            'source' => ['url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
                'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
                'available_at' => ServiceLocality149::SOURCE_AVAILABLE_AT],
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'material_records' => ['scope' => $scope, 'registry' => $registry, 'supply_establishment' => $delivery],
            'missing_fields' => [], ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        if (! is_array($report['operation_input'] ?? null) || ! is_string($report['report_hash'] ?? null)) {
            return false;
        }
        $saved = $report;
        unset($saved['report_hash']);

        return hash_equals($report['report_hash'], CanonicalJson::hash($saved))
            && CanonicalJson::hash($report) === CanonicalJson::hash($this->qualify($report['operation_input']));
    }

    /** Qualify the location exception using supplied material predicates; never decide a credit right.
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $scope
     * @return array<string, mixed>
     */
    private function centralized(array $input, ?array $scope, DateTimeImmutable $known, string $on): array
    {
        if ($input['onerous'] !== true || $input['acquirer_domiciled_in_brazil'] !== true) {
            return $this->centralizedStop('DOMESTIC_ACQUIRER_REQUIRED', ['onerous', 'acquirer_domiciled_in_brazil']);
        }
        $category = $input['supply_category'] ?? null;
        $articles = ['FIXED_COMMUNICATION' => 'LC214 art.11 IX', 'MOVABLE_RENTAL' => 'LC214 art.11 §4 I',
            'RESIDUAL_SERVICE' => 'LC214 art.11 X', 'PERSONAL_REMOTE' => 'LC214 art.11 X e §5'];
        if (! is_string($category) || ! isset($articles[$category]) || $scope === null
            || ($scope['supply_category'] ?? null) !== $category || ($scope['source_article'] ?? null) !== $articles[$category]
            || ($scope['scope_confirmed'] ?? null) !== true || ($scope['centralized_purchase'] ?? null) !== true
            || ($category === 'MOVABLE_RENTAL' && ($scope['physical_movable_rental'] ?? null) !== true)
            || ($category === 'RESIDUAL_SERVICE' && ($scope['outside_inciso_I_to_IX'] ?? null) !== true)
            || ($category === 'PERSONAL_REMOTE' && (($scope['normally_personal_physical'] ?? null) !== true
                || ($scope['remotely_performed_at_least_partly'] ?? null) !== true))) {
            return $this->centralizedStop('SCOPE_RECORD_UNPROVEN', ['scope_evidence']);
        }
        $party = $input['acquirer_id'];
        $purchase = $this->record($input['centralized_purchase_evidence'], $input, $known, $on);
        if ($purchase === null || ($purchase['role'] ?? null) !== 'CENTRALIZED_ACQUISITION'
            || ($purchase['entity_id'] ?? null) !== $party || ($purchase['centralized'] ?? null) !== true
            || ($purchase['complete'] ?? null) !== true || ! $this->id($purchase['document_id'] ?? null)
            || ! $this->id($purchase['headquarters_establishment_id'] ?? null)) {
            return $this->centralizedStop('ACQUISITION_BINDING_UNPROVEN', ['centralized_purchase_evidence']);
        }
        $regime = $this->record($input['regular_regime_evidence'] ?? null, $input, $known, $on);
        if ($regime === null || ($regime['role'] ?? null) !== 'ACQUIRER_IBS_CBS_REGIME' || ($regime['entity_id'] ?? null) !== $party
            || ($regime['ibs_regime'] ?? null) !== 'REGULAR' || ($regime['cbs_regime'] ?? null) !== 'REGULAR'
            || ! $this->id($regime['document_id'] ?? null) || ! $this->covers($regime, $on)) {
            return $this->centralizedStop('BOTH_REGULAR_REGIMES_UNPROVEN', ['regular_regime_evidence']);
        }
        // This is an external qualification consumed for location only. Credit engine output/amount is not inferred here.
        $restriction = $this->record($input['credit_restriction_evidence'] ?? null, $input, $known, $on);
        if ($restriction === null || ($restriction['role'] ?? null) !== 'CREDIT_PROHIBITION_QUALIFICATION'
            || ($restriction['entity_id'] ?? null) !== $party || ($restriction['complete'] ?? null) !== true
            || ! $this->id($restriction['document_id'] ?? null) || ! $this->id($restriction['qualification_reference'] ?? null)
            || ($restriction['ibs_credit_prohibited'] ?? null) !== false || ($restriction['cbs_credit_prohibited'] ?? null) !== false) {
            return $this->centralizedStop('EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED', ['credit_restriction_evidence']);
        }
        $registry = $this->record($input['registry_evidence'] ?? null, $input, $known, $on);
        if ($registry === null || ($registry['role'] ?? null) !== 'UNIQUE_REGISTRATION_ESTABLISHMENTS'
            || ($registry['entity_id'] ?? null) !== $party || ($registry['country'] ?? null) !== 'BR'
            || ! in_array($registry['party_kind'] ?? null, ['LEGAL_ENTITY', 'UNINCORPORATED_ENTITY'], true)
            || ($registry['regularly_registered'] ?? null) !== true || ($registry['unique_registration'] ?? null) !== true
            || ($registry['all_establishments_included'] ?? null) !== true || ! $this->id($registry['document_id'] ?? null)) {
            return $this->centralizedStop('COMPLETE_ACQUIRER_REGISTRY_REQUIRED', ['registry_evidence']);
        }
        $entries = $registry['establishments'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || $entries === []) {
            return $this->centralizedStop('ESTABLISHMENT_SET_REQUIRED', ['registry_evidence.record.establishments']);
        }
        $seen = $active = $headquarters = $references = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! $this->id($entry['id'] ?? null) || isset($seen['ID:'.$entry['id']])
                || ($entry['entity_id'] ?? null) !== $party || ! is_bool($entry['headquarters'] ?? null)
                || ! is_string($entry['uf'] ?? null) || ! is_string($entry['municipality_ibge'] ?? null)
                || $this->date($entry['valid_from'] ?? null) === null || ! array_key_exists('valid_until', $entry)
                || ($entry['valid_until'] !== null && ($this->date($entry['valid_until']) === null || $entry['valid_until'] < $entry['valid_from']))) {
                return $this->centralizedStop('ESTABLISHMENT_IDENTITY_OR_INTERVAL_INVALID', ['registry_evidence.record.establishments']);
            }
            $key = 'ID:'.$entry['id'];
            $seen[$key] = true;
            if (! $this->covers($entry, $on)) {
                continue;
            }
            $reference = (new ObservedGeographicReference115)->resolve($this->geography,
                $entry['municipality_ibge'], $entry['uf'], $known, $this->geographyPolicy);
            if ($reference['status'] !== 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT') {
                return $this->centralizedStop($reference['reason'], ['registry_evidence.record.establishments']);
            }
            $active[$key] = $entry;
            $references[$key] = $reference;
            if ($entry['headquarters'] === true) {
                $headquarters[] = $entry;
            }
        }
        if (count($active) < 2) {
            return $this->centralizedStop('MORE_THAN_ONE_ACTIVE_ESTABLISHMENT_REQUIRED', ['registry_evidence.record.establishments']);
        }
        if (count($headquarters) !== 1 || $headquarters[0]['id'] !== $purchase['headquarters_establishment_id']) {
            return $this->centralizedStop('UNIQUE_ACTIVE_HEADQUARTERS_BINDING_REQUIRED', ['centralized_purchase_evidence.record.headquarters_establishment_id']);
        }
        $covered = $purchase['covered_establishment_ids'] ?? null;
        if (! is_array($covered) || ! array_is_list($covered) || $covered === []) {
            return $this->centralizedStop('ACQUISITION_ESTABLISHMENT_COVERAGE_REQUIRED', ['centralized_purchase_evidence.record.covered_establishment_ids']);
        }
        $coverage = [];
        foreach ($covered as $id) {
            if (! $this->id($id) || ! isset($active['ID:'.$id]) || isset($coverage['ID:'.$id])) {
                return $this->centralizedStop('ACQUISITION_ESTABLISHMENT_COVERAGE_CONFLICT', ['centralized_purchase_evidence.record.covered_establishment_ids']);
            }
            $coverage['ID:'.$id] = true;
        }
        $hq = $headquarters[0];
        $activeIds = array_column(array_values($active), 'id');
        sort($activeIds, SORT_STRING);
        sort($covered, SORT_STRING);
        $report = ['contract' => self::CENTRALIZED_CONTRACT, 'status' => 'LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED',
            'reason' => 'DOMICILE152_CENTRALIZED_REGULAR_ACQUIRER_HEADQUARTERS_BOUND',
            'source_article' => in_array($category, ['FIXED_COMMUNICATION', 'MOVABLE_RENTAL'], true)
                ? 'LC214 art.11 §4 I e II' : $articles[$category].'; §4 II',
            'destination_basis' => 'ACQUIRER_DOMICILE', 'selected_party_id' => $party,
            'selected_establishment_id' => $hq['id'], 'headquarters_selected' => true,
            'locality' => ['uf' => $hq['uf'], 'municipality_ibge' => $hq['municipality_ibge']],
            'active_establishment_ids' => $activeIds, 'covered_establishment_ids' => $covered,
            'registry_interval_semantics' => 'EXPLICIT_INCLUSIVE_DATES',
            'geographic_reference' => $references['ID:'.$hq['id']],
            'source' => ['url' => 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html',
                'sha256' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde',
                'available_at' => ServiceLocality149::SOURCE_AVAILABLE_AT],
            'cbs_regulation_source' => ['url' => 'https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html',
                'sha256' => '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235', 'source_article' => 'Decreto12955 art.12 §4'],
            'operation_input' => $input, 'input_hash' => CanonicalJson::hash($input),
            'material_records' => ['scope' => $scope, 'registry' => $registry, 'centralized_acquisition' => $purchase,
                'regular_regime' => $regime, 'external_credit_restriction' => $restriction],
            'external_credit_restriction_qualification_consumed' => true,
            'credit_restriction_assessment_authenticated' => false, 'missing_fields' => [], ...$this->limits()];

        return [...$report, 'report_hash' => CanonicalJson::hash($report)];
    }

    /** @param array<string, mixed> $record */
    private function covers(array $record, string $on): bool
    {
        return $this->date($record['valid_from'] ?? null) !== null && $record['valid_from'] <= $on
            && array_key_exists('valid_until', $record)
            && ($record['valid_until'] === null || ($this->date($record['valid_until']) !== null && $record['valid_until'] >= $on));
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function centralizedStop(string $reason, array $missing): array
    {
        return ['contract' => self::CENTRALIZED_CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => 'DOMICILE152_'.$reason,
            'missing_fields' => $missing, 'locality' => null, ...$this->limits()];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    private function record(mixed $envelope, array $input, DateTimeImmutable $known, string $on): ?array
    {
        if (! is_array($envelope) || ! $this->id($envelope['source_id'] ?? null)
            || ! is_array($envelope['record'] ?? null) || ! is_string($envelope['record_hash'] ?? null)
            || ! hash_equals($envelope['record_hash'], CanonicalJson::hash($envelope['record']))) {
            return null;
        }
        $record = $envelope['record'];
        foreach (['operation_id', 'supply_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'onerous',
            'acquirer_domiciled_in_brazil', 'recipient_domiciled_in_brazil'] as $field) {
            if (! array_key_exists($field, $record) || $record[$field] !== $input[$field]) {
                return null;
            }
        }
        $observed = $this->instant($record['observed_at'] ?? null);
        if (($record['applicable_on'] ?? null) !== $on || $observed === null || $observed > $known
            || $observed->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d') < $on) {
            return null;
        }

        return $record;
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value && preg_match('//u', $value) === 1
            && preg_match('/[\x00-\x1f\x7f]|\A\p{Z}|\p{Z}\z/u', $value) === 0;
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
        if (! is_string($value) || preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-](?:[01][0-9]|2[0-3]):[0-5][0-9])\z/', $value) !== 1
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
            'tax_rate_selected' => false, 'tax_incidence_determined' => false, 'credit_right_determined' => false,
            'source_record_authenticity_proven' => false, 'official_registry_queried' => false,
            'historical_juridical_validity_proven' => false, 'document_obligation_complete' => false,
            'posting_authorized' => false, 'production_ready' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing): array
    {
        return ['contract' => self::CONTRACT, 'status' => 'ACTION_REQUIRED', 'reason' => 'DOMICILE151_'.$reason,
            'missing_fields' => $missing, 'locality' => null, ...$this->limits()];
    }
}
