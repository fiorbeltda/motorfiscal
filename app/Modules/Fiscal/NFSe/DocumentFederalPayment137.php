<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Throwable;

/** Draft coordinator:replay each bound payment through the existing4A engine. */
final readonly class DocumentFederalPayment137
{
    public const CONTRACT = 'LOCAL-DOCUMENT-FEDERAL-PAYMENT-137-V6';

    public const AVAILABLE_AT = '2026-10-23T18:15:12+00:00';

    /**
     * @return array<string, mixed>
     */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'prior_projection' => PartialExportDpsPair135::policy(), 'retention_projection' => FederalRetention137::policy(),
            'payment_scope' => 'FULL_SEPARATE_BRL_SETTLEMENT_FOR_EACH_DOCUMENT',
            'provider_scope' => 'BR_PRIVATE_LEGAL_ENTITY_DOMESTIC_SERVICE_WITH_ORDINARY_REGIME',
            'own_customer_country_bound_to_acquirer' => true, 'source_capture_pending' => false, 'scope' => 'STAGED_BINDING_NOT_DOCUMENT_OBLIGATION_APPROVAL'];
    }

    /**
     * @param  array<array-key, mixed>  $snapshot
     * @param  array<array-key, mixed>  $declarations
     * @param  array<array-key, mixed>  $proofs
     * @param  array<array-key, mixed>  $policy
     * @return array<string, mixed>
     */
    public function bind(array $snapshot, array $declarations, array $proofs, array $policy, string $resourceRoot, ?DocumentFederalPayment139 $publicContract = null): array
    {
        $expectedPolicy = $publicContract === null ? self::policy() : DocumentFederalPayment139::policy();
        $availableAt = $expectedPolicy['available_at'];
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode($expectedPolicy)) {
            return $this->stop('EXACT_POLICY_REQUIRED');
        }
        $known = $this->instant($snapshot['input']['knowledge_at'] ?? null);
        if ($known === null || $known < new DateTimeImmutable($availableAt)) {
            return $this->stop('SOURCE_NOT_YET_KNOWN');
        }
        if (! array_is_list($proofs) || count($proofs) !== 2) {
            return $this->stop('TWO_PAYMENT_PROOFS_REQUIRED');
        }
        try {
            $prior = (new PartialExportDpsPair135)->project($snapshot, $declarations, $resourceRoot);
        } catch (Throwable $e) {
            return [...$this->stop('PRIOR_SNAPSHOT_AND_DOCUMENT_REPLAY_REQUIRED'), 'upstream_error' => $e->getMessage()];
        }
        $factory = new FiscalEngineFactory;
        $knowledge = $snapshot['knowledge']; // The prior projector has independently captured and compared this complete knowledge.
        if ($publicContract !== null) {
            try {
                $knowledge = $publicContract->captureKnowledge($resourceRoot);
            } catch (Throwable $e) {
                return [...$this->stop('EXACT_CURRENT_FEDERAL_KNOWLEDGE_REQUIRED'), 'upstream_error' => $e->getMessage()];
            }
        }
        $rtc = array_values(array_filter($snapshot['result']['trace'], static fn (array $t): bool => $t['type'] === 'CONSUMPTION_REFORM'))[0]['payload'];
        $iss = array_values(array_filter($rtc['trace'], static fn (array $t): bool => $t['stage'] === 'PARTIAL_EXPORT_DOCUMENT_ISS134'))[0];
        $ownDocuments = array_column($iss['documents'], null, 'role');
        $raw = $snapshot['input']['facts'];
        if (($raw['provider.person_type'] ?? null) !== 'PRIVATE_LEGAL_ENTITY'
            || ($raw['rtc.supplier_country'] ?? null) !== 'BR'
            || ($raw['operation.service_origin'] ?? null) !== 'DOMESTIC') {
            return $this->stop('MATERIAL_PROVIDER_OUTSIDE_EXPLICIT_SUPPORTED_PROFILE');
        }
        foreach ($declarations['own_tax_proofs'] as $ownClaim) {
            if ($ownClaim['value']['document']['customer_id'] !== $raw['rtc.acquirer_id']
                || $ownClaim['value']['revenue']['customer_country'] !== $raw['rtc.acquirer_country']) {
                return $this->stop('OWN_CUSTOMER_COUNTRY_CONFLICT_WITH_MATERIAL_ACQUIRER');
            }
        }
        $ownByRole = array_column($prior['bridge']['fresh_own_calculation']['documents'], null, 'role');
        $ids = [];
        $payments = [];
        $rows = [];
        foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs'], $declarations['documentary_proof']] as $claim) {
            $ids[$claim['evidence_id']] = true;
        }
        foreach ($prior['records'] as $record) {
            $role = $record['role'];
            $matches = array_values(array_filter($proofs, static fn (mixed $p): bool => is_array($p) && ($p['value']['document']['role'] ?? null) === $role));
            if (count($matches) !== 1) {
                return $this->stop('UNIQUE_PAYMENT_PER_DOCUMENT_REQUIRED');
            }
            $proof = $matches[0];
            if (! $this->captured($proof, $known, $expectedPolicy['contract'], $availableAt) || isset($ids[$proof['evidence_id']])) {
                return $this->stop('CURRENT_DISTINCT_PAYMENT_CAPTURES_REQUIRED');
            }
            $ids[$proof['evidence_id']] = true;
            foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs'], $declarations['documentary_proof']] as $dependency) {
                if ($this->instant($dependency['observed_at']) > $this->instant($proof['observed_at'])) {
                    return $this->stop('PAYMENT_PROOF_PREDATES_DOCUMENTARY_DEPENDENCIES');
                }
            }
            $value = $proof['value'];
            $context = (new SoftwareOwnRevenue135)->context($iss, $ownDocuments[$role]);
            if (! $this->keys($value, ['source_policy_hash', 'snapshot_hash', 'documentary_proof_hash', 'document', 'payment', 'profile', 'federal_facts'])
                || $value['source_policy_hash'] !== CanonicalJson::hash($policy) || $value['snapshot_hash'] !== CanonicalJson::hash($snapshot)
                || $value['documentary_proof_hash'] !== $declarations['documentary_proof']['source_hash']
                || CanonicalJson::encode($value['document']) !== CanonicalJson::encode($context)) {
                return $this->stop('FULL_DOCUMENT_AND_SOURCE_CONTEXT_CONFLICT');
            }
            $payment = $value['payment'];
            $profile = $value['profile'];
            $extra = $value['federal_facts'];
            if (! is_array($payment) || ! $this->keys($payment, ['reference', 'currency', 'gross_brl', 'net_brl', 'settled_on', 'payer_role', 'payer_id', 'payer_country', 'payer_person_type', 'payee_id', 'bank', 'payer_accounting_credit'])
                || ! $this->reference($payment['reference']) || isset($payments[$payment['reference']])
                || $payment['currency'] !== 'BRL' || $payment['gross_brl'] !== $record['gross_document_price_brl']
                || ! $this->money($payment['net_brl']) || ! $this->day($payment['settled_on'])
                || $payment['settled_on'] > $known->format('Y-m-d') || $payment['payee_id'] !== $context['supplier_id']
                || ! $this->day($proof['valid_from']) || ! $this->day($proof['valid_to'])
                || $proof['valid_from'] > min($context['iss_supply_on'], $payment['settled_on'])
                || $proof['valid_to'] < max($context['iss_supply_on'], $payment['settled_on'])) {
                return $this->stop('EXACT_SEPARATE_FULL_DOCUMENT_PAYMENT_REQUIRED');
            }
            $credit = $payment['payer_accounting_credit'];
            if (! is_array($credit) || ! $this->keys($credit, ['status', 'credited_on', 'evidence_reference'])
                || ! $this->reference($credit['evidence_reference'])
                || ! in_array($credit['status'], ['NO_PAYER_ACCOUNTING_CREDIT', 'DATED_PAYER_ACCOUNTING_CREDIT'], true)
                || ($credit['status'] === 'NO_PAYER_ACCOUNTING_CREDIT' && $credit['credited_on'] !== null)
                || ($credit['status'] === 'DATED_PAYER_ACCOUNTING_CREDIT' && (! $this->day($credit['credited_on'])
                    || $credit['credited_on'] > $known->format('Y-m-d') || $credit['credited_on'] < $proof['valid_from'] || $credit['credited_on'] > $proof['valid_to']))) {
                return $this->stop('EXPLICIT_DATED_PAYER_CREDIT_OR_EVIDENCED_ABSENCE_REQUIRED');
            }
            $payments[$payment['reference']] = true;
            $party = match ($payment['payer_role']) {
                'ACQUIRER' => ['id' => $raw['rtc.acquirer_id'], 'country' => $raw['rtc.acquirer_country']],
                'DOMESTIC_RECIPIENT' => ['id' => $raw['rtc.export_domestic_recipient_id'], 'country' => $raw['rtc.export_domestic_recipient_country']],
                default => null
            };
            if ($party === null || $payment['payer_id'] !== $party['id'] || $payment['payer_country'] !== $party['country']
                || ($party['country'] !== 'BR' && $payment['payer_person_type'] !== 'FOREIGN')
                || ($party['country'] === 'BR' && in_array($payment['payer_person_type'], ['FOREIGN', 'INDIVIDUAL'], true))) {
                return $this->stop('PAYING_PARTY_NOT_BOUND_TO_DECLARED_DOCUMENT_ROLE');
            }
            if (! is_array($profile) || ! $this->keys($profile, ['provider_tax_regime', 'payer_tax_regime', 'provider_head_office_tax_id', 'payer_head_office_tax_id', 'head_office_evidence_reference'])
                || ! in_array($profile['provider_tax_regime'], ['LUCRO_REAL', 'LUCRO_PRESUMIDO'], true)
                || $profile['provider_tax_regime'] !== ($raw['provider.tax_regime'] ?? null)
                || ! $this->reference($profile['head_office_evidence_reference'])
                || ! is_string($profile['provider_head_office_tax_id']) || preg_match('/\A[0-9]{14}\z/', $profile['provider_head_office_tax_id']) !== 1
                || substr($profile['provider_head_office_tax_id'], 0, 8) !== substr($context['supplier_id'], 0, 8)
                || ($party['country'] !== 'BR' && $profile['payer_head_office_tax_id'] !== null)
                || ($party['country'] === 'BR' && (! is_string($profile['payer_head_office_tax_id']) || preg_match('/\A[0-9]{14}\z/', $profile['payer_head_office_tax_id']) !== 1
                    || substr($profile['payer_head_office_tax_id'], 0, 8) !== substr($payment['payer_id'], 0, 8)))
                || ($payment['payer_person_type'] === 'FOREIGN' && $profile['payer_tax_regime'] !== 'NOT_APPLICABLE')
                || ! is_array($extra) || array_is_list($extra) || ! $this->bank($payment, $context['document_reference'])) {
                return $this->stop('CAPTURED_BANK_AND_EXPLICIT_LEGAL_PARTY_PROFILES_REQUIRED');
            }
            $ownClaims = array_values(array_filter($declarations['own_tax_proofs'], static fn (array $p): bool => $p['value']['document']['role'] === $role));
            $revenue = $ownClaims[0]['value']['revenue'];
            if ($revenue['treatment'] === 'SERVICE_EXPORT_WITH_DIVISAS') {
                $inflow = json_decode($revenue['bank_inflow']['content'], true, flags: JSON_THROW_ON_ERROR);
                if ($inflow['payer_id'] !== $payment['payer_id'] || $inflow['payee_id'] !== $payment['payee_id']
                    || $inflow['payer_country'] !== $payment['payer_country'] || $inflow['settled_on'] !== $payment['settled_on']
                    || $inflow['credited_brl'] !== $payment['net_brl'] || $inflow['currency'] !== $payment['currency']) {
                    return $this->stop('EXPORT_PAYMENT_CONFLICTS_WITH_CAPTURED_OWN_REVENUE_DIVISAS');
                }
            }
            $fixed = ['service.canonical_id' => $raw['service.canonical_id'], 'provider.municipality_ibge' => $raw['provider.municipality_ibge'],
                'customer.municipality_ibge' => $raw['customer.municipality_ibge'], 'customer.person_type' => 'PJ',
                'operation.service_origin' => 'DOMESTIC', 'operation.amount' => $payment['gross_brl'],
                'federal.evaluate_withholdings' => true, 'rtc.evaluate' => false, 'rtc.supplier_id' => $context['supplier_id'],
                'provider.person_type' => 'PRIVATE_LEGAL_ENTITY', 'provider.head_office_tax_id' => $profile['provider_head_office_tax_id'],
                'provider.tax_regime' => $profile['provider_tax_regime'], 'payer.person_type' => $payment['payer_person_type'],
                'payer.tax_regime' => $profile['payer_tax_regime'], 'payment.occurred' => true, 'payment.date' => $payment['settled_on'],
                'payment.gross_amount' => $payment['gross_brl'], 'invoice.issue_date' => substr($declarations['documentary_proof']['value']['options']['issued_at'], 0, 10),
                'invoice.gross_amount' => $payment['gross_brl']];
            if ($party['country'] === 'BR') {
                $fixed['payer.head_office_tax_id'] = $profile['payer_head_office_tax_id'];
            }
            if ($credit['status'] === 'DATED_PAYER_ACCOUNTING_CREDIT') {
                $fixed['payment.accounting_credit_date'] = $credit['credited_on'];
            }
            foreach ($extra as $key => $unused) {
                if (! is_string($key) || array_key_exists($key, $fixed) || preg_match('/\A(?:federal|inss|public|payer|provider)\./', $key) !== 1) {
                    return $this->stop('CAPTURED_FEDERAL_FACTS_CANNOT_REWRITE_BOUND_MONEY_PARTIES_OR_EVENTS');
                }
            }
            $input = ['scenario_id' => ($publicContract === null ? 'STAGED-FEDERAL137-' : 'STAGED-FEDERAL139-').$role.'-'.$payment['reference'], 'competence_date' => $context['iss_supply_on'],
                'knowledge_at' => $known->format('Y-m-d\TH:i:s.uP'), 'rule_bundle_id' => $knowledge['rule_bundle_id'],
                'catalog_versions' => $knowledge['catalog_versions'], 'facts' => [...$fixed, ...$extra]];
            try {
                $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
                $first = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
                $second = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
            } catch (Throwable $e) {
                return [...$this->stop('TYPED_FEDERAL_FACTS_AND_EXECUTION_REQUIRED'), 'upstream_error' => $e->getMessage()];
            }
            if (CanonicalJson::hash($first) !== CanonicalJson::hash($second)) {
                return $this->stop('EXACT_FEDERAL_REPLAY_REQUIRED');
            }
            $federal = $first['decision']['federal_withholdings'] ?? null;
            if (! is_array($federal) || ($federal['status'] ?? null) !== 'RESOLVED') {
                if ($publicContract !== null) {
                    return [...$this->stop('FINAL_DOCUMENT_FEDERAL_ASSESSMENT_REQUIRED'), 'role' => $role,
                        'upstream_status' => $first['status'], 'missing_facts' => $first['missing_facts'],
                        'questions' => $first['questions'], 'reason_codes' => $first['reason_codes'], 'trace' => $first['trace']];
                }

                return [...$this->stop('FINAL_DOCUMENT_FEDERAL_ASSESSMENT_REQUIRED'), 'upstream' => $federal];
            }
            $fields = (new FederalRetention137)->project($federal, $policy['retention_projection'], $known, $resourceRoot);
            if ($fields['status'] !== 'STAGED_FEDERAL_RETENTION_FIELDS_COMPUTED') {
                return [...$this->stop('RECONCILED_RETENTION_COMPONENTS_REQUIRED'), 'upstream' => $fields];
            }
            $doc = new DOMDocument;
            $doc->loadXML($record['xml'], LIBXML_NONET);
            $xp = new DOMXPath($doc);
            $xp->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
            $issIndicator = $xp->evaluate('string(/n:DPS/n:infDPS/n:valores/n:trib/n:tribMun/n:tpRetISSQN)');
            if (! in_array($issIndicator, ['1', '2', '3'], true)) {
                return $this->stop('EXPLICIT_FRESH_MUNICIPAL_RETENTION_STATUS_REQUIRED');
            }
            $issWithheld = in_array($issIndicator, ['2', '3'], true) ? $record['own_iss_tax_brl'] : '0.00';
            if (! $this->money($issWithheld)) {
                return $this->stop('EXACT_WITHHELD_ISS_AMOUNT_REQUIRED');
            }
            $allRetained = DecimalValue::fromString($federal['total_withholding'])->plus(DecimalValue::fromString($issWithheld));
            $net = DecimalValue::fromString($payment['gross_brl'])->minus($allRetained)->rounded(2)->value();
            if ($net !== $payment['net_brl']) {
                return [...$this->stop('BANK_NET_CONFLICT_WITH_FRESH_FEDERAL_AND_MUNICIPAL_RETENTIONS'), 'role' => $role, 'expected_net_brl' => $net];
            }
            $rows[] = ['role' => $role, 'document_reference' => $context['document_reference'], 'payment_reference' => $payment['reference'],
                'payer_role' => $payment['payer_role'], 'payer_id' => $payment['payer_id'], 'payee_id' => $payment['payee_id'],
                'own_contribution_treatment' => $ownByRole[$role]['own_contribution_treatment'], 'own_incident_amounts_brl' => $ownByRole[$role]['own_incident_amounts_brl'], 'gross_document_price_brl' => $payment['gross_brl'], 'bank_net_brl' => $net, 'withheld_iss_brl' => $issWithheld,
                'total_retained_brl' => $allRetained->rounded(2)->value(), 'federal_evaluation' => $federal, 'retention_fields' => $fields['fields'],
                'proof_hash' => $proof['source_hash'], 'federal_snapshot' => ['input' => $input, 'knowledge' => $knowledge, 'result' => $first],
                'exact_federal_replays' => 2, 'federal_context_scope' => 'WITHHOLDING_ONLY_NOT_AUXILIARY_ISS_OR_RTC_DECISIONS', 'head_office_inferred_from_document_identity' => false, 'payer_accounting_credit' => $credit, 'payer_credit_absence_inferred' => false];
        }
        usort($rows, static fn (array $a, array $b): int => $a['role'] <=> $b['role']);

        return ['status' => 'STAGED_PER_DOCUMENT_FEDERAL_PAYMENTS_BOUND', 'documents' => $rows, 'source_snapshot_hash' => CanonicalJson::hash($snapshot),
            'source_policy_hash' => CanonicalJson::hash($policy), 'binding_hash' => CanonicalJson::hash($rows),
            'per_document_replay_binding_qualified' => true, 'own_pis_cofins_replaced_with_withholding' => false,
            'bank_or_party_authenticated' => false, 'nt007_original_bytes_captured' => true,
            'eligible_for_integration' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /**
     * @param  array<array-key, mixed>  $payment
     */
    private function bank(array $payment, string $document): bool
    {
        $bank = $payment['bank'];
        if (! is_array($bank) || ! $this->keys($bank, ['reference', 'media_type', 'content', 'content_sha256']) || ! $this->reference($bank['reference'])
            || $bank['media_type'] !== 'application/json' || ! is_string($bank['content']) || hash('sha256', $bank['content']) !== $bank['content_sha256']) {
            return false;
        }
        $expected = ['document_reference' => $document, 'payment_reference' => $payment['reference'], 'payer_id' => $payment['payer_id'],
            'payer_country' => $payment['payer_country'], 'payee_id' => $payment['payee_id'], 'currency' => $payment['currency'],
            'gross_brl' => $payment['gross_brl'], 'net_brl' => $payment['net_brl'], 'settled_on' => $payment['settled_on']];

        return $bank['content'] === CanonicalJson::encode($expected);
    }

    /**
     * @param  array<array-key, mixed>  $p
     */
    private function captured(array $p, DateTimeImmutable $known, string $contract, string $availableAt): bool
    {
        if (! $this->keys($p, ['contract', 'evidence_id', 'observed_at', 'valid_from', 'valid_to', 'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'])
            || $p['contract'] !== $contract || ! $this->reference($p['evidence_id']) || ! is_array($p['value'])) {
            return false;
        }
        $observed = $this->instant($p['observed_at']);
        if ($observed === null || $observed > $known || $observed < new DateTimeImmutable($availableAt)) {
            return false;
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($p[$key] !== null && ($this->instant($p[$key]) === null || $this->instant($p[$key]) <= $known)) {
                return false;
            }
        }
        $origin = $p['origin'];
        $body = array_diff_key($p, ['origin' => true, 'source_hash' => true]);

        return is_array($origin) && $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256']) && $this->reference($origin['reference'])
            && $origin['media_type'] === 'application/json' && $origin['content'] === CanonicalJson::encode($body)
            && hash('sha256', $origin['content']) === $origin['content_sha256']
            && $p['source_hash'] === CanonicalJson::hash(array_diff_key($p, ['source_hash' => true]));
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }$format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    private function day(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }$d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    private function reference(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<array-key, mixed>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'DOCPAY137_'.$reason, 'eligible_for_integration' => false, 'production_ready' => false];
    }
}
