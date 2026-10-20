<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\FinancialPublicOfferDebtQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Reconcile art195 entries once, in D2101, while retaining gross accounting movements in D1101. */
final readonly class DereDebtYieldXmlProjector
{
    public const CONTRACT = 'DERE-D2101-CONSTANT-COST-YIELD-LOCAL-2026.10.06-1';

    public const DAILY_CONTRACT = 'DERE-D2101-DAILY-MEMORY-YIELD-LOCAL-2026.10.06-1';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $month
     * @param  array<string, mixed>  $pgcc
     * @param  array<string, mixed>  $balance
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function project(array $operation, array $month, array $pgcc, array $balance, DateTimeImmutable $knownAt, array $rule): array
    {
        if (! in_array($rule['contract'] ?? null, [self::CONTRACT, self::DAILY_CONTRACT], true) || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! is_array($rule['qualification'] ?? null) || ($rule['allowed_codes'] ?? null) !== ['110113001', '110113002']) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        $book = $month['book'] ?? null;
        $detail = $month['debt_yield'] ?? null;
        $base = $operation['base'] ?? null;
        if (! is_array($book) || ! is_array($detail) || ! is_array($base) || ($base['status'] ?? null) !== 'RESOLVED'
            || ($pgcc['status'] ?? null) !== 'RESOLVED' || ($balance['status'] ?? null) !== 'PROJECTED'
            || ($balance['balance']['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($book['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($base['supplier_id'] ?? null) !== $book['supplier_id'] || ($base['period_end'] ?? null) !== $book['period_end']
            || ($balance['balance']['supplier_id'] ?? null) !== $book['supplier_id']
            || ($balance['balance']['book_source_hash'] ?? null) !== $book['book_source_hash']
            || ! in_array('D2101', $pgcc['required_auxiliary_events'] ?? [], true)) {
            return $this->stop('QUALIFIED_CONTEXT_UNPROVEN');
        }
        if (isset($month['debt_no_movement']) || isset($month['debt_principal_movement']) || isset($detail['semTitulos'])
            || ($detail['supplier_id'] ?? null) !== $book['supplier_id'] || ($detail['period_end'] ?? null) !== $book['period_end']
            || ! is_array($detail['financial_entry_ids'] ?? null) || ! array_is_list($detail['financial_entry_ids'])
            || ! is_array($detail['previous_title_balances'] ?? null) || ! in_array('03A', $month['contributor']['servFinanc']['tpAtividades'] ?? [], true)) {
            return $this->stop('YIELD_SCOPE_OR_ACTIVITIES_UNPROVEN');
        }
        try {
            $end = LocalDate::fromString($book['period_end'])->value();
            $start = substr($end, 0, 7).'-01';
            $observed = LocalDate::fromString((string) ($detail['observed_on'] ?? ''))->value();
            if ($observed < $end || $observed > $knownAt->format('Y-m-d') || ($balance['balance']['period'] ?? null) !== substr($end, 0, 7)) {
                return $this->stop('YIELD_OBSERVATION_CONFLICT');
            }
            $plans = array_column($pgcc['accounts'], null, 'cCta');
            $accounts = array_column($book['accounts'], null, 'cCta');
            $entries = array_column($base['entries'], null, 'id');
            $qualified = [];
            $byAsset = $byResult = [];
            $sum = DecimalValue::fromString('0.00');
            $requiredPriors = [];
            $qualifier = new FinancialPublicOfferDebtQualifier;
            foreach ($entries as $id => $entry) {
                if (($entry['fiscal_origin'] ?? null) !== 'D2101') {
                    continue;
                }
                $q = $qualifier->resolve($entry, $base, $knownAt, $rule['qualification']);
                if ($q['status'] !== 'RESOLVED') {
                    return $q;
                }
                if (CanonicalJson::encode($q) !== CanonicalJson::encode($entry['d2101_binding'] ?? null)
                    || ($entry['computed_amount'] ?? null) !== $q['taxable_amount'] || ($entry['book_apur_amount'] ?? null) !== '0.00') {
                    return $this->stop('YIELD_ENGINE_BINDING_CONFLICT');
                }
                $t = $q['xml_title'];
                $plan = $plans[$t['cCta']] ?? [];
                $resultPlan = $plans[$q['result_account_code']] ?? [];
                if (($plan['indCta'] ?? null) !== 'A' || ($plan['codTrib'] ?? null) !== $q['expected_codTrib']
                    || ($plan['codNat'] ?? null) !== '1' || ($plan['natCta'] ?? null) !== 'D'
                    || ($resultPlan['indCta'] ?? null) !== 'A' || ($resultPlan['codNat'] ?? null) !== '4'
                    || ($resultPlan['natCta'] ?? null) !== 'C') {
                    return $this->stop('YIELD_PGCC_OR_ROUTE_CONFLICT');
                }
                $priorMonth = (new DateTimeImmutable($start))->modify('-1 month')->format('Y-m');
                $prior = $detail['previous_title_balances'][$id] ?? null;
                $first = ($q['first_acquisition'] ?? false) === true;
                $value = ['supplier_id' => $book['supplier_id'], 'period' => $priorMonth, 'idTitulo' => $t['idTitulo'],
                    'cCta' => $t['cCta'], 'vSaldoContFin' => $t['vSaldoContIni']];
                if ($first ? $prior !== null || $t['vSaldoContIni'] !== '0.00'
                    : ! $qualifier->proof($prior, $value, $book['supplier_id'], $priorMonth.'-01',
                        (new DateTimeImmutable($priorMonth.'-01'))->format('Y-m-t'), $knownAt) || $prior['observed_on'] > $observed) {
                    return $this->stop('YIELD_PREVIOUS_TITLE_BALANCE_UNPROVEN');
                }
                if (! $first) {
                    $requiredPriors[] = $id;
                }
                $qualified[$id] = $q;
                $byAsset[$t['cCta']][] = $q;
                $byResult[$q['result_account_code']][] = $id;
                $sum = $sum->plus(DecimalValue::fromString($q['taxable_amount']));
            }
            $ids = array_keys($qualified);
            sort($ids);
            $declared = $detail['financial_entry_ids'];
            if (! array_all($declared, static fn (mixed $id): bool => is_string($id))) {
                return $this->stop('YIELD_ENTRY_COVERAGE_CONFLICT');
            }
            sort($declared);
            $priors = array_keys($detail['previous_title_balances']);
            sort($priors);
            sort($requiredPriors);
            if ($ids === [] || count($ids) > 10000 || $ids !== $declared || $requiredPriors !== $priors) {
                return $this->stop('YIELD_ENTRY_COVERAGE_CONFLICT');
            }
            foreach ($plans as $code => $plan) {
                if ($plan['indCta'] !== 'A' || ! in_array($plan['codTrib'], $rule['allowed_codes'], true)) {
                    continue;
                }
                $a = $accounts[$code] ?? [];
                if (($a['financial_entry_ids'] ?? null) !== [] || ($a['vApur'] ?? null) !== '0.00'
                    || ($a['natSaldoInic'] ?? null) !== 'D' || ($a['natSaldoFinal'] ?? null) !== 'D') {
                    return $this->stop('YIELD_ASSET_FISCAL_SEGREGATION_UNPROVEN');
                }
                $totals = array_fill_keys(['vSaldoInic', 'vSaldoFinal', 'vMovDebt', 'vMovCred'], DecimalValue::fromString('0.00'));
                foreach ($byAsset[$code] ?? [] as $q) {
                    $t = $q['xml_title'];
                    $variation = DecimalValue::fromString($t['vVarMensal']);
                    $totals['vSaldoInic'] = $totals['vSaldoInic']->plus(DecimalValue::fromString($t['vSaldoContIni']));
                    $totals['vSaldoFinal'] = $totals['vSaldoFinal']->plus(DecimalValue::fromString($t['vSaldoContFin']));
                    if (isset($q['asset_movements'])) {
                        $totals['vMovDebt'] = $totals['vMovDebt']->plus(DecimalValue::fromString($q['asset_movements']['vMovDebt']));
                        $totals['vMovCred'] = $totals['vMovCred']->plus(DecimalValue::fromString($q['asset_movements']['vMovCred']));

                        continue;
                    }
                    $totals['vMovCred'] = $totals['vMovCred']->plus(DecimalValue::fromString($t['vJurosRec']));
                    $field = $variation->isNegative() ? 'vMovCred' : 'vMovDebt';
                    $totals[$field] = $totals[$field]->plus($variation->isNegative() ? $variation->multipliedBy(DecimalValue::fromString('-1')) : $variation);
                }
                foreach ($totals as $field => $amount) {
                    if ($amount->compareTo(DecimalValue::fromString((string) ($a[$field] ?? 'invalid'))) !== 0) {
                        return $this->stop('YIELD_ASSET_BALANCE_RECONCILIATION_CONFLICT');
                    }
                }
            }
            foreach ($byResult as $code => $originIds) {
                $a = $accounts[$code] ?? [];
                $credit = $debit = $excludedCredit = DecimalValue::fromString('0.00');
                $found = [];
                foreach ($a['financial_entry_ids'] ?? [] as $id) {
                    $e = $entries[$id] ?? null;
                    if (! is_array($e) || ($e['bucket'] ?? null) !== 'REVENUE') {
                        return $this->stop('YIELD_RESULT_ACCOUNT_LINK_CONFLICT');
                    }
                    if (isset($qualified[$id])) {
                        $q = $qualified[$id];
                        if ($q['result_account_code'] !== $code) {
                            return $this->stop('YIELD_RESULT_ACCOUNT_LINK_CONFLICT');
                        }
                        $amount = DecimalValue::fromString($q['gross_amount']);
                        if (isset($q['gross_result_credit'], $q['gross_result_debit'])) {
                            $positive = DecimalValue::fromString($q['gross_result_credit']);
                            $credit = $credit->plus($positive);
                            $excludedCredit = $excludedCredit->plus($positive);
                            $debit = $debit->plus(DecimalValue::fromString($q['gross_result_debit']));
                        } elseif ($q['gross_nature'] === 'C') {
                            $credit = $credit->plus($amount);
                            $excludedCredit = $excludedCredit->plus($amount);
                        } else {
                            $debit = $debit->plus($amount);
                        }
                        $found[] = $id;
                    } else {
                        $credit = $credit->plus(DecimalValue::fromString($e['computed_amount']));
                    }
                }
                sort($found);
                sort($originIds);
                if ($found !== $originIds || ($a['natVApur'] ?? null) !== 'C'
                    || $credit->compareTo(DecimalValue::fromString((string) ($a['vMovCred'] ?? 'invalid'))) !== 0
                    || $debit->compareTo(DecimalValue::fromString((string) ($a['vMovDebt'] ?? 'invalid'))) !== 0
                    || $excludedCredit->compareTo(DecimalValue::fromString((string) ($a['vAjusteCred'] ?? '0.00'))) !== 0
                    || ($a['vAjusteDebt'] ?? '0.00') !== '0.00') {
                    return $this->stop('YIELD_RESULT_GROSS_OR_DUPLICATE_BASE_CONFLICT');
                }
            }
            $value = ['supplier_id' => $book['supplier_id'], 'period_end' => $end, 'detail' => array_diff_key($detail, ['evidence' => true]),
                'operation_hash' => CanonicalJson::hash($operation), 'book_input_hash' => CanonicalJson::hash($book), 'pgcc_source_hash' => $pgcc['source_hash']];
            if (! $qualifier->proof($detail['evidence'] ?? null, $value, $book['supplier_id'], $start, $end, $knownAt)
                || $detail['evidence']['observed_on'] > $observed) {
                return $this->stop('YIELD_COMPLETE_SCOPE_EVIDENCE_UNPROVEN');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('YIELD_AMOUNT_OR_DATE_INVALID');
        }
        $identity = $month['identity'] ?? [];
        $generated = $identity['generated_at'] ?? null;
        $sequence = $identity['event_sequence'] ?? null;
        $time = is_string($generated) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated) : false;
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) < $observed || substr($generated, 0, 10) <= $end
            || ! is_int($sequence) || $sequence < 1 || $sequence > 99999) {
            return $this->stop('YIELD_GENERATION_IDENTITY_CONFLICT');
        }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3', 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtDebOpOfPublic');
        $event->setAttribute('id', 'DeRE21011'.$book['supplier_id'].$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $v) {
            $this->node($xml, $header, $name, $v);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($book['supplier_id'], 0, 8));
        $this->node($xml, $this->node($xml, $event, 'idePeriodo'), 'perApur', substr($end, 0, 7));
        $info = $this->node($xml, $this->node($xml, $event, 'infoDebOpOfPublic'), 'infoTitulos');
        $titles = array_column($qualified, 'xml_title');
        usort($titles, static fn (array $a, array $b): int => [$a['cCta'], $a['idTitulo']] <=> [$b['cCta'], $b['idTitulo']]);
        foreach ($titles as $t) {
            $row = $this->node($xml, $info, 'detTitulo');
            $row->setAttribute('idTitulo', $t['idTitulo']);
            $row->setAttribute('cCta', $t['cCta']);
            foreach (array_diff_key($t, ['idTitulo' => true, 'cCta' => true]) as $field => $v) {
                $this->node($xml, $row, $field, $v);
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_D2101_YIELD_PROJECTED', 'contract' => $rule['contract'],
            'xml' => $xml->saveXML(), 'schema' => 'evtDebOpOfPublic-v0_0_3.xsd', 'title_count' => count($titles), 'total_vApur' => $sum->value(),
            'financial_entry_ids' => $ids, 'qualified_entries' => $qualified, 'book_input_hash' => CanonicalJson::hash($book),
            'pgcc_source_hash' => $pgcc['source_hash'], 'evidence_hash' => $detail['evidence']['source_hash'],
            'scope' => $rule['contract'] === self::DAILY_CONTRACT ? 'ART195_DAILY_MEMORY_YIELD_ONCE_IN_D2101_GROSS_MOVEMENTS_RECONCILED'
                : 'ART195_STATIC_COST_YIELD_ONCE_IN_D2101_GROSS_RESULT_EXCLUDED_FROM_D1101_BASE',
            'production_ready' => false, 'signature_created' => false, 'transmission_performed' => false, 'tax_totalization_performed' => false];
    }

    private function node(DOMDocument $xml, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $xml->createElementNS($parent->namespaceURI, $name);
        if ($value !== null) {
            $node->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D2101_'.$reason, 'production_ready' => false];
    }
}
