<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/** Reuses identity/amount safeguards and relocates only the source-proven XML nodes. */
final readonly class DpsXmlProjector127
{
    /** @param array<string, mixed> $decision
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>|null  $foreignCodes
     * @return array<string, mixed>
     */
    public function project(array $decision, array $identity, ?array $foreignCodes = null): array
    {
        $fields = $decision['dps_projection']['rtc']['issuer_supplied'] ?? null;
        if (! is_array($fields) || count($fields) !== count(DpsLayoutPolicy127::FIELD_MAPPING)
            || array_diff(array_values(DpsLayoutPolicy127::FIELD_MAPPING), array_keys($fields)) !== []
            || ($decision['dps_projection']['rtc']['schema_version'] ?? null) !== 'RESTRICTED-LOCAL-OBSERVED-2026.10.22-127') {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DPS127_EXACT_ISSUER_MAPPING_REQUIRED', 'missing_fields' => [], 'xml' => null];
        }
        $legacy = $decision;
        $legacyFields = [];
        foreach (DpsLayoutPolicy127::FIELD_MAPPING as $old => $current) {
            $legacyFields[$old] = $fields[$current];
        }
        $legacy['dps_projection']['rtc']['issuer_supplied'] = $legacyFields;
        $projected = (new DpsXmlProjector)->project($legacy, $identity, $foreignCodes);
        if ($projected['status'] !== 'PROJECTED' || $projected['xml'] === null) {
            return $projected;
        }
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        if (! $doc->loadXML($projected['xml'], LIBXML_NONET)) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DPS127_LEGACY_XML_INVALID', 'missing_fields' => [], 'xml' => null];
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('d', 'http://www.sped.fazenda.gov.br/nfse');
        $base = '/d:DPS/d:infDPS';
        $info = $this->node($xpath, $base);
        $location = $this->node($xpath, $base.'/d:cLocEmi');
        $purpose = $this->node($xpath, $base.'/d:IBSCBS/d:finNFSe');
        $trib = $this->node($xpath, $base.'/d:IBSCBS/d:valores/d:trib');
        $group = $this->node($xpath, $base.'/d:IBSCBS/d:valores/d:trib/d:gIBSCBS');
        if (! $info instanceof DOMElement || ! $location instanceof DOMElement || ! $purpose instanceof DOMElement
            || ! $trib instanceof DOMElement || ! $group instanceof DOMElement) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DPS127_LEGACY_NODE_MAPPING_CONFLICT', 'missing_fields' => [], 'xml' => null];
        }
        $info->insertBefore($purpose, $location);
        foreach (['CST', 'cClassTrib'] as $tag) {
            $node = $this->node($xpath, $base.'/d:IBSCBS/d:valores/d:trib/d:gIBSCBS/d:'.$tag);
            if (! $node instanceof DOMElement) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS127_CLASSIFICATION_NODE_MISSING', 'missing_fields' => [], 'xml' => null];
            }
            $trib->insertBefore($node, $group);
        }
        $trib->removeChild($group);
        // In this observed archive cNBS precedes xDescServ in TCCServ.
        $nbs = $this->node($xpath, $base.'/d:serv/d:cServ/d:cNBS');
        if ($nbs instanceof DOMElement) {
            $description = $this->node($xpath, $base.'/d:serv/d:cServ/d:xDescServ');
            if (! $description instanceof DOMElement || ! $description->parentNode instanceof DOMElement) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS127_SERVICE_NODE_MAPPING_CONFLICT', 'missing_fields' => [], 'xml' => null];
            }
            $description->parentNode->insertBefore($nbs, $description);
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DPS127_XML_CANDIDATE_BUILT', 'missing_fields' => [],
            'xml' => $doc->saveXML() ?: null, 'field_mapping' => DpsLayoutPolicy127::FIELD_MAPPING,
            'schema_sha256' => DpsLayoutPolicy127::SOURCE_SHA256, 'production_ready' => false,
            ...(isset($projected['foreign_acquirer_binding']) ? ['foreign_acquirer_binding' => $projected['foreign_acquirer_binding']] : [])];
    }

    private function node(DOMXPath $xpath, string $path): ?DOMNode
    {
        $nodes = $xpath->query($path);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMNode ? $node : null;
    }
}
