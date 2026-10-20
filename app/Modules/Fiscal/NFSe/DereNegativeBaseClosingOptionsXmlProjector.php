<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseClosingParametersValidator;
use App\Modules\Fiscal\ConsumptionReform\DereNegativeBaseClosingVersionReconciler;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;

/** Declares locally qualified synthetic BCN options over preserved monthly XML; no tax recalculation. */
final readonly class DereNegativeBaseClosingOptionsXmlProjector
{
    public const CONTRACT = 'DERE-BCN-CLOSING-OPTIONS-LOCAL-1.3.0-50';

    /** @param array<string, mixed> $baseline
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function project(array $baseline, array $request, DateTimeImmutable $knownAt, array $policy, string $sources): array
    {
        if (($policy['contract'] ?? null) !== self::CONTRACT || ($policy['status'] ?? null) !== 'SANDBOX_CANDIDATE'
            || ($policy['mode'] ?? null) !== 'EXPLICIT_SYNTHETIC_LOCAL_REQUEST_ONLY'
            || ($policy['production_ready'] ?? null) !== false || ! is_string($policy['published_at'] ?? null)
            || ! is_string($policy['source_hash'] ?? null)
            || CanonicalJson::hash(array_diff_key($policy, ['source_hash' => true])) !== $policy['source_hash']) {
            return $this->stop('POLICY_UNPROVEN');
        }
        $published = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $policy['published_at']);
        if ($published === false || $published > $knownAt) {
            return $this->stop('POLICY_NOT_KNOWN');
        }
        $schemaHash = $policy['schema_sha256'] ?? null;
        if (! is_string($schemaHash) || ! preg_match('/^[a-f0-9]{64}$/', $schemaHash)) {
            return $this->stop('OFFICIAL_SOURCE_CONFLICT');
        }
        foreach (['schema_sha256' => 'dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
            'mod_sha256' => 'dere-observed-20261005-mod.pdf', 'annex_i_sha256' => 'dere-observed-20261005-annex-i.pdf'] as $field => $file) {
            if (! is_string($policy[$field] ?? null) || ! is_file($sources.'/'.$file) || $policy[$field] !== hash_file('sha256', $sources.'/'.$file)) {
                return $this->stop('OFFICIAL_SOURCE_CONFLICT');
            }
        }
        $balance = $baseline['balance']['balance'] ?? null;
        if (($baseline['status'] ?? null) !== 'PROJECTED' || ($baseline['production_ready'] ?? null) !== false
            || ($baseline['schema'] ?? null) !== 'evtFechMensal-v0_0_2.xsd' || ! is_array($balance)
            || ! is_string($baseline['xml'] ?? null) || ($request['synthetic'] ?? null) !== true
            || ($request['baseline_projection_hash'] ?? null) !== CanonicalJson::hash($baseline)
            || ($request['supplier_id'] ?? null) !== ($balance['supplier_id'] ?? null)
            || ($request['period'] ?? null) !== ($balance['period'] ?? null)
            || ! is_string($balance['period'] ?? null) || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $balance['period'])
            || ! is_array($request['parameters'] ?? null) || ! is_array($request['prior_closing_events'] ?? null)) {
            return $this->stop('MONTHLY_BASELINE_OR_REQUEST_SCOPE_UNPROVEN');
        }
        $period = $balance['period'];
        if (! is_string($policy['valid_from'] ?? null) || $period.'-01' < $policy['valid_from']
            || (isset($policy['valid_to']) && $period.'-01' > $policy['valid_to'])
            || (isset($policy['revoked_at']) && $knownAt >= new DateTimeImmutable($policy['revoked_at']))) {
            return $this->stop('POLICY_PERIOD_UNAVAILABLE');
        }
        $parameters = (new DereNegativeBaseClosingParametersValidator)->validate($request['parameters'], $period);
        if ($parameters['status'] !== 'PARAMETERS_VALIDATED') {
            return $parameters;
        }
        $previous = (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m');
        $events = $request['prior_closing_events'];
        if (! array_is_list($events) || array_any($events, static fn (mixed $event): bool => ! is_array($event)
            || array_any(array_keys($event), static fn (mixed $key): bool => ! is_string($key)))) {
            return $this->stop('PRIOR_CHAIN_SHAPE_INVALID');
        }
        /** @var list<array<string, mixed>> $events */
        $chain = (new DereNegativeBaseClosingVersionReconciler)->reconcile($events,
            $balance['supplier_id'], $previous, $sources, true);
        if ($chain['status'] !== 'LOCAL_CLOSING_VERSION_CHAIN_RECONCILED') {
            return $chain;
        }
        $stock = $chain['active_return']['stock_after_return'];
        $byKey = array_column($stock, null, 'codBCN');
        $knownRoots = array_column($chain['active_return']['normalized_return']['groups'], 'codBCNRaiz');
        foreach ($request['parameters']['infoBCN'] as $option) {
            if (! in_array($option['codBCNRaiz'], $knownRoots, true)) {
                return $this->stop('ROOT_NOT_IN_PRIOR_RETURN');
            }
        }
        foreach ($request['parameters']['detBCNeg'] ?? [] as $detail) {
            if (! isset($byKey[$detail['codBCN']])) {
                return $this->stop('ORIGIN_NOT_IN_ACTIVE_PRIOR_STOCK');
            }
        }
        foreach ($stock as $row) {
            $usedRoot = array_filter($request['parameters']['infoBCN'], static fn (array $option): bool => $option['codBCNRaiz'] === $row['codBCNRaiz'] && $option['usarBCNAcum'] === '1');
            $anniversaryMonth = sprintf('%04d%s', (int) substr($row['perOrigem'], 0, 4) + 5, substr($row['perOrigem'], 4));
            if ($usedRoot !== [] && $period >= $anniversaryMonth) {
                return $this->stop('EXPIRY_CALENDAR_REQUIRED');
            }
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $baseline['xml'])) {
            return $this->stop('BASELINE_XML_INVALID');
        }
        $baselineSchema = $this->schema($baseline['xml'], $sources, $schemaHash);
        if ($baselineSchema['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
            return $this->stop('BASELINE_SCHEMA_UNPROVEN');
        }
        $xml = new DOMDocument;
        $xml->resolveExternals = false;
        $xml->substituteEntities = false;
        if (! $xml->loadXML($baseline['xml'], LIBXML_NONET)) {
            return $this->stop('BASELINE_XML_INVALID');
        }
        $xpath = new DOMXPath($xml);
        $namespace = 'http://www.dere.gov.br/schemas/evtFechMensal/v0_0_2';
        $xpath->registerNamespace('d', $namespace);
        $events = $xpath->query('/d:DeRE/d:evtFechMensal');
        $event = $events === false ? null : $events->item(0);
        $optionGroups = $event instanceof DOMElement ? $xpath->query('d:infoFechamento/d:gUtilizBCN', $event) : false;
        if (! $event instanceof DOMElement || $xpath->evaluate('string(d:idePeriodo/d:perApur)', $event) !== $period
            || $xpath->evaluate('string(d:ideContrib/d:nrInsc)', $event) !== substr($balance['supplier_id'], 0, 8)
            || $optionGroups === false || $optionGroups->length > 0) {
            return $this->stop('BASELINE_XML_SCOPE_OR_EXISTING_OPTIONS_CONFLICT');
        }
        $infoNodes = $xpath->query('d:infoFechamento', $event);
        $info = $infoNodes === false ? null : $infoNodes->item(0);
        if (! $info instanceof DOMElement) {
            $info = $xml->createElementNS($namespace, 'infoFechamento');
            $event->appendChild($info);
        }
        $group = $xml->createElementNS($namespace, 'gUtilizBCN');
        $info->appendChild($group);
        foreach (['infoBCN', 'detBCNeg'] as $name) {
            foreach ($request['parameters'][$name] ?? [] as $row) {
                $child = $xml->createElementNS($namespace, $name);
                $group->appendChild($child);
                foreach ($name === 'infoBCN' ? ['codBCNRaiz', 'usarBCNAcum', 'metodoAproveit'] : ['codBCN', 'vUsarBCN'] as $tag) {
                    if (! array_key_exists($tag, $row)) {
                        continue;
                    }
                    $leaf = $xml->createElementNS($namespace, $tag);
                    $leaf->appendChild($xml->createTextNode($row[$tag]));
                    $child->appendChild($leaf);
                }
            }
        }
        $candidate = $xml->saveXML();
        if ($candidate === false) {
            return $this->stop('XML_SERIALIZATION_FAILED');
        }
        $validation = $this->schema($candidate, $sources, $schemaHash);
        if ($validation['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
            return $this->stop('CANDIDATE_SCHEMA_UNPROVEN');
        }

        return ['status' => 'PROJECTED', 'contract' => self::CONTRACT, 'xml' => $candidate, 'schema_validation' => $validation,
            'schema' => 'evtFechMensal-v0_0_2.xsd', 'baseline_projection_hash' => CanonicalJson::hash($baseline),
            'request_hash' => CanonicalJson::hash($request), 'prior_chain_hash' => $chain['chain_hash'],
            'policy_hash' => $policy['source_hash'], 'synthetic' => true,
            'gross_debits_recalculated' => false, 'period_closed' => false,
            'signature_created' => false, 'transmission_performed' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function schema(string $xml, string $sources, string $hash): array
    {
        return (new DereXmlSchemaValidator)->validate($xml,
            $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip', $hash, 'evtFechMensal-v0_0_2.xsd',
            $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_OPTIONS_'.$reason, 'production_ready' => false];
    }
}
