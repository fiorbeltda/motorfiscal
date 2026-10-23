<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DocumentComex135;
use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\ConsumptionReform\DocumentOwnTaxBridge135;
use App\Modules\Fiscal\ConsumptionReform\PartialExportForeignClassification135;
use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use RuntimeException;

/** Complete unsigned sandbox DPS XMLs from replayed134 plus newly captured135 declarations. */
final readonly class PartialExportDpsPair135
{
    public const CONTRACT = 'LOCAL-PARTIAL-EXPORT-DPS-PAIR-135-V1';

    public const AVAILABLE_AT = '2026-10-23T11:29:24+00:00';

    public const BUNDLE_SHA256 = '19fcbc089beb05fbb9f218ac939394b0d6f11e3492d4b15ea878b786abef7cee';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'source_bundle' => DocumentIssIntegration134::VERSION, 'source_bundle_sha256' => self::BUNDLE_SHA256,
            'own_bridge' => DocumentOwnTaxBridge135::policy(), 'foreign_classification' => PartialExportForeignClassification135::policy(),
            'additional_sources' => self::additionalSources(), 'own_federal_xml_scope' => 'NOT_PROJECTED_IN_THIS_CONTRACT',
            'scope' => 'LOCAL_UNSIGNED_SANDBOX_PAIR_NOT_FULFILLMENT'];
    }

    /** @return array<string, string> */
    public static function additionalSources(): array
    {
        $sources = [];
        foreach (SoftwareOwnRevenue135::policy()['sources'] as $name => $hash) {
            $sources['own135-'.$name] = $hash;
        }

        return [...$sources,
            'own135-stf-tema118.html' => SoftwareOwnRevenue135::policy()['court']['source_sha256'],
            'own135-lc214.html' => SoftwareOwnRevenue135::policy()['legacy_window']['source_sha256'],
            'export135-anexovi10401.xlsx' => PartialExportForeignClassification135::policy()['layout_sha256']];
    }

    /** Throws before any output artifact is written.
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $declarations
     * @return array<string, mixed>
     */
    public function project(array $snapshot, array $declarations, string $resourceRoot): array
    {
        $bundleName = 'bundles/consumption-phase4b-'.DocumentIssIntegration134::VERSION.'.json';
        $this->require(is_file($resourceRoot.'/'.$bundleName) && hash_file('sha256', $resourceRoot.'/'.$bundleName) === self::BUNDLE_SHA256, 'Immutable source134 bundle changed.');
        $bundle = json_decode((string) file_get_contents($resourceRoot.'/'.$bundleName), true, flags: JSON_THROW_ON_ERROR);
        $sourceCount = 0;
        foreach ($bundle['official_sources'] as $source) {
            $this->require(basename($source['file']) === $source['file'] && is_file($resourceRoot.'/sources/official/'.$source['file'])
                && hash_file('sha256', $resourceRoot.'/sources/official/'.$source['file']) === $source['sha256'], 'Source134 original bytes missing or changed.');
            $sourceCount++;
        }
        foreach (self::additionalSources() as $name => $digest) {
            $this->require(is_file($resourceRoot.'/sources/official/'.$name) && hash_file('sha256', $resourceRoot.'/sources/official/'.$name) === $digest, 'Source135 original bytes missing or changed.');
        }
        $factory = new FiscalEngineFactory;
        $knowledge = $factory->captureKnowledge($resourceRoot, $bundleName);
        $this->require(is_array($snapshot['input'] ?? null) && is_array($snapshot['knowledge'] ?? null) && is_array($snapshot['result'] ?? null)
            && CanonicalJson::hash($snapshot['knowledge']) === CanonicalJson::hash($knowledge), 'Exact complete134 snapshot knowledge required.');
        $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
        $known = $loaded->input->context->knowledgeAt;
        $this->require($known >= new DateTimeImmutable(self::AVAILABLE_AT), 'Projection135 not yet available at this knowledge instant.');
        $fresh = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
        $this->require(CanonicalJson::encode($fresh) === CanonicalJson::encode($snapshot['result']), 'Complete snapshot replay differs.');
        $this->require($fresh['status'] === 'ACTION_REQUIRED' && $fresh['reason_codes'] === ['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'], 'Exact nonfinal134 partial export required.');
        $rtcItems = array_values(array_filter($fresh['trace'], static fn (array $t): bool => $t['type'] === 'CONSUMPTION_REFORM'));
        $this->require(count($rtcItems) === 1, 'Unique fresh RTC result required.');
        $rtc = $rtcItems[0]['payload'];
        $iss = $this->stage($rtc, 'PARTIAL_EXPORT_DOCUMENT_ISS134');
        $allocation = $this->stage($rtc, 'PARTIAL_EXPORT_GROSS_ALLOCATION');
        $nominal = $this->stage($rtc, 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION');
        $this->require($this->keys($declarations, ['own_tax_proofs', 'comex_proofs', 'documentary_proof'])
            && is_array($declarations['own_tax_proofs']) && is_array($declarations['comex_proofs']) && is_array($declarations['documentary_proof']), 'Exact135 declaration groups required.');
        $proof = $declarations['documentary_proof'];
        $this->require($this->captured($proof, $known) && is_array($proof['value']['options'] ?? null), 'Current captured documentary proof required.');
        $evidenceIds = [];
        foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs'], $proof] as $claim) {
            $id = $claim['evidence_id'] ?? null;
            $this->require(is_string($id) && trim($id) !== '' && ! isset($evidenceIds[$id]), 'Distinct own, COMEX and documentary evidence identifiers required.');
            $evidenceIds[$id] = true;
        }
        $options = $proof['value']['options'];
        $expectedValue = ['projection_policy_hash' => CanonicalJson::hash(self::policy()), 'snapshot_hash' => CanonicalJson::hash($snapshot),
            'own_proof_hashes' => $this->proofHashes($declarations['own_tax_proofs'], true),
            'comex_proof_hashes' => $this->proofHashes($declarations['comex_proofs'], false), 'options' => $options];
        $this->require(CanonicalJson::encode($proof['value']) === CanonicalJson::encode($expectedValue), 'Documentary snapshot, policy or proof selection conflict.');
        foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs']] as $prior) {
            $priorObserved = $this->instant($prior['observed_at'] ?? null);
            $this->require($priorObserved !== null && $priorObserved <= $this->instant($proof['observed_at']), 'Documentary proof predates its dependencies.');
        }
        $facts = $loaded->input->context->facts;
        $raw = $facts->canonicalValues();
        $codes = $knowledge['documents']['rtc_code_catalog'];
        $bridge = (new DocumentOwnTaxBridge135)->reconcile($facts, $iss, $declarations['own_tax_proofs'], $allocation, DocumentOwnTaxBridge135::policy(), $known);
        $this->require($bridge['status'] === 'DOCUMENT_PRICES_ALL_OWN_TAXES_RTC_BRIDGE_BOUND', 'Own-tax price bridge refused:'.($bridge['reason'] ?? 'unknown'));
        $comex = (new DocumentComex135)->qualify($iss, $raw, $declarations['comex_proofs'], $codes, $known);
        $this->require($comex['status'] === 'LOCAL_DOCUMENT_COMEX_CLAIMS_BOUND', 'COMEX refused:'.($comex['reason'] ?? 'unknown'));
        $classification = (new PartialExportForeignClassification135)->qualify($facts, $rtc, $codes,
            LocalDate::fromString($snapshot['input']['competence_date']), $known, PartialExportForeignClassification135::policy());
        $this->require($classification['status'] === 'LOCAL_FOREIGN_EXPORT_CLASSIFICATION_QUALIFIED', 'Foreign classification refused.');
        $this->require($this->keys($options, ['issued_at', 'issuer_municipality', 'service_municipality', 'series', 'numbers',
            'simples_option', 'special_regime', 'nbs', 'software_type', 'nbs_review_evidence_id', 'service_description',
            'domestic_recipient_name', 'foreign_acquirer_internal_id', 'domestic_recipient_internal_id', 'foreign_acquirer', 'values_options', 'foreign_trade']), 'Exact explicit documentary options required.');
        $this->require(is_array($options['numbers']) && is_array($options['values_options']) && is_array($options['foreign_trade']), 'Explicit per-role identity,value and COMEX records required.');
        $issued = $this->instant($options['issued_at']);
        $this->require($issued !== null && $issued >= new DateTimeImmutable(self::AVAILABLE_AT) && $issued <= $known
            && $this->day($proof['valid_from']) && $this->day($proof['valid_to']) && $proof['valid_to'] >= $issued->format('Y-m-d'), 'Documentary issue date or coverage conflict.');
        $this->require($options['issuer_municipality'] === '3550308' && $options['service_municipality'] === '3550308'
            && $options['simples_option'] === '1' && $options['special_regime'] === '0', 'Declared issuer or service regime outside local133 profile.');
        foreach (['nbs_review_evidence_id', 'service_description', 'domestic_recipient_name'] as $key) {
            $this->require(is_string($options[$key]) && trim($options[$key]) !== '', 'Explicit documentary text/evidence required.');
        }
        $nbs = $this->reviewNbs($options, $codes);
        $this->require($options['foreign_acquirer_internal_id'] === $facts->value('rtc.acquirer_id')
            && $options['domestic_recipient_internal_id'] === $facts->value('rtc.export_domestic_recipient_id')
            && is_array($options['foreign_acquirer']) && ($options['foreign_acquirer']['country'] ?? null) === $facts->value('rtc.acquirer_country'), 'Party declaration differs from source roles.');
        $party = (new DpsForeignPartyProjector)->project($options['foreign_acquirer'], 'toma', $codes, $known);
        $this->require($party['status'] === 'PROJECTED', 'Foreign party declaration refused.');
        $values = (new DpsDocumentValues134)->project($iss, $options['values_options']);
        $this->require($values['status'] === 'LOCAL_DOCUMENT_VALUES_PROJECTED', 'Document values refused.');
        $valuesByRole = array_column($values['fragments'], null, 'role');
        $trades = array_column($comex['documents'], 'trade_fields', 'role');
        $this->require(CanonicalJson::encode($trades) === CanonicalJson::encode($options['foreign_trade']), 'Requested COMEX differs from freshly bound document fields.');
        $identities = array_column($raw['rtc.export_dated_dps_identity_records'], 'identity', 'role');
        $records = [];
        $ids = [];
        foreach ($iss['documents'] as $document) {
            $role = $document['role'];
            $identity = $identities[$role] ?? [];
            $this->require($identity['model'] === 'DPS' && $identity['environment'] === '2' && $identity['registration_type'] === '2'
                && $identity['emitter_type'] === '1' && $identity['emission_channel'] === 'OWN_APPLICATION'
                && $identity['issued_at'] === $options['issued_at'] && $identity['number'] === ($options['numbers'][$role] ?? null)
                && $identity['series'] === $options['series'] && $identity['issuer_municipality'] === $options['issuer_municipality']
                && $identity['supplier_id'] === $facts->value('rtc.supplier_id') && $identity['id'] === $document['dps_id']
                && $identity['competence_date'] === $document['iss_supply_on'] && ! isset($ids[$identity['id']]), 'Qualified identity differs from requested XML envelope.');
            $ids[$identity['id']] = true;
            $national = $document['municipal_result']['national_result'];
            $this->require($national['supplier_id'] === $facts->value('rtc.supplier_id')
                && $national['acquirer_id'] === $options['foreign_acquirer_internal_id']
                && $national['recipient_id'] === ($role === 'RTC_FOREIGN' ? $options['foreign_acquirer_internal_id'] : $options['domestic_recipient_internal_id'])
                && $proof['valid_from'] <= $document['iss_supply_on'], 'Documentary parties or coverage conflict with fresh material result.');
            $xml = $this->xml($document, $options, $party['xml'], $valuesByRole[$role]['xml'], $trades[$role], $nominal, $classification, $codes, $raw);
            $schema = $codes['dps_xsd'];
            $validation = (new DpsXmlSchemaValidator)->validate($xml, $resourceRoot.'/sources/official/'.$schema['source_file'], $schema['source_sha256'], $schema['root_schema']);
            $this->require($validation['status'] === 'VALID', 'Complete DPS XSD refused:'.json_encode($validation['errors']));
            $records[] = ['role' => $role, 'document_reference' => $document['document_reference'], 'xml' => $xml,
                'xml_sha256' => hash('sha256', $xml), 'validation' => $validation, 'dps_id' => $document['dps_id'],
                'gross_document_price_brl' => $document['gross_service_price'], 'own_iss_base_brl' => $document['iss_base_brl'], 'own_iss_tax_brl' => $document['iss_tax_brl']];
        }

        return ['status' => 'LOCAL_PARTIAL_EXPORT_PAIR_DPS_XSD_VALIDATED', 'contract' => self::CONTRACT, 'records' => $records,
            'snapshot_hash' => CanonicalJson::hash($snapshot), 'snapshot_replay_matches' => true, 'documentary_proof_hash' => $proof['source_hash'],
            'base_official_sources_valid' => $sourceCount, 'additional_sources_valid' => count(self::additionalSources()),
            'bridge' => $bridge, 'comex' => $comex, 'foreign_classification' => $classification, 'nbs_selection' => $nbs,
            'validation_scope' => 'TWO_COMPLETE_UNSIGNED_DPS_DOCUMENTS_OFFICIAL_XSD', 'complete_dps_built' => true,
            'fiscal_decision_status' => $fresh['status'], 'country_list_qualified' => false, 'currency_temporal_validity_proven' => false,
            'documentary_options_authenticated' => false, 'judicial_temporal_effects_approved' => false,
            'own_pis_cofins_projected_in_xml' => false,
            'complete_fiscal_pair_qualified' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<string, mixed> $rtc
     * @return array<string, mixed>
     */
    private function stage(array $rtc, string $name): array
    {
        $found = array_values(array_filter($rtc['trace'], static fn (array $t): bool => $t['stage'] === $name));
        $this->require(count($found) === 1, 'Unique fresh stage required:'.$name);

        return $found[0];
    }

    /** @param array<array-key, mixed> $proofs
     * @return array<string, string>
     */
    private function proofHashes(array $proofs, bool $own): array
    {
        $this->require(array_is_list($proofs) && count($proofs) === 2, 'Two proofs per contract required.');
        $rows = [];
        foreach ($proofs as $proof) {
            $role = $own ? ($proof['value']['document']['role'] ?? null) : ($proof['value']['role'] ?? null);
            $this->require(is_string($role) && in_array($role, ['RTC_FOREIGN', 'RTC_DOMESTIC'], true) && ! isset($rows[$role])
                && is_string($proof['source_hash'] ?? null), 'Distinct explicit proof roles/hashes required.');
            $rows[$role] = $proof['source_hash'];
        }
        ksort($rows);

        return $rows;
    }

    /** @param array<string, mixed> $options
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    private function reviewNbs(array $options, array $codes): array
    {
        $selected = match ($options['software_type']) {
            'CUSTOMIZED' => '115022000', 'NON_CUSTOMIZED' => '115021000', default => null,
        };
        $this->require($selected !== null && $options['nbs'] === $selected, 'Explicit supported software/NBS review required.');
        $matches = [];
        foreach ($codes['nbs'] as $key => $description) {
            if (str_replace('.', '', trim((string) $key)) === $selected) {
                $matches[] = ['source_key' => $key, 'normalized_reference' => trim((string) $key), 'code' => $selected, 'description' => $description];
            }
        }
        $this->require(count($matches) === 1, 'NBS reference missing or ambiguous after whitespace normalization.');

        return [...$matches[0], 'review_evidence_id' => $options['nbs_review_evidence_id'], 'correlation_is_decision_authority' => false, 'legal_applicability_approved' => false];
    }

    /** @param array<string, mixed> $proof */
    private function captured(array $proof, DateTimeImmutable $known): bool
    {
        if (! $this->keys($proof, ['contract', 'evidence_id', 'observed_at', 'valid_from', 'valid_to', 'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'])
            || $proof['contract'] !== self::CONTRACT || ! is_string($proof['evidence_id']) || trim($proof['evidence_id']) === '' || ! is_array($proof['value'])) {
            return false;
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed < new DateTimeImmutable(self::AVAILABLE_AT) || $observed > $known) {
            return false;
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($proof[$key] !== null && ($this->instant($proof[$key]) === null || $this->instant($proof[$key]) <= $known)) {
                return false;
            }
        }
        $origin = $proof['origin'];
        $body = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);

        return is_array($origin) && $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            && is_string($origin['reference']) && trim($origin['reference']) !== '' && $origin['media_type'] === 'application/json'
            && $origin['content'] === CanonicalJson::encode($body) && $origin['content_sha256'] === hash('sha256', $origin['content'])
            && $proof['source_hash'] === CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    private function day(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    /** @param array<string, mixed> $document
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $trade
     * @param  array<string, mixed>  $nominal
     * @param  array<string, mixed>  $foreignClass
     * @param  array<string, mixed>  $codes
     * @param  array<string, mixed>  $facts
     */
    private function xml(array $document, array $options, string $partyXml, string $valuesXml, array $trade, array $nominal, array $foreignClass, array $codes, array $facts): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $root->setAttribute('versao', '1.01');
        $doc->appendChild($root);
        $info = $this->node($doc, $root, 'infDPS');
        $info->setAttribute('Id', $document['dps_id']);
        foreach (['tpAmb' => '2', 'dhEmi' => $options['issued_at'], 'verAplic' => 'MOTOR-135-LOCAL',
            'serie' => $options['series'], 'nDPS' => $options['numbers'][$document['role']], 'dCompet' => $document['iss_supply_on'],
            'tpEmit' => '1', 'finNFSe' => '0', 'cLocEmi' => $options['issuer_municipality']] as $key => $value) {
            $this->node($doc, $info, $key, $value);
        }
        $supplier = $this->node($doc, $info, 'prest');
        $this->node($doc, $supplier, 'CNPJ', $facts['rtc.supplier_id']);
        $regime = $this->node($doc, $supplier, 'regTrib');
        $this->node($doc, $regime, 'opSimpNac', $options['simples_option']);
        $this->node($doc, $regime, 'regEspTrib', $options['special_regime']);
        $this->import($doc, $info, $partyXml);
        $service = $this->node($doc, $info, 'serv');
        $this->node($doc, $this->node($doc, $service, 'locPrest'), 'cLocPrestacao', $options['service_municipality']);
        $code = $this->node($doc, $service, 'cServ');
        $this->node($doc, $code, 'cTribNac', '010101');
        $this->node($doc, $code, 'cNBS', $options['nbs']);
        $this->node($doc, $code, 'xDescServ', $options['service_description']);
        $comex = $this->node($doc, $service, 'comExt');
        foreach (['mdPrestacao', 'vincPrest', 'tpMoeda', 'vServMoeda', 'mecAFComexP', 'mecAFComexT', 'movTempBens', 'mdic'] as $name) {
            $this->node($doc, $comex, $name, $trade[$name]);
        }
        $this->import($doc, $info, $valuesXml);
        $ibscbs = $this->node($doc, $info, 'IBSCBS');
        $foreign = $document['role'] === 'RTC_FOREIGN';
        if (! $foreign) {
            $this->node($doc, $ibscbs, 'cIndOp', $nominal['cindop']);
        }
        $this->node($doc, $ibscbs, 'indDest', $foreign ? '0' : '1');
        if (! $foreign) {
            $recipient = $this->node($doc, $ibscbs, 'dest');
            $this->node($doc, $recipient, 'CNPJ', $facts['rtc.export_domestic_recipient_id']);
            $this->node($doc, $recipient, 'xNome', $options['domestic_recipient_name']);
        }
        $tax = $this->node($doc, $this->node($doc, $ibscbs, 'valores'), 'trib');
        $class = $foreign ? $foreignClass['cclasstrib'] : $nominal['cclasstrib'];
        $this->node($doc, $tax, 'CST', $foreign ? $foreignClass['cst'] : $codes['cclasstrib'][$class]['cst']);
        $this->node($doc, $tax, 'cClassTrib', $class);

        return (string) $doc->saveXML();
    }

    private function node(DOMDocument $doc, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
        if ($value !== null) {
            $node->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    private function import(DOMDocument $doc, DOMElement $parent, string $xml): void
    {
        $fragment = new DOMDocument;
        $this->require($fragment->loadXML($xml, LIBXML_NONET) && $fragment->documentElement instanceof DOMElement, 'Projected XML fragment invalid.');
        $element = $fragment->documentElement;
        if (! $element instanceof DOMElement) {
            throw new RuntimeException('Projected XML fragment root missing.');
        }
        $parent->appendChild($doc->importNode($element, true));
    }
}
