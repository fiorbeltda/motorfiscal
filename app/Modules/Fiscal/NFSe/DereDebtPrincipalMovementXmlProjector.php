<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Original D2101 for proven principal-only movements; taxable yields remain outside this contract. */
final readonly class DereDebtPrincipalMovementXmlProjector
{
    public const CONTRACT = 'DERE-D2101-PRINCIPAL-MOVEMENT-LOCAL-2026.10.06-1';

    /** @param array<string, mixed> $month
     * @param  array<string, mixed>  $pgcc
     * @param  array<string, mixed>  $balance
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function project(array $month, array $pgcc, array $balance, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null) || ($rule['allowed_codes'] ?? null) !== ['110113001', '110113002']) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        $book = $month['book'] ?? null;
        $detail = $month['debt_principal_movement'] ?? null;
        if (($pgcc['status'] ?? null) !== 'RESOLVED' || ($balance['status'] ?? null) !== 'PROJECTED' || ! is_array($book)
            || ($balance['balance']['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($book['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($balance['balance']['book_source_hash'] ?? null) !== ($book['book_source_hash'] ?? null)
            || ($balance['balance']['supplier_id'] ?? null) !== ($book['supplier_id'] ?? null)
            || ! in_array('D2101', $pgcc['required_auxiliary_events'] ?? [], true)) {
            return $this->stop('QUALIFIED_CONTEXT_UNPROVEN');
        }
        if (! is_array($detail) || isset($detail['semTitulos']) || isset($month['debt_no_movement'])
            || ($detail['supplier_id'] ?? null) !== $book['supplier_id'] || ($detail['period_end'] ?? null) !== $book['period_end']
            || ! is_array($detail['titles'] ?? null) || ! array_is_list($detail['titles'])
            || $detail['titles'] === [] || count($detail['titles']) > 10000) {
            return $this->stop('TITLE_SCOPE_OR_DETAIL_INVALID');
        }
        try {
            $end = LocalDate::fromString($book['period_end'])->value();
            $start = substr($end, 0, 7).'-01';
            $observed = LocalDate::fromString((string) ($detail['observed_on'] ?? ''))->value();
            if ($end !== (new DateTimeImmutable($end))->format('Y-m-t') || $observed < $end || $observed > $knownAt->format('Y-m-d')
                || ($balance['balance']['period'] ?? null) !== substr($end, 0, 7)) {
                return $this->stop('EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT');
            }
            $accounts = [];
            foreach ($pgcc['accounts'] as $a) {
                if ($a['indCta'] === 'A' && in_array($a['codTrib'], $rule['allowed_codes'], true)) {
                    $accounts[$a['cCta']] = $a['codTrib'];
                }
            }
            if ($accounts === []) {
                return $this->stop('APPLICABLE_ACCOUNTS_UNPROVEN');
            }
            $totals = [];
            $seen = [];
            $issuers = [];
            $titles = [];
            foreach ($detail['titles'] as $t) {
                if (! is_array($t) || ! $this->isin($t['idTitulo'] ?? null)
                    || ! is_string($t['cCta'] ?? null) || ! isset($accounts[$t['cCta']])
                    || isset($seen[$t['cCta'].'|'.$t['idTitulo']]) || ! $this->cnpj($t['CNPJDevedor'] ?? null)) {
                    return $this->stop('TITLE_ID_ACCOUNT_OR_ISSUER_INVALID');
                }
                $seen[$t['cCta'].'|'.$t['idTitulo']] = true;
                if (isset($issuers[$t['idTitulo']]) && $issuers[$t['idTitulo']] !== $t['CNPJDevedor']) {
                    return $this->stop('ISIN_ISSUER_CONFLICT');
                }
                $issuers[$t['idTitulo']] = $t['CNPJDevedor'];
                if ($accounts[$t['cCta']] === '110113002') {
                    if (! is_string($t['descFundo'] ?? null) || trim($t['descFundo']) !== $t['descFundo']
                        || $t['descFundo'] === '' || mb_strlen($t['descFundo']) > 255 || ($t['exclusive_fund'] ?? null) !== true
                        || ! is_string($t['public_offer_portfolio_percent'] ?? null)
                        || DecimalValue::fromString($t['public_offer_portfolio_percent'])->compareTo(DecimalValue::fromString('95')) < 0
                        || DecimalValue::fromString($t['public_offer_portfolio_percent'])->compareTo(DecimalValue::fromString('100')) > 0) {
                        return $this->stop('EXCLUSIVE_FUND_95_PERCENT_UNPROVEN');
                    }
                } elseif (isset($t['exclusive_fund']) || isset($t['descFundo']) || isset($t['public_offer_portfolio_percent'])) {
                    return $this->stop('DIRECT_TITLE_FUND_CONFLICT');
                }
                if (($t['instrument_type'] ?? null) !== 'CORPORATE_DEBT_PUBLIC_OFFER' || ($t['public_offer_regulator'] ?? null) !== 'CVM') {
                    return $this->stop('PUBLIC_OFFER_ELIGIBILITY_UNPROVEN');
                }
                foreach (['vJurosRec', 'vVarMensal', 'vPisCofins', 'vApur'] as $f) {
                    if (($t[$f] ?? (in_array($f, ['vVarMensal', 'vPisCofins'], true) ? '0.00' : null)) !== '0.00') {
                        return $this->stop('YIELD_TAX_INTEGRATION_PENDING');
                    }
                }
                $opening = $this->money($t['vSaldoContIni'] ?? null);
                $entry = $this->money($t['vEntradas'] ?? null);
                $exit = $this->money($t['vSaidas'] ?? null);
                $closing = $this->money($t['vSaldoContFin'] ?? null);
                $this->money($t['vSelic'] ?? null);
                if ($opening->plus($entry)->minus($exit)->compareTo($closing) !== 0) {
                    return $this->stop('TITLE_BALANCE_CONFLICT');
                }
                if (($t['first_acquisition_period'] ?? null) === true) {
                    if ($t['vSaldoContIni'] !== '0.00' || isset($t['previous_title_balance'])
                        || $entry->compareTo(DecimalValue::fromString('0.00')) <= 0) {
                        return $this->stop('FIRST_ACQUISITION_CONFLICT');
                    }
                } elseif (($t['first_acquisition_period'] ?? null) === false) {
                    $prior = $t['previous_title_balance'] ?? null;
                    $priorMonth = (new DateTimeImmutable($start))->modify('-1 month')->format('Y-m');
                    $expected = ['supplier_id' => $book['supplier_id'], 'period' => $priorMonth, 'idTitulo' => $t['idTitulo'],
                        'cCta' => $t['cCta'], 'vSaldoContFin' => $t['vSaldoContIni']];
                    if (! $this->proof($prior, $expected, $book['supplier_id'], $priorMonth.'-01',
                        (new DateTimeImmutable($priorMonth.'-01'))->format('Y-m-t'), $observed)) {
                        return $this->stop('PREVIOUS_TITLE_BALANCE_UNPROVEN');
                    }
                } else {
                    return $this->stop('FIRST_ACQUISITION_STATUS_UNPROVEN');
                }
                $titleValue = array_diff_key($t, ['eligibility_evidence' => true, 'selic_memory_evidence' => true, 'previous_title_balance' => true]);
                $expected = ['supplier_id' => $book['supplier_id'], 'period_end' => $end, 'title' => $titleValue];
                if (! $this->proof($t['eligibility_evidence'] ?? null, $expected, $book['supplier_id'], $start, $end, $observed)) {
                    return $this->stop('PUBLIC_OFFER_EVIDENCE_UNPROVEN');
                }
                $memory = $t['selic_memory_evidence'] ?? null;
                $expected = ['supplier_id' => $book['supplier_id'], 'period_end' => $end, 'idTitulo' => $t['idTitulo'],
                    'cCta' => $t['cCta'], 'vSelic' => $t['vSelic'], 'method' => 'DAILY_SELIC_WEIGHTED_AVERAGE_COST',
                    'title_input_hash' => CanonicalJson::hash($titleValue), 'daily_memory_source_hash' => $memory['value']['daily_memory_source_hash'] ?? null];
                if (! $this->hash($expected['daily_memory_source_hash'])
                    || ! $this->proof($memory, $expected, $book['supplier_id'], $start, $end, $observed)) {
                    return $this->stop('SELIC_DAILY_MEMORY_UNPROVEN');
                }
                $totals[$t['cCta']] ??= ['opening' => DecimalValue::fromString('0.00'), 'entry' => DecimalValue::fromString('0.00'),
                    'exit' => DecimalValue::fromString('0.00'), 'closing' => DecimalValue::fromString('0.00')];
                foreach (['opening' => $opening, 'entry' => $entry, 'exit' => $exit, 'closing' => $closing] as $f => $v) {
                    $totals[$t['cCta']][$f] = $totals[$t['cCta']][$f]->plus($v);
                }
                $titles[] = $t;
            }
            $bookAccounts = array_column($book['accounts'], null, 'cCta');
            foreach ($accounts as $code => $taxCode) {
                $a = $bookAccounts[$code] ?? null;
                if (! is_array($a) || ($a['financial_entry_ids'] ?? null) !== [] || ($a['vApur'] ?? null) !== '0.00'
                    || ($a['natSaldoInic'] ?? null) !== 'D' || ($a['natSaldoFinal'] ?? null) !== 'D') {
                    return $this->stop('PRINCIPAL_LEDGER_SEGREGATION_UNPROVEN');
                }
                foreach (['opening' => 'vSaldoInic', 'entry' => 'vMovDebt', 'exit' => 'vMovCred', 'closing' => 'vSaldoFinal'] as $f => $field) {
                    $v = $totals[$code][$f] ?? DecimalValue::fromString('0.00');
                    if ($v->compareTo($this->money($a[$field] ?? null)) !== 0) {
                        return $this->stop('TITLE_ACCOUNT_RECONCILIATION_CONFLICT');
                    }
                }
            }
            $expected = ['supplier_id' => $book['supplier_id'], 'period_end' => $end, 'titles' => $detail['titles'],
                'book_input_hash' => CanonicalJson::hash($book), 'pgcc_source_hash' => $pgcc['source_hash'],
                'principal_only_complete' => true, 'income_or_disposal_gain' => '0.00'];
            if (! $this->proof($detail['evidence'] ?? null, $expected, $book['supplier_id'], $start, $end, $observed)) {
                return $this->stop('COMPLETE_PRINCIPAL_SCOPE_UNPROVEN');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
        $identity = $month['identity'] ?? [];
        $generated = $identity['generated_at'] ?? null;
        $sequence = $identity['event_sequence'] ?? null;
        if (! is_string($generated) || ! is_int($sequence) || $sequence < 1 || $sequence > 99999
            || ! is_string($book['supplier_id']) || ! $this->cnpj($book['supplier_id'])) {
            return $this->stop('EVENT_IDENTITY_INVALID');
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) < $observed || substr($generated, 0, 10) <= $end) {
            return $this->stop('GENERATION_DATE_CONFLICT');
        }
        usort($titles, static fn (array $a, array $b): int => [$a['cCta'], $a['idTitulo']] <=> [$b['cCta'], $b['idTitulo']]);
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3', 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtDebOpOfPublic');
        $event->setAttribute('id', 'DeRE21011'.$book['supplier_id'].$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($book['supplier_id'], 0, 8));
        $this->node($xml, $this->node($xml, $event, 'idePeriodo'), 'perApur', substr($end, 0, 7));
        $info = $this->node($xml, $this->node($xml, $event, 'infoDebOpOfPublic'), 'infoTitulos');
        foreach ($titles as $t) {
            $row = $this->node($xml, $info, 'detTitulo');
            $row->setAttribute('idTitulo', $t['idTitulo']);
            $row->setAttribute('cCta', $t['cCta']);
            foreach (['descFundo', 'CNPJDevedor', 'vSaldoContIni', 'vEntradas', 'vSaidas', 'vJurosRec', 'vVarMensal', 'vSaldoContFin', 'vSelic', 'vPisCofins', 'vApur'] as $f) {
                if (isset($t[$f])) {
                    $this->node($xml, $row, $f, $t[$f]);
                }
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_D2101_PRINCIPAL_MOVEMENT_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'schema' => 'evtDebOpOfPublic-v0_0_3.xsd', 'title_count' => count($titles), 'total_vApur' => '0.00',
            'book_input_hash' => CanonicalJson::hash($book), 'pgcc_source_hash' => $pgcc['source_hash'], 'evidence_hash' => $detail['evidence']['source_hash'],
            'source_hash' => $rule['source_hash'], 'scope' => 'PRINCIPAL_ONLY_NO_YIELD_OR_DISPOSAL_GAIN_EXTERNAL_QUALIFIED_SELIC_MEMORY',
            'selic_calculation_performed' => false, 'tax_totalization_performed' => false,
            'production_ready' => false, 'signature_created' => false, 'transmission_performed' => false];
    }

    private function money(mixed $v): DecimalValue
    {
        if (! is_string($v) || preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $v) !== 1) {
            throw new InvalidArgumentException('Canonical nonnegative money required.');
        }

        return DecimalValue::fromString($v);
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function proof(mixed $p, array $expected, string $supplier, string $start, string $end, string $observed): bool
    {
        return is_array($p) && ($p['qualified'] ?? null) === true && ($p['supplier_id'] ?? null) === $supplier
            && is_string($p['evidence_id'] ?? null) && trim($p['evidence_id']) !== '' && $this->hash($p['source_hash'] ?? null)
            && $p['source_hash'] === CanonicalJson::hash(array_diff_key($p, ['source_hash' => true]))
            && CanonicalJson::encode($p['value'] ?? null) === CanonicalJson::encode($expected)
            && LocalDate::fromString((string) ($p['valid_from'] ?? ''))->value() <= $start
            && LocalDate::fromString((string) ($p['valid_to'] ?? ''))->value() >= $end
            && LocalDate::fromString((string) ($p['observed_on'] ?? ''))->value() >= $end
            && $p['observed_on'] <= $observed;
    }

    private function hash(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[a-f0-9]{64}$/', $v) === 1;
    }

    private function isin(mixed $v): bool
    {
        if (! is_string($v) || preg_match('/^[A-Z]{2}[0-9A-Z]{9}[0-9]$/', $v) !== 1) {
            return false;
        }
        $digits = '';
        foreach (str_split($v) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }
        $sum = 0;
        foreach (str_split(strrev($digits)) as $i => $char) {
            $number = (int) $char * ($i % 2 === 1 ? 2 : 1);
            $sum += intdiv($number, 10) + $number % 10;
        }

        return $sum % 10 === 0;
    }

    private function cnpj(mixed $v): bool
    {
        if (! is_string($v) || preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $v) !== 1 || preg_match('/^(.)\1{13}$/', $v) === 1) {
            return false;
        }
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (ord($v[$i]) - 48) * $weight;
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $v[count($weights)] !== $digit) {
                return false;
            }
        }

        return true;
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
