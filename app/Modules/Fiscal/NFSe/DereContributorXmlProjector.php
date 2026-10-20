<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DereContributorQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/** Original unsigned D1001; never implies registration in the government environment. */
final readonly class DereContributorXmlProjector
{
    public const CONTRACT = 'DERE-CONTRIBUTOR-LOCAL-2026.10.05-1';

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $catalogs
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function project(array $profile, array $identity, array $catalogs, DateTimeImmutable $knownAt, array $rule): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || LocalDate::fromString($rule['published_at'])->value() > $knownAt->format('Y-m-d')
                || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
                return $this->stop('RULE_UNAVAILABLE');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('RULE_UNAVAILABLE');
        }
        if (($rule['qualification_policy']['financial_evidence_required'] ?? false) === true
            && (! is_array($catalogs['servFinanc'] ?? null)
                || CanonicalJson::hash($catalogs['servFinanc']) !== ($rule['catalog_sha256'] ?? null))) {
            return $this->stop('ACTIVITY_CATALOG_POLICY_HASH_CONFLICT');
        }
        $qualification = (new DereContributorQualifier)->resolve($profile, $catalogs, $knownAt, $rule['qualification_policy'] ?? []);
        if ($qualification['status'] !== 'RESOLVED') {
            return $qualification;
        }
        $generated = $identity['generated_at'] ?? null;
        $sequence = $identity['event_sequence'] ?? null;
        if (! is_int($sequence) || $sequence < 1 || $sequence > 99999 || ! is_string($generated)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $generated)) {
            return $this->stop('EVENT_IDENTITY_INVALID');
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) < $profile['period_end'] || substr($generated, 0, 10) < $profile['observed_on']) {
            return $this->stop('GENERATION_DATE_CONFLICT');
        }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtInfoContrib/v1_0_1', 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtInfoContrib');
        $event->setAttribute('id', 'DeRE10011'.$profile['supplier_id'].$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($profile['supplier_id'], 0, 8));
        $period = $this->node($xml, $event, 'idePeriodo');
        $this->node($xml, $period, 'iniValid', $profile['valid_from']);
        if (isset($profile['valid_to'])) {
            $this->node($xml, $period, 'fimValid', $profile['valid_to']);
        }
        $info = $this->node($xml, $event, 'infoContrib');
        $this->node($xml, $info, 'regTribPrinc', $profile['regTribPrinc']);
        foreach ($profile['regTribSecund'] as $regime) {
            $this->node($xml, $info, 'regTribSecund', $regime);
        }
        $this->node($xml, $info, 'indNatTrib', $profile['indNatTrib']);
        foreach (['servFinanc', 'plAssistSaude', 'prognosticos'] as $name) {
            if (! isset($profile[$name])) {
                continue;
            }
            $group = $this->node($xml, $info, $name);
            $activities = $this->node($xml, $group, 'tpAtividades');
            foreach ($profile[$name]['tpAtividades'] as $code) {
                $this->node($xml, $activities, 'tpAtividade', $code);
            }
            if ($name === 'prognosticos' && isset($profile[$name]['UFsCredenc'])) {
                $ufs = $this->node($xml, $group, 'UFsCredenc');
                foreach ($profile[$name]['UFsCredenc'] as $uf) {
                    $this->node($xml, $ufs, 'UFCredenc', $uf);
                }
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_CONTRIBUTOR_LOCAL_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'schema' => 'evtInfoContrib-v1_0_1.xsd', 'qualification' => $qualification,
            'production_ready' => false, 'registration_performed' => false, 'signature_created' => false, 'transmission_performed' => false];
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
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_CONTRIBUTOR_'.$reason, 'production_ready' => false];
    }
}
