<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DOMDocument;
use DOMElement;

/** Candidate identity fragment only: neither a fiscal document nor authenticated foreign residency. */
final readonly class DpsForeignPartyProjector
{
    public const CONTRACT = 'DPS-FOREIGN-PARTY-CANDIDATE-2026.10.15-75';

    /**
     * @param  array<string, mixed>  $party
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function project(array $party, string $role, array $codes): array
    {
        if (! in_array($role, ['toma', 'IBSCBS.dest'], true)) {
            return $this->stop('ACTION_REQUIRED', 'ROLE_NOT_BOUND_TO_OBSERVED_SCHEMA');
        }
        if (($codes['dps_xsd']['source_sha256'] ?? null) !== DpsRestrictedSchemaSelection::ARCHIVE_SHA256
            || ($codes['dps_xsd']['root_schema'] ?? null) !== 'DPS_v1.01.xsd'
            || ($codes['local_xml_projection']['schema_selection']['root_sha256'] ?? null) !== DpsRestrictedSchemaSelection::ROOT_SHA256) {
            return $this->stop('ACTION_REQUIRED', 'SCHEMA_BINDING_NOT_PROVEN');
        }
        $allowed = ['country', 'name', 'nif', 'no_nif_reason', 'address', 'phone', 'email',
            'party_evidence_id', 'identification_evidence_id', 'address_evidence_id'];
        if (array_diff(array_keys($party), $allowed) !== []) {
            return $this->stop('ACTION_REQUIRED', 'ADDITIONAL_PARTY_DATA_NOT_MAPPED');
        }
        $address = $party['address'] ?? null;
        if (! is_array($address) || array_is_list($address)) {
            return $this->stop('ACTION_REQUIRED', 'FOREIGN_ADDRESS_REQUIRED');
        }
        if (array_diff(array_keys($address), ['postal_code', 'city', 'province', 'street', 'number', 'complement', 'district']) !== []) {
            return $this->stop('ACTION_REQUIRED', 'ADDITIONAL_ADDRESS_DATA_NOT_MAPPED');
        }
        foreach (['party_evidence_id', 'identification_evidence_id', 'address_evidence_id'] as $key) {
            if (! $this->text($party[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_LINK_REQUIRED');
            }
        }
        $country = $party['country'] ?? null;
        if (! is_string($country) || preg_match('/\A[A-Z]{2}\z/D', $country) !== 1 || $country === 'BR') {
            return $this->stop('INVALID', 'FOREIGN_COUNTRY_FORMAT_REQUIRED');
        }
        $hasNif = array_key_exists('nif', $party);
        $hasReason = array_key_exists('no_nif_reason', $party);
        if ($hasNif && $hasReason) {
            return $this->stop('INVALID', 'IDENTIFICATION_CHOICE_CONFLICT');
        }
        if (! $hasNif && ! $hasReason) {
            return $this->stop('ACTION_REQUIRED', 'IDENTIFICATION_REQUIRED');
        }
        if ($hasReason && ! in_array($party['no_nif_reason'], ['1', '2'], true)) {
            // Code 0 describes missing data on an origin note, not proof of an issuer's NIF exemption.
            return $this->stop('ACTION_REQUIRED', 'NIF_ABSENCE_NOT_JUSTIFIED');
        }
        $identification = $hasNif ? 'NIF' : 'cNaoNIF';
        $fields = [$identification => $hasNif ? $party['nif'] : $party['no_nif_reason'],
            'xNome' => $party['name'] ?? null, 'end.endExt.cPais' => $country,
            'end.endExt.cEndPost' => $address['postal_code'] ?? null,
            'end.endExt.xCidade' => $address['city'] ?? null,
            'end.endExt.xEstProvReg' => $address['province'] ?? null,
            'end.xLgr' => $address['street'] ?? null, 'end.nro' => $address['number'] ?? null];
        if (array_key_exists('complement', $address)) {
            $fields['end.xCpl'] = $address['complement'];
        }
        $fields['end.xBairro'] = $address['district'] ?? null;
        foreach (['phone' => 'fone', 'email' => 'email'] as $key => $tag) {
            if (array_key_exists($key, $party)) {
                $fields[$tag] = $party[$key];
            }
        }
        $qualifiedFields = [];
        foreach ($fields as $field => $value) {
            if (! $this->text($value)) {
                return $this->stop('ACTION_REQUIRED', 'NONEMPTY_XML_TEXT_REQUIRED');
            }
            $spec = $codes['dps_xsd_fields']['DPS.infDPS.'.$role.'.'.$field] ?? null;
            if (! is_array($spec)) {
                return $this->stop('ACTION_REQUIRED', 'FIELD_NOT_BOUND_TO_OBSERVED_SCHEMA');
            }
            // The extracted leaf facets do not duplicate the inherited TSString restriction.
            if (($spec['base'] ?? null) === 'TSString'
                && preg_match('/\A(?:[!-ÿ][ -ÿ]*[!-ÿ]|[!-ÿ])\z/uD', $value) !== 1) {
                return $this->stop('INVALID', 'SOURCE_STRING_REPRESENTATION_UNSUPPORTED');
            }
            $qualifiedFields[$role.'.'.$field] = $value;
        }
        $validation = (new DpsIssuerFieldValidator)->validate($qualifiedFields,
            ['xsd_source_hash' => DpsRestrictedSchemaSelection::ARCHIVE_SHA256], $codes);
        if ($validation['status'] !== 'VALID') {
            return [...$this->stop($validation['status'], 'OBSERVED_FIELD_FACET_CONFLICT'), 'field_validation' => $validation];
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $element = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', $role === 'toma' ? 'toma' : 'dest');
        $document->appendChild($element);
        /** @var array<string, DOMElement> $containers */
        $containers = ['' => $element];
        foreach ($fields as $path => $value) {
            $parts = explode('.', $path);
            $tag = array_pop($parts);
            $parent = '';
            foreach ($parts as $part) {
                $next = $parent === '' ? $part : $parent.'.'.$part;
                if (! isset($containers[$next])) {
                    $containers[$next] = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', $part);
                    $containers[$parent]->appendChild($containers[$next]);
                }
                $parent = $next;
            }
            $node = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', $tag);
            $node->appendChild($document->createTextNode($value));
            $containers[$parent]->appendChild($node);
        }
        $xml = $document->saveXML($element);
        if ($xml === false) {
            return $this->stop('INVALID', 'XML_SERIALIZATION_FAILED');
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DPS_FOREIGN_PARTY_CANDIDATE_BUILT',
            'contract_version' => self::CONTRACT, 'scope' => 'FOREIGN_PARTY_FRAGMENT_ONLY',
            'role' => $role, 'fields' => $qualifiedFields, 'xml' => $xml, 'xml_sha256' => hash('sha256', $xml),
            'party_input_hash' => CanonicalJson::hash($party),
            'evidence_links' => array_intersect_key($party, array_flip(['party_evidence_id', 'identification_evidence_id', 'address_evidence_id'])),
            'schema_sha256' => DpsRestrictedSchemaSelection::ARCHIVE_SHA256,
            'country_list_qualified' => false, 'identity_authenticity_verified' => false,
            'residency_verified' => false, 'document_obligation_qualified' => false,
            'native_complete_document_validation_performed' => false, 'production_ready' => false];
    }

    private function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_check_encoding($value, 'UTF-8')
            && preg_match('/\A[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*\z/uD', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_DPS_FOREIGN_PARTY_'.$reason,
            'scope' => 'FOREIGN_PARTY_FRAGMENT_ONLY', 'xml' => null, 'production_ready' => false];
    }
}
