<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DerePgccDeductionBindingQualifier;
use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/** Rebuilds local monthly prerequisites; does not represent government reception or legal homologation. */
final readonly class DereMonthlyClosingXmlProjector
{
    public const CONTRACT = 'DERE-MONTHLY-CLOSING-LOCAL-2026.10.02-1';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $month
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function project(array $operation, array $month, DateTimeImmutable $knownAt, array $rules): array
    {
        $rule = $rules['closing'] ?? [];
        if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null)) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        $book = $month['book'] ?? null;
        $identity = $month['identity'] ?? null;
        $prerequisites = $month['prerequisites'] ?? null;
        if (! is_array($book) || ! is_array($identity) || ! is_array($prerequisites)
            || ($prerequisites['contributor_registration_qualified'] ?? null) !== true
            || ($prerequisites['auxiliary_obligations_qualified'] ?? null) !== true
            || ($prerequisites['existing_closing'] ?? null) !== false
            || ($prerequisites['negative_base_use_requested'] ?? null) !== false
            || ! $this->hash($prerequisites['contributor_source_hash'] ?? null)
            || ! $this->hash($prerequisites['obligations_source_hash'] ?? null)
            || ($prerequisites['supplier_id'] ?? null) !== ($book['supplier_id'] ?? null)
            || ($prerequisites['period_end'] ?? null) !== ($book['period_end'] ?? null)
            || ($prerequisites['pgcc_source_hash'] ?? null) !== ($book['pgcc_source_hash'] ?? null)) {
            return $this->stop('PREREQUISITES_UNPROVEN_OR_CONFLICTING');
        }
        $contributorProjection = null;
        if (($rule['contributor_projection_required'] ?? false) === true) {
            $profile = $month['contributor'] ?? null;
            $contributorIdentity = $month['contributor_identity'] ?? null;
            $catalogs = $rule['contributor_catalogs'] ?? null;
            if (! is_array($profile) || ! is_array($contributorIdentity) || ! is_array($catalogs)
                || ($profile['supplier_id'] ?? null) !== $book['supplier_id'] || ($profile['period_end'] ?? null) !== $book['period_end']
                || ($profile['source_hash'] ?? null) !== $prerequisites['contributor_source_hash']) {
                return $this->stop('CONTRIBUTOR_SCOPE_OR_CATALOGS_UNPROVEN');
            }
            $contributorProjection = (new DereContributorXmlProjector)->project($profile, $contributorIdentity, $catalogs, $knownAt,
                $rules['contributor_projection'] ?? []);
            if ($contributorProjection['status'] !== 'PROJECTED') {
                return $contributorProjection;
            }
            if ($profile['regTribPrinc'] !== '1' && ! in_array('1', $profile['regTribSecund'], true)) {
                return $this->stop('CONTRIBUTOR_FINANCIAL_REGIME_CONFLICT');
            }
            if (($rule['contributor_operation_activity_required'] ?? false) === true) {
                $category = $operation['qualification']['operation_category'] ?? null;
                $code = is_string($category) ? ($rule['operation_activity_codes'][$category] ?? null) : null;
                if (! is_string($code) || ! in_array($code, $profile['servFinanc']['tpAtividades'], true)) {
                    return $this->stop('CONTRIBUTOR_OPERATION_ACTIVITY_CONFLICT');
                }
                if (! is_string($operation['base']['period_start'] ?? null)
                    || $profile['valid_from'] > $operation['base']['period_start']) {
                    return $this->stop('CONTRIBUTOR_MONTH_VALIDITY_CONFLICT');
                }
            }
            if (isset($month['pgcc']) && ($profile['valid_from'] > ($month['pgcc']['valid_from'] ?? '')
                || (isset($profile['valid_to'], $month['pgcc']['valid_to']) && $profile['valid_to'] < $month['pgcc']['valid_to'])
                || (($rule['pgcc_projection_required'] ?? false) === true && (($month['pgcc_identity']['contributor_source_hash'] ?? null) !== $profile['source_hash']
                || ($month['pgcc_identity']['contributor_valid_from'] ?? null) !== $profile['valid_from']
                || ($month['pgcc_identity']['contributor_valid_to'] ?? null) !== ($profile['valid_to'] ?? null)
                || ($month['pgcc_identity']['dere_start_on'] ?? null) !== $profile['dere_start_on']
                || ($month['pgcc_identity']['dere_start_source_hash'] ?? null) !== $profile['dere_start_source_hash'])))) {
                return $this->stop('CONTRIBUTOR_PGCC_VALIDITY_OR_EVIDENCE_CONFLICT');
            }
        }
        $pgcc = null;
        $pgccProjection = null;
        if (($rule['pgcc_projection_required'] ?? false) === true && ($rule['pgcc_qualification_required'] ?? false) !== true) {
            return $this->stop('PGCC_POLICY_CONFLICT');
        }
        if (($rule['pgcc_qualification_required'] ?? false) === true) {
            $plan = $month['pgcc'] ?? null;
            $catalogs = $rule['pgcc_catalogs'] ?? null;
            if (! is_array($plan) || ! is_array($catalogs) || ! is_array($catalogs['reference'] ?? null) || ! is_array($catalogs['tax_codes'] ?? null)
                || ($plan['supplier_id'] ?? null) !== $book['supplier_id'] || ($plan['period_end'] ?? null) !== $book['period_end']
                || ($plan['source_hash'] ?? null) !== $book['pgcc_source_hash']) {
                return $this->stop('PGCC_SCOPE_OR_CATALOGS_UNPROVEN');
            }
            $pgcc = (new DerePgccQualifier)->resolve($plan, $catalogs['reference'], $catalogs['tax_codes'], $knownAt, false, $rule['pgcc_qualification_policy'] ?? []);
            if ($pgcc['status'] !== 'RESOLVED') {
                return $pgcc;
            }
            if (($rule['pgcc_projection_required'] ?? false) === true) {
                $pgccIdentity = $month['pgcc_identity'] ?? null;
                if (! is_array($pgccIdentity)) {
                    return $this->stop('PGCC_DECLARATION_IDENTITY_UNPROVEN');
                }
                $pgccProjection = (new DerePgccXmlProjector)->project($plan, $pgccIdentity, $catalogs['reference'], $catalogs['tax_codes'],
                    $knownAt, $rules['pgcc_projection'] ?? []);
                if ($pgccProjection['status'] !== 'PROJECTED') {
                    return $pgccProjection;
                }
            }
            if ($pgcc['freqEncerr'] !== $book['closing_frequency']) {
                return $this->stop('PGCC_CLOSING_FREQUENCY_CONFLICT');
            }
            $analytical = [];
            foreach ($pgcc['accounts'] as $account) {
                if ($account['indCta'] === 'A') {
                    $analytical[$account['cCta']] = array_intersect_key($account, array_flip(['cCta', 'indCta', 'codNat', 'valid_from', 'valid_to']));
                }
            }
            $bookAccounts = [];
            foreach ($book['pgcc_accounts'] ?? [] as $account) {
                if (! is_array($account) || ! is_string($account['cCta'] ?? null) || isset($bookAccounts[$account['cCta']])) {
                    return $this->stop('PGCC_BOOK_ACCOUNTS_INVALID');
                }
                $bookAccounts[$account['cCta']] = $account;
            }
            if (CanonicalJson::encode($bookAccounts) !== CanonicalJson::encode($analytical)) {
                return $this->stop('PGCC_BOOK_ACCOUNTS_CONFLICT');
            }
            $balanceCodes = array_column($book['accounts'] ?? [], 'cCta');
            sort($balanceCodes);
            $planCodes = array_keys($analytical);
            sort($planCodes);
            if ($balanceCodes !== $planCodes) {
                return $this->stop('PGCC_ANALYTICAL_BALANCE_INCOMPLETE');
            }
            foreach (['D1106', 'D1121', 'D2101'] as $event) {
                if (($prerequisites[$event.'_required'] ?? null) !== in_array($event, $pgcc['required_auxiliary_events'], true)) {
                    return $this->stop('PGCC_AUXILIARY_OBLIGATIONS_CONFLICT');
                }
            }
        }
        foreach (['D1106_required', 'D2101_required', 'D1121_required'] as $flag) {
            if (! is_bool($prerequisites[$flag] ?? null)) {
                return $this->stop('AUXILIARY_OBLIGATIONS_UNPROVEN');
            }
        }
        $debtPolicy = $rule['debt_no_movement_projection'] ?? null;
        $principalPolicy = $rule['debt_principal_movement_projection'] ?? null;
        $yieldPolicy = $rule['debt_yield_projection'] ?? null;
        $hasYield = array_any($operation['base']['entries'] ?? [], static fn (array $entry): bool => ($entry['fiscal_origin'] ?? null) === 'D2101');
        if ($hasYield && (! $prerequisites['D2101_required'] || ! is_array($yieldPolicy) || ! isset($month['debt_yield']))) {
            return $this->stop('D2101_YIELD_DETAIL_OR_POLICY_REQUIRED');
        }
        if ($prerequisites['D1106_required'] || ($prerequisites['D2101_required'] && ! is_array($debtPolicy))) {
            return $this->stop('ADDITIONAL_AUXILIARY_EVENTS_PENDING');
        }
        $securitiesBinding = null;
        if (($operation['base']['realization_bindings'] ?? []) !== []
            || array_any($operation['base']['entries'] ?? [], static fn (array $entry): bool => ($entry['kind'] ?? null) === 'QUALIFIED_SECURITIES_COMPONENT')) {
            if ($pgcc === null || ($rule['pgcc_qualification_policy']['official_catalogs_required'] ?? false) !== true) {
                return $this->stop('TVM_REALIZATION_PGCC_CONTEXT_REQUIRED');
            }
            $securitiesBinding = (new DereSecuritiesRealizationBindingQualifier)->resolve($operation, $month, $pgcc, $knownAt,
                $rule['securities_realization_binding'] ?? []);
            if ($securitiesBinding['status'] !== 'RESOLVED') {
                return $securitiesBinding;
            }
        }
        $balance = (new DereTrialBalanceXmlProjector)->project($operation, $book, $identity, $knownAt, $rules['balance'] ?? [], $securitiesBinding);
        if ($balance['status'] !== 'PROJECTED') {
            return $balance;
        }
        $debtProjection = null;
        if (is_array($debtPolicy)) {
            if ($prerequisites['D2101_required']) {
                if ($pgcc === null || ($rule['pgcc_qualification_policy']['official_catalogs_required'] ?? false) !== true
                    || ($debtPolicy['allowed_codes'] ?? null) !== ($rule['pgcc_catalogs']['tax_codes']['auxiliary_codes']['D2101'] ?? null)) {
                    return $this->stop('D2101_POLICY_CONFLICT');
                }
                if (isset($month['debt_yield'])) {
                    if (! is_array($yieldPolicy) || ($yieldPolicy['allowed_codes'] ?? null) !== $debtPolicy['allowed_codes']) {
                        return $this->stop('D2101_YIELD_POLICY_UNAVAILABLE');
                    }
                    $debtProjection = (new DereDebtYieldXmlProjector)->project($operation, $month, $pgcc, $balance, $knownAt, $yieldPolicy);
                } elseif (isset($month['debt_principal_movement'])) {
                    if (! is_array($principalPolicy) || ($principalPolicy['allowed_codes'] ?? null) !== $debtPolicy['allowed_codes']) {
                        return $this->stop('D2101_PRINCIPAL_POLICY_UNAVAILABLE');
                    }
                    $debtProjection = (new DereDebtPrincipalMovementXmlProjector)->project($month, $pgcc, $balance, $knownAt, $principalPolicy);
                } else {
                    $debtProjection = (new DereDebtNoMovementXmlProjector)->project($month, $pgcc, $balance, $knownAt, $debtPolicy);
                }
                if ($debtProjection['status'] !== 'PROJECTED') {
                    return $debtProjection;
                }
            } elseif (isset($month['debt_no_movement']) || isset($month['debt_principal_movement']) || isset($month['debt_yield'])) {
                return $this->stop('D2101_UNEXPECTED_DECLARATION');
            }
        }
        $base = $operation['base'];
        if (DecimalValue::fromString($base['net_base'])->isNegative()) {
            return $this->stop('NEGATIVE_BASE_ACCOUNTING_PENDING');
        }
        $hasDeductions = DecimalValue::fromString($base['deductions_before_art188'])->compareTo(DecimalValue::fromString('0.00')) > 0;
        $deductionOperation = $operation;
        $binding = null;
        if (($rule['pgcc_deduction_binding_required'] ?? false) === true) {
            if ($pgcc === null) {
                return $this->stop('DEDUCTION_PGCC_POLICY_CONFLICT');
            }
            $binding = (new DerePgccDeductionBindingQualifier)->resolve($operation, $month, $pgcc, $knownAt, $rule);
            if ($binding['status'] !== 'RESOLVED') {
                return $binding;
            }
            $deductionOperation = $binding['operation'];
            $hasDeductions = DecimalValue::fromString($binding['reportable_amount'])->compareTo(DecimalValue::fromString('0.00')) > 0;
        }
        $deductions = null;
        if ($hasDeductions) {
            if (! is_array($month['deductions'] ?? null)) {
                return $this->stop('DEDUCTIONS_REQUIRED');
            }
            if (($month['deductions']['supplier_id'] ?? null) !== $book['supplier_id']
                || ($month['deductions']['document_id'] ?? null) !== $book['document_id']
                || ($month['deductions']['period_end'] ?? null) !== $book['period_end']) {
                return $this->stop('DEDUCTIONS_AND_BALANCE_SCOPE_CONFLICT');
            }
            $deductions = (new DereDeductionXmlProjector)->project($deductionOperation, $month['deductions'], $identity, $knownAt, $rules['deductions'] ?? [], $binding);
            if ($deductions['status'] !== 'PROJECTED') {
                return $deductions;
            }
        } elseif (isset($month['deductions'])) {
            return $this->stop('UNEXPECTED_DEDUCTION_DETAIL');
        }
        $supplier = $book['supplier_id'];
        $time = new DateTimeImmutable($identity['generated_at']);
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtFechMensal/v0_0_2', 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtFechMensal');
        $event->setAttribute('id', 'DeRE11991'.$supplier.$time->format('YmdHis').str_pad((string) $identity['event_sequence'], 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($supplier, 0, 8));
        $this->node($xml, $this->node($xml, $event, 'idePeriodo'), 'perApur', $balance['balance']['period']);
        if (! $hasDeductions && $prerequisites['D1121_required']) {
            $info = $this->node($xml, $event, 'infoFechamento');
            $this->node($xml, $this->node($xml, $info, 'infoParamFech'), 'indInexistDedu', '1');
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_MONTHLY_LOCAL_CLOSING_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'schema' => 'evtFechMensal-v0_0_2.xsd', 'balance' => $balance, 'deductions' => $deductions,
            'pgcc_source_hash' => $book['pgcc_source_hash'], ...($pgcc === null ? [] : ['pgcc_qualification' => $pgcc]), 'prerequisites' => $prerequisites,
            ...($binding === null ? [] : ['deduction_pgcc_binding' => array_diff_key($binding, ['operation' => true])]),
            ...($securitiesBinding === null ? [] : ['securities_realization_binding' => $securitiesBinding]),
            ...($debtProjection === null ? [] : [(isset($month['debt_yield']) ? 'debt_yield_projection' : (isset($month['debt_principal_movement']) ? 'debt_principal_movement_projection' : 'debt_no_movement_projection')) => $debtProjection]),
            ...(($rule['pgcc_qualification_policy']['official_catalogs_required'] ?? false) !== true ? [] : [
                'deduction_event_required' => $prerequisites['D1121_required'],
                'deduction_event_policy' => 'OFFICIAL_RN_CODTRIB_LIST_NOT_PRESENCE_OF_ANY_DEDUCTION',
            ]),
            ...($pgccProjection === null ? [] : ['pgcc_projection' => $pgccProjection]),
            ...($contributorProjection === null ? [] : ['contributor_projection' => $contributorProjection]),
            'scope' => 'LOCAL_UNSIGNED_MONTHLY_PREREQUISITES_NO_GOVERNMENT_RECEPTION_OR_FULL_PGCC_APPROVAL',
            'production_ready' => false, 'period_closed' => false, 'signature_created' => false, 'transmission_performed' => false];
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private function node(DOMDocument $xml, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $xml->createElementNS($parent->namespaceURI, $name);
        if ($value !== null) {
            $element->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_MONTHLY_'.$reason, 'period_closed' => false, 'production_ready' => false];
    }
}
