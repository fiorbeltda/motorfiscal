<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Original D2101 only for independently proven empty, unmoved analytical accounts. */
final readonly class DereDebtNoMovementXmlProjector
{
    public const CONTRACT = 'DERE-D2101-NO-MOVEMENT-LOCAL-2026.10.06-1';

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
        if (($pgcc['status'] ?? null) !== 'RESOLVED' || ($balance['status'] ?? null) !== 'PROJECTED' || ! is_array($book)
            || ($balance['balance']['supplier_id'] ?? null) !== ($book['supplier_id'] ?? null)
            || ($balance['balance']['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($book['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ($balance['balance']['book_source_hash'] ?? null) !== ($book['book_source_hash'] ?? null)
            || ! in_array('D2101', $pgcc['required_auxiliary_events'] ?? [], true)) {
            return $this->stop('QUALIFIED_CONTEXT_UNPROVEN');
        }
        $codes = [];
        foreach ($pgcc['accounts'] as $account) {
            if ($account['indCta'] === 'A' && in_array($account['codTrib'], $rule['allowed_codes'], true)) {
                $codes[] = $account['cCta'];
            }
        }
        sort($codes);
        if ($codes === [] || count(array_unique($codes)) !== count($codes)) {
            return $this->stop('APPLICABLE_ACCOUNTS_UNPROVEN');
        }
        $seen = [];
        foreach ($book['accounts'] as $account) {
            if (! in_array($account['cCta'], $codes, true)) {
                continue;
            }
            if (isset($seen[$account['cCta']])) {
                return $this->stop('ACCOUNT_DUPLICATE');
            }
            $seen[$account['cCta']] = true;
            foreach (['vSaldoInic', 'vMovDebt', 'vMovCred', 'vSaldoFinal', 'vApur', 'vAjusteDebt', 'vAjusteCred'] as $field) {
                $amount = $account[$field] ?? (in_array($field, ['vAjusteDebt', 'vAjusteCred'], true) ? '0.00' : null);
                if ($amount !== '0.00') {
                    return $this->stop('NONZERO_ACCOUNT_DETAIL_REQUIRED');
                }
            }
            if (($account['financial_entry_ids'] ?? null) !== []) {
                return $this->stop('ACCOUNT_ENTRY_DETAIL_REQUIRED');
            }
        }
        if (count($seen) !== count($codes)) {
            return $this->stop('ACCOUNT_COVERAGE_INCOMPLETE');
        }
        $detail = $month['debt_no_movement'] ?? null;
        if (! is_array($detail) || ($detail['semTitulos'] ?? null) !== '1' || ($detail['titles'] ?? null) !== []) {
            return $this->stop('NO_MOVEMENT_DECLARATION_REQUIRED');
        }
        $declared = $detail['account_codes'] ?? null;
        if (! is_array($declared) || ! array_is_list($declared) || ! array_all($declared, static fn (mixed $value): bool => is_string($value))) {
            return $this->stop('ACCOUNT_SCOPE_CONFLICT');
        }
        sort($declared);
        if ($declared !== $codes || ($detail['supplier_id'] ?? null) !== $book['supplier_id']
            || ($detail['period_end'] ?? null) !== $book['period_end']) {
            return $this->stop('ACCOUNT_SCOPE_CONFLICT');
        }
        $proof = $detail['evidence'] ?? null;
        $expected = ['supplier_id' => $book['supplier_id'], 'period_end' => $book['period_end'], 'semTitulos' => '1',
            'account_codes' => $codes, 'titles' => [], 'book_input_hash' => CanonicalJson::hash($book),
            'pgcc_source_hash' => $pgcc['source_hash']];
        if (! is_array($proof) || ($proof['qualified'] ?? null) !== true || ($proof['supplier_id'] ?? null) !== $book['supplier_id']
            || ! is_string($proof['evidence_id'] ?? null) || trim($proof['evidence_id']) === ''
            || ! $this->hash($proof['source_hash'] ?? null)
            || $proof['source_hash'] !== CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))
            || CanonicalJson::encode($proof['value'] ?? null) !== CanonicalJson::encode($expected)) {
            return $this->stop('NO_MOVEMENT_EVIDENCE_UNPROVEN');
        }
        try {
            $end = LocalDate::fromString($book['period_end'])->value();
            $observed = LocalDate::fromString((string) ($detail['observed_on'] ?? ''))->value();
            $proofObserved = LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value();
            if ($end !== (new DateTimeImmutable($end))->format('Y-m-t') || $observed < $end || $observed > $knownAt->format('Y-m-d')
                || $proofObserved < $end || $proofObserved > $observed
                || LocalDate::fromString((string) ($proof['valid_from'] ?? ''))->value() > substr($end, 0, 7).'-01'
                || LocalDate::fromString((string) ($proof['valid_to'] ?? ''))->value() < $end
                || ($balance['balance']['period'] ?? null) !== substr($end, 0, 7)) {
                return $this->stop('EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('EVIDENCE_PERIOD_OR_OBSERVATION_CONFLICT');
        }
        $identity = $month['identity'] ?? [];
        $generated = $identity['generated_at'] ?? null;
        $sequence = $identity['event_sequence'] ?? null;
        if (! is_string($generated) || ! is_int($sequence) || $sequence < 1 || $sequence > 99999
            || ! is_string($book['supplier_id']) || preg_match('/^[0-9A-Z]{14}$/', $book['supplier_id']) !== 1) {
            return $this->stop('EVENT_IDENTITY_INVALID');
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) < $observed || substr($generated, 0, 10) <= $end) {
            return $this->stop('GENERATION_DATE_CONFLICT');
        }
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
        $this->node($xml, $this->node($xml, $event, 'infoDebOpOfPublic'), 'semTitulos', '1');

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_D2101_NO_MOVEMENT_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'schema' => 'evtDebOpOfPublic-v0_0_3.xsd', 'account_codes' => $codes,
            'book_input_hash' => CanonicalJson::hash($book), 'pgcc_source_hash' => $pgcc['source_hash'],
            'evidence_hash' => $proof['source_hash'], 'source_hash' => $rule['source_hash'],
            'scope' => 'EMPTY_UNMOVED_ACCOUNTS_WITH_INDEPENDENT_PROOF_ONLY_NO_TITLE_TAX_CALCULATION',
            'production_ready' => false, 'signature_created' => false, 'transmission_performed' => false];
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
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D2101_'.$reason, 'production_ready' => false];
    }
}
