<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;

/** Rebuild complete monthly proofs before binding the active lifecycle payload to native economics. */
final readonly class DerePublicOfferActiveEventBusinessBinder
{
    /** @param array<string,mixed> $operation
     * @param  array<string,mixed>  $month
     * @param  array<string,mixed>  $rules
     * @param  array<string|int,mixed>  $events
     * @param  array<string,mixed>  $pgccBinding
     * @param  array<string,mixed>|null  $periodHistory
     * @return array<string,mixed>
     */
    public function bind(array $operation, array $month, DateTimeImmutable $knownAt, array $rules, array $events, array $pgccBinding, string $sources, bool $allowNoMovement = false, ?array $periodHistory = null): array
    {
        if (! is_string($month['evidence_scope'] ?? null) || ! str_starts_with($month['evidence_scope'], 'SYNTHETIC')
            || ($month['prerequisites']['existing_closing'] ?? null) !== false) {
            return $this->stop('EXPLICIT_SYNTHETIC_OPEN_MONTH_REQUIRED');
        }
        $projection = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $knownAt, $rules);
        if (($projection['status'] ?? null) !== 'PROJECTED') {
            return $projection;
        }
        $native = $projection['debt_yield_projection'] ?? null;
        if ($native === null && $allowNoMovement) {
            $native = $projection['debt_no_movement_projection'] ?? null;
        }
        $pgcc = $projection['pgcc_qualification'] ?? null;
        $supplier = $operation['qualification']['supplier_id'] ?? null;
        $end = $operation['base']['period_end'] ?? null;
        if (! is_array($native) || ! is_string($native['xml'] ?? null) || ! is_array($pgcc)
            || ! is_string($supplier) || ! is_string($end) || ($pgccBinding['synthetic'] ?? null) !== true
            || ($pgccBinding['pgcc_source_hash'] ?? null) !== ($pgcc['source_hash'] ?? null)
            || ! is_string($pgccBinding['pgcc_receipt'] ?? null)) {
            return $this->stop('NATIVE_PUBLIC_PROJECTION_AND_PGCC_BINDING_REQUIRED');
        }
        $validation = (new DereXmlSchemaValidator)->validate($native['xml'],
            $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
            '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd', 'evtDebOpOfPublic-v0_0_3.xsd',
            $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
        if ($validation['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
            return $this->stop('NATIVE_PUBLIC_SCHEMA_UNPROVEN');
        }
        $periodQualification = null;
        if ($periodHistory !== null) {
            $periodEvents = $periodHistory['events'] ?? null;
            $witness = $periodHistory['witness'] ?? null;
            if (($periodHistory['monthly_input_hash'] ?? null) !== CanonicalJson::hash($month)
                || ! is_array($periodEvents) || ! array_is_list($periodEvents) || ! is_array($witness)) {
                return $this->stop('MONTHLY_BOUND_PERIOD_HISTORY_REQUIRED');
            }
            $qualifiedEvents = [];
            foreach ($periodEvents as $periodEvent) {
                if (! is_array($periodEvent)) {
                    return $this->stop('PERIOD_EVENT_OBJECT_REQUIRED');
                }
                $qualifiedEvents[] = $periodEvent;
            }
            $periodQualification = (new DerePublicOfferPeriodStateQualifier)->qualify($qualifiedEvents, $events,
                $supplier, substr($end, 0, 7), $knownAt, $sources, $witness);
            if (($periodQualification['status'] ?? null) !== 'LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED') {
                return $periodQualification;
            }
        }
        $chain = (new DerePublicOfferEventVersionReconciler)->select($events, $supplier, substr($end, 0, 7), $knownAt, $sources,
            ['synthetic' => true, 'period_open' => true]);
        if (($chain['status'] ?? null) !== 'LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED') {
            return $chain;
        }
        $active = $chain['active_event'];
        if (! is_array($active)) {
            return $this->stop('ACTIVE_PUBLIC_EVENT_MISSING_AFTER_EXCLUSION');
        }
        if (($active['reconciliation']['pgcc_receipt'] ?? null) !== $pgccBinding['pgcc_receipt']) {
            return $this->stop('ACTIVE_PGCC_RECEIPT_CONFLICT');
        }
        if (! is_string($active['origin_xml'] ?? null)) {
            return $this->stop('ACTIVE_PUBLIC_ORIGIN_BYTES_REQUIRED');
        }
        $expected = $this->business($native['xml']);
        $actual = $this->business($active['origin_xml']);
        if ($expected === null || $actual === null || CanonicalJson::encode($expected) !== CanonicalJson::encode($actual)) {
            return $this->stop('ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH');
        }

        return ['status' => 'LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND', 'active_event' => $active, 'version_chain' => $chain,
            ...($periodQualification === null ? [] : ['period_history_qualification' => $periodQualification]),
            'native_projection_hash' => CanonicalJson::hash($projection), 'native_origin_xml_sha256' => hash('sha256', $native['xml']),
            'business_payload_hash' => CanonicalJson::hash($actual), 'monthly_input_hash' => CanonicalJson::hash($month),
            'pgcc_source_hash' => $pgcc['source_hash'], 'total_vApur' => $active['reconciliation']['total_vApur'],
            'active_event_business_locally_reconciled' => true, 'period_state_externally_verified' => false,
            'pgcc_receipt_externally_verified' => false, 'government_return_authenticated' => false,
            'eligible_for_posting' => false, 'production_ready' => false];
    }

    /** @return array<string,mixed>|null */
    private function business(string $xml): ?array
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $path = new DOMXPath($document);
        $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        $result = [];
        foreach (['ideContrib', 'idePeriodo', 'infoDebOpOfPublic'] as $tag) {
            $nodes = $path->query('/d:DeRE/d:evtDebOpOfPublic/d:'.$tag);
            $node = $nodes === false ? null : $nodes->item(0);
            if (! $node instanceof DOMElement) {
                return null;
            }
            $result[$tag] = $this->node($node);
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function node(DOMElement $node): array
    {
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            if ($attribute->namespaceURI !== 'http://www.w3.org/2000/xmlns/') {
                $attributes[($attribute->namespaceURI ?? '').':'.$attribute->localName] = $attribute->value;
            }
        }
        ksort($attributes);
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $this->node($child);
            } elseif (trim($child->textContent) !== '') {
                $children[] = $child->textContent;
            }
        }

        return ['name' => $node->namespaceURI.':'.$node->localName, 'attributes' => $attributes, 'children' => $children];
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PUBLIC_BINDING_'.$reason, 'production_ready' => false];
    }
}
