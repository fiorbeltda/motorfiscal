<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Original local D1011 candidate; registration and deployment calendar require independent proof. */
final readonly class DerePgccXmlProjector
{
    public const CONTRACT = 'DERE-PGCC-LOCAL-2026.10.02-1';

    /** @param array<string, mixed> $plan
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>  $taxCodes
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function project(array $plan, array $identity, array $reference, array $taxCodes, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null)) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        $fullDeclaration = ($rule['historical_declaration_required'] ?? false) === true;
        $schemaVersion = $rule['schema_version'] ?? 'v1_0_3';
        if (! in_array($schemaVersion, ['v1_0_3', 'v1_0_4'], true)) {
            return $this->stop('SCHEMA_VERSION_UNSUPPORTED');
        }
        $qualified = (new DerePgccQualifier)->resolve($plan, $reference, $taxCodes, $knownAt, $fullDeclaration, $rule['qualification_policy'] ?? []);
        if ($qualified['status'] !== 'RESOLVED') {
            return $qualified;
        }
        // Never silently turn a full declaration containing historical accounts into an active-only subset.
        if (count($plan['accounts']) !== count($qualified['accounts'])) {
            return $this->stop('HISTORICAL_ACCOUNT_DECLARATION_REVIEW_REQUIRED');
        }
        $supplier = $plan['supplier_id'] ?? null;
        $sequence = $identity['event_sequence'] ?? null;
        $generated = $identity['generated_at'] ?? null;
        if (! is_string($supplier) || ! preg_match('/^[0-9A-Z]{14}$/', $supplier)
            || ! is_int($sequence) || $sequence < 1 || $sequence > 99999
            || ! is_string($generated) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $generated)) {
            return $this->stop('EVENT_IDENTITY_INVALID');
        }
        if (($identity['contributor_qualified'] ?? null) !== true || ($identity['dere_start_qualified'] ?? null) !== true
            || ($identity['contributor_supplier_id'] ?? null) !== $supplier || ! $this->hash($identity['contributor_source_hash'] ?? null)
            || ! $this->hash($identity['dere_start_source_hash'] ?? null)) {
            return $this->stop('REGISTRATION_OR_START_UNPROVEN');
        }
        try {
            $start = LocalDate::fromString($plan['valid_from'])->value();
            $contributorStart = LocalDate::fromString($identity['contributor_valid_from'] ?? '')->value();
            $contributorEnd = isset($identity['contributor_valid_to']) ? LocalDate::fromString($identity['contributor_valid_to'])->value() : null;
            $dereStart = LocalDate::fromString($identity['dere_start_on'] ?? '')->value();
            $observed = LocalDate::fromString($identity['observed_on'] ?? '')->value();
            if ($start < $dereStart || $start < $contributorStart || ($contributorEnd !== null && ($contributorEnd < $contributorStart || $start > $contributorEnd))
                || $observed > $knownAt->format('Y-m-d')) {
                return $this->stop('REGISTRATION_OR_START_DATE_CONFLICT');
            }
            $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
            if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt || substr($generated, 0, 10) < $start) {
                return $this->stop('GENERATION_DATE_CONFLICT');
            }
            if (($rule['qualification_policy']['official_catalogs_required'] ?? false) === true
                && substr($generated, 0, 10) < $plan['observed_on']) {
                return $this->stop('GENERATION_DATE_CONFLICT');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('DATE_INVALID');
        }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtPGCC/'.$schemaVersion, 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtPGCC');
        $event->setAttribute('id', 'DeRE10111'.$supplier.$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($supplier, 0, 8));
        $period = $this->node($xml, $event, 'idePeriodo');
        $this->node($xml, $period, 'iniValid', $plan['valid_from']);
        if (isset($plan['valid_to'])) {
            $this->node($xml, $period, 'fimValid', $plan['valid_to']);
        }
        $info = $this->node($xml, $event, 'infoPGCC');
        $this->node($xml, $info, 'planoCtaRef', $plan['planoCtaRef']);
        $this->node($xml, $info, 'freqEncerr', $plan['freqEncerr']);
        $accounts = $this->node($xml, $info, 'infoContas');
        foreach ($qualified['accounts'] as $account) {
            $row = $this->node($xml, $accounts, 'infoConta');
            foreach (['cCta', 'cCtaInterna', 'cDbrMista', 'nomeCta', 'indCta', 'descCta', 'cCtaSup', 'cCtaRef', 'nivelCta', 'natCta', 'codNat', 'codTrib', 'indTribISS', 'idLeiDisp'] as $name) {
                if (isset($account[$name])) {
                    $this->node($xml, $row, $name, (string) $account[$name]);
                }
            }
            $this->node($xml, $row, 'iniVig', $account['valid_from']);
            if (isset($account['valid_to'])) {
                $this->node($xml, $row, 'fimVig', $account['valid_to']);
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_PGCC_LOCAL_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'schema' => 'evtPGCC-'.$schemaVersion.'.xsd', 'qualification' => $qualified,
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

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PGCC_'.$reason, 'production_ready' => false];
    }
}
