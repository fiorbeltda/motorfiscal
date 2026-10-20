<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DereSecuritiesRealizationBindingQualifier;
use App\Modules\Fiscal\ConsumptionReform\DereTrialBalanceQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/** Local original D1101 candidate, qualified again before projection. */
final readonly class DereTrialBalanceXmlProjector
{
    public const CONTRACT = 'DERE-TRIAL-BALANCE-LOCAL-2026.10.02-1';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $book
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>|null  $securitiesBinding
     * @return array<string, mixed>
     */
    public function project(array $operation, array $book, array $identity, DateTimeImmutable $knownAt, array $rule, ?array $securitiesBinding = null): array
    {
        $hasSecurities = ($operation['base']['realization_bindings'] ?? []) !== []
            || array_any($operation['base']['entries'] ?? [], static fn (array $entry): bool => ($entry['kind'] ?? null) === 'QUALIFIED_SECURITIES_COMPONENT');
        if ($hasSecurities && (($securitiesBinding['status'] ?? null) !== 'RESOLVED'
            || ($securitiesBinding['contract'] ?? null) !== DereSecuritiesRealizationBindingQualifier::CONTRACT
            || ($securitiesBinding['operation_hash'] ?? null) !== CanonicalJson::hash($operation)
            || ($securitiesBinding['book_content_hash'] ?? null) !== CanonicalJson::hash($book))) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BALANCE_TVM_BOOK_BINDING_REQUIRED', 'production_ready' => false];
        }
        $balance = (new DereTrialBalanceQualifier)->resolve($operation, $book, $knownAt, $rule);
        if ($balance['status'] !== 'RESOLVED') {
            return $balance;
        }
        $supplier = $balance['supplier_id'];
        $sequence = $identity['event_sequence'] ?? null;
        $generated = $identity['generated_at'] ?? null;
        if (! is_string($supplier) || preg_match('/^[0-9A-Z]{14}$/', $supplier) !== 1
            || ! is_int($sequence) || $sequence < 1 || $sequence > 99999
            || ! is_string($generated) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $generated) !== 1) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DERE_BALANCE_EVENT_IDENTITY_INVALID'];
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) <= $book['period_end']) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DERE_BALANCE_GENERATION_DATE_CONFLICT'];
        }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtBalancete/v1_0_1', 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtBalancete');
        $event->setAttribute('id', 'DeRE11011'.$supplier.$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($supplier, 0, 8));
        $this->node($xml, $this->node($xml, $event, 'idePeriodo'), 'perApur', $balance['period']);
        $accounts = $this->node($xml, $this->node($xml, $event, 'infoBalancete'), 'infoContas');
        foreach ($balance['accounts'] as $account) {
            $row = $this->node($xml, $accounts, 'infoConta');
            foreach (['cCta', 'natSaldoInic', 'vSaldoInic', 'vMovDebt', 'vAjusteDebt', 'vMovCred', 'vAjusteCred', 'natSaldoFinal', 'vSaldoFinal', 'natVApur', 'vApur'] as $name) {
                if (isset($account[$name])) {
                    $this->node($xml, $row, $name, $account[$name]);
                }
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_BALANCE_LOCAL_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'balance' => $balance, 'schema' => 'evtBalancete-v1_0_1.xsd',
            'production_ready' => false, 'signature_created' => false, 'transmission_performed' => false];
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
}
