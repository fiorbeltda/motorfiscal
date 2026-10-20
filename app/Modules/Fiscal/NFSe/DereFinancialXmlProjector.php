<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Projects qualified allocation detail; it does not infer transactions from a monthly total. */
final readonly class DereFinancialXmlProjector
{
    public const CONTRACT = 'DERE-FINANCIAL-LOCAL-2026.10.02-1';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function project(array $operation, array $tax, array $proof, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])
            || ! isset($rule['published_at']) || new DateTimeImmutable($rule['published_at']) > $knownAt) {
            return $this->stop('ACTION_REQUIRED', 'CONTRACT_UNAVAILABLE');
        }
        if (($operation['status'] ?? null) !== 'RESOLVED' || ($tax['status'] ?? null) !== 'RESOLVED'
            || ($operation['qualification']['regime'] ?? null) !== 'SPECIFIC_FINANCIAL_ART181'
            || ($proof['supplier_id'] ?? null) !== ($operation['qualification']['supplier_id'] ?? null)
            || ($tax['supplier_id'] ?? null) !== ($proof['supplier_id'] ?? null)
            || ($proof['document_id'] ?? null) !== ($operation['qualification']['document_id'] ?? null)
            || ($proof['period_end'] ?? null) !== ($tax['period_end'] ?? null)
            || ($proof['period_end'] ?? null) !== ($operation['base']['period_end'] ?? null)) {
            return $this->stop('INVALID', 'SCOPE_CONFLICT');
        }
        foreach (['allocation_complete', 'allocation_qualified', 'net_bases_qualified', 'iss_incidence_qualified', 'parties_qualified'] as $key) {
            if (($proof[$key] ?? null) !== true) {
                return $this->stop('ACTION_REQUIRED', 'QUALIFICATION_MISSING:'.$key);
            }
        }
        foreach (['allocation_evidence_id', 'iss_incidence_evidence_id', 'parties_evidence_id'] as $key) {
            if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        if (! is_bool($proof['iss_incident'] ?? null) || ! is_string($proof['allocation_source_hash'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/', $proof['allocation_source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'ALLOCATION_SOURCE_OR_ISS_UNKNOWN');
        }
        $category = $operation['qualification']['operation_category'] ?? null;
        $code = $rule['base_codes'][$category][$proof['iss_incident'] ? 'ISS' : 'NO_ISS'] ?? null;
        if (! is_array($code) || ! is_string($code['code'] ?? null) || ! is_string($code['event'] ?? null) || ! is_string($code['namespace'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'BASE_CODE_NOT_MATERIALIZED');
        }
        $groups = $proof['groups'] ?? null;
        if (! is_array($groups) || ! array_is_list($groups) || $groups === [] || count($groups) > 1000) {
            return $this->stop('INVALID', 'GROUPS_INVALID');
        }
        try {
            $period = substr(LocalDate::fromString($proof['period_end'])->value(), 0, 7);
            if (LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value() > $knownAt->format('Y-m-d')) {
                return $this->stop('INVALID', 'OBSERVATION_IN_FUTURE');
            }
            $emitted = $this->timestamp($proof['generated_at'] ?? null);
            if ($emitted > $knownAt || substr($proof['generated_at'], 0, 7) < $period) {
                return $this->stop('INVALID', 'GENERATION_DATE_CONFLICT');
            }
            $supplier = $proof['supplier_id'];
            if (! is_string($supplier) || ! preg_match('/^[0-9A-Z]{14}$/', $supplier)
                || ! is_int($proof['event_sequence'] ?? null) || $proof['event_sequence'] < 1 || $proof['event_sequence'] > 99999) {
                return $this->stop('INVALID', 'EVENT_IDENTITY_INVALID');
            }
            $xml = new DOMDocument('1.0', 'UTF-8');
            $root = $xml->createElementNS($code['namespace'], 'DeRE');
            $xml->appendChild($root);
            $event = $this->node($xml, $root, $code['event']);
            $id = 'DeRE'.$code['event_code'].'1'.$supplier.$emitted->format('YmdHis').str_pad((string) $proof['event_sequence'], 5, '0', STR_PAD_LEFT);
            $event->setAttribute('id', $id);
            $header = $this->node($xml, $event, 'ideEvento');
            foreach (['finEvt' => '11', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
                $this->node($xml, $header, $name, $value);
            }
            $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($supplier, 0, 8));
            $periodNode = $this->node($xml, $event, 'idePeriodo');
            $this->node($xml, $periodNode, 'perApur', $period);
            $this->node($xml, $periodNode, 'dhEmi', $proof['generated_at']);
            $info = $this->node($xml, $event, 'infoOper');
            $baseSum = DecimalValue::fromString('0.00');
            $keys = [];
            $transactions = [];
            foreach ($groups as $group) {
                if (! is_array($group) || ! is_array($group['operations'] ?? null) || ! array_is_list($group['operations'])
                    || $group['operations'] === [] || count($group['operations']) > 999
                    || ! in_array($group['registration_type'] ?? null, ['1', '2'], true) || ! is_string($group['acquirer_id'] ?? null)
                    || ! is_string($group['municipality'] ?? null) || ! preg_match('/^[0-9]{7}$/', $group['municipality'])
                    || ! is_int($group['series'] ?? null)) {
                    return $this->stop('INVALID', 'GROUP_IDENTITY_OR_OPERATIONS_INVALID');
                }
                $first = null;
                $last = null;
                foreach ($group['operations'] as $detail) {
                    if (! is_array($detail) || ! is_string($detail['transaction_id'] ?? null) || $detail['transaction_id'] === ''
                        || in_array($detail['transaction_id'], $transactions, true)) {
                        return $this->stop('INVALID', 'TRANSACTION_DUPLICATE_OR_INVALID');
                    }
                    $transactions[] = $detail['transaction_id'];
                    $time = $this->timestamp($detail['occurred_at'] ?? null);
                    if (substr($detail['occurred_at'], 0, 7) !== $period || $time > $emitted || ($last !== null && $time < $last)) {
                        return $this->stop('INVALID', 'OPERATION_TIME_CONFLICT');
                    }
                    $first ??= $time;
                    $last = $time;
                }
                $key = (new DereAccessKey)->group(substr($supplier, 0, 8), 'BR', $group['registration_type'], $group['acquirer_id'], $code['code'],
                    LocalDate::fromString($first->format('Y-m-d')), LocalDate::fromString($last->format('Y-m-d')), $group['series']);
                if (in_array($key, $keys, true)) {
                    return $this->stop('INVALID', 'GROUP_KEY_DUPLICATE');
                }
                $keys[] = $key;
                $g = $this->node($xml, $info, 'gOper');
                $this->node($xml, $g, 'chDeRE', $key);
                $this->node($xml, $g, 'codBC', $code['code']);
                $parties = $this->node($xml, $this->node($xml, $g, 'idePartes'), 'infoAdq');
                $this->node($xml, $parties, $group['registration_type'] === '1' ? 'CNPJ' : 'CPF', $group['acquirer_id']);
                $this->node($xml, $parties, 'cMun', $group['municipality']);
                $details = $this->node($xml, $g, 'dadosOper');
                foreach ($group['operations'] as $index => $detail) {
                    if (! is_string($detail['net_base'] ?? null) || ! preg_match('/^\d+\.\d{2}$/', $detail['net_base'])
                        || ! is_bool($detail['foreign_currency_referenced'] ?? null)) {
                        return $this->stop('INVALID', 'OPERATION_BASE_OR_CURRENCY_INVALID');
                    }
                    $baseSum = $baseSum->plus(DecimalValue::fromString($detail['net_base']));
                    $d = $this->node($xml, $details, 'detOper');
                    $d->setAttribute('seq', str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                    $this->node($xml, $d, 'dhOper', $detail['occurred_at']);
                    $this->node($xml, $this->node($xml, $d, 'qualifOper'), 'indMoedaEstr', $detail['foreign_currency_referenced'] ? '1' : '0');
                    $this->node($xml, $this->node($xml, $d, 'gBC'), 'vBCApur', $detail['net_base']);
                    $tributes = $this->node($xml, $d, 'gTributos');
                    $this->node($xml, $tributes, 'vBCTrib', $detail['net_base']);
                    foreach (['IBS_MUNICIPAL' => ['pIBSMunTrib', 'vIBSMunTrib'], 'IBS_STATE' => ['pIBSUFTrib', 'vIBSUFTrib'], 'CBS' => ['pCBSTrib', 'vCBSTrib']] as $component => [$rateTag, $amountTag]) {
                        $rate = $tax['components'][$component]['percent'] ?? null;
                        if (! is_string($rate) || ! preg_match('/^\d+(?:\.\d{1,6})?$/', $rate)
                            || ! is_string($tax['components'][$component]['source_hash'] ?? null)
                            || ! preg_match('/^[a-f0-9]{64}$/', $tax['components'][$component]['source_hash'])) {
                            return $this->stop('INVALID', 'COMPONENT_RATE_UNPROVEN');
                        }
                        $this->node($xml, $tributes, $rateTag, DecimalValue::fromString($rate)->rounded(6)->value());
                        $this->node($xml, $tributes, $amountTag, (new DereRounding)->taxAmount($detail['net_base'], $rate));
                    }
                }
            }
            if ($baseSum->compareTo(DecimalValue::fromString($tax['base'])) !== 0
                || $baseSum->compareTo(DecimalValue::fromString($operation['base']['net_base'])) !== 0) {
                return $this->stop('INVALID', 'MONTHLY_ALLOCATION_BASE_MISMATCH');
            }
            foreach (['IBS_MUNICIPAL', 'IBS_STATE', 'CBS'] as $component) {
                if (($tax['components'][$component]['nominal_amount'] ?? null) !== (new DereRounding)->taxAmount($tax['base'], $tax['components'][$component]['percent'])) {
                    return $this->stop('INVALID', 'MONTHLY_ROUNDING_CONFLICT:'.$component);
                }
            }

            return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_QUALIFIED_ALLOCATION_PROJECTED', 'contract' => self::CONTRACT,
                'xml' => $xml->saveXML(), 'event_id' => $id, 'group_keys' => $keys, 'allocated_base' => $baseSum->value(),
                'transaction_count' => count($transactions), 'allocation_source_hash' => $proof['allocation_source_hash'], 'source_hash' => $rule['source_hash'],
                'production_ready' => false, 'signature_performed' => false, 'transmission_performed' => false,
                'scope' => 'UNSIGNED_LOCAL_TRANSACTION_CANDIDATE_NOT_MONTHLY_DECLARATION_APPROVAL'];
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'VALUE_OR_DATE_INVALID');
        }
    }

    private function timestamp(mixed $value): DateTimeImmutable
    {
        if (! is_string($value) || ! preg_match('/^20\d{2}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:00$/', $value)) {
            throw new InvalidArgumentException('Explicit DeRE timestamp required.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $value);
        if ($date === false || $date->format('Y-m-d\TH:i:s.vP') !== $value) {
            throw new InvalidArgumentException('Invalid DeRE timestamp.');
        }

        return $date;
    }

    private function node(DOMDocument $xml, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $xml->createElement($name);
        if ($value !== null) {
            $node->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_DERE_'.$reason, 'xml' => null, 'production_ready' => false];
    }
}
