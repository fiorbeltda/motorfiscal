<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Throwable;
use ZipArchive;

/** Versioned documentary field model, kept distinct from an XSD-valid complete DPS. */
final readonly class ImportNt009Model142
{
    public const CONTRACT = 'IMPORT-NT009-FIELD-MODEL-142-V1';

    public const AVAILABLE_AT = '2026-10-27T13:05:23+00:00';

    private const NT009_SHA256 = 'cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e';

    private const XSD_SHA256 = '25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267';

    private const CURRENCY_SHA256 = '4ef4ecd5574dd880f6ab49fcf20eaaefa8c6084ff2ca3a6463e02dd1c1478f1c';

    /** @param array<string,mixed> $snapshot Replayed141 knowledge/input/result.
     * @param  array<string,mixed>  $invoiceEvidence
     * @param  array<string,mixed>  $declaration  Explicit documentary COMEX declaration, not inferred from the service.
     * @return array<string,mixed>
     */
    public function project(array $snapshot, string $invoiceSource, array $invoiceEvidence, array $declaration, string $sourceRoot): array
    {
        $money = (new ImportDocumentMoney141)->reconcile($snapshot, $invoiceSource, $invoiceEvidence);
        if ($money['status'] !== 'LOCAL_NORMALIZED_INVOICE_MONETARY_RECONCILED') {
            return $money;
        }
        foreach (['catalog126-nt009-observed20261022.pdf' => self::NT009_SHA256,
            'bcb-currency-codes-observed-20261014.html' => self::CURRENCY_SHA256,
            'dps127-restricted-xsd-observed20261022-20261020.zip' => self::XSD_SHA256] as $file => $hash) {
            $path = $sourceRoot.'/'.$file;
            if (! is_file($path) || hash_file('sha256', $path) !== $hash) {
                return $this->stop('PRIMARY_SOURCE_HASH_MISMATCH');
            }
        }
        $keys = ['contract', 'invoice_id', 'evidence_id', 'observed_at', 'comext', 'payment_evidence_id'];
        if (count($declaration) !== count($keys) || array_diff(array_keys($declaration), $keys) !== []
            || ($declaration['contract'] ?? null) !== self::CONTRACT
            || ($declaration['invoice_id'] ?? null) !== $money['invoice_id']
            || ! is_string($declaration['evidence_id'] ?? null) || trim($declaration['evidence_id']) === '') {
            return $this->stop('EXPLICIT_BOUND_COMEXT_DECLARATION_REQUIRED');
        }
        $known = $this->instant($snapshot['input']['knowledge_at'] ?? null);
        $observed = $this->instant($declaration['observed_at'] ?? null);
        if ($known === null || $observed === null || $known < new DateTimeImmutable(self::AVAILABLE_AT) || $observed > $known) {
            return $this->stop('DECLARATION_OR_CONTRACT_NOT_KNOWN');
        }
        $trade = $declaration['comext'];
        $tradeKeys = ['mdPrestacao', 'vincPrest', 'tpMoeda', 'mecAFComexP', 'mecAFComexT', 'movTempBens', 'mdic', 'nContCambio'];
        if (! is_array($trade) || count($trade) !== count($tradeKeys) || array_diff(array_keys($trade), $tradeKeys) !== []) {
            return $this->stop('EXACT_COMEXT_FIELDS_REQUIRED');
        }
        if (($trade['mdPrestacao'] ?? null) !== '1' || ($trade['tpMoeda'] ?? null) !== '220'
            || ! in_array($trade['vincPrest'] ?? null, ['0', '1', '2', '3', '4', '5', '6'], true)
            || ($trade['mecAFComexP'] ?? null) !== '01' || ($trade['mecAFComexT'] ?? null) !== '01'
            || ($trade['movTempBens'] ?? null) !== '1' || ! in_array($trade['mdic'] ?? null, ['0', '1'], true)) {
            return $this->stop('COMEXT_PROFILE_OR_FACET_NOT_MAPPED');
        }
        // Numeric identity in the observed NT009 table; never strip letters, punctuation or leading zeroes.
        if (! is_string($money['invoice_id']) || preg_match('/\A[0-9]{1,20}\z/', $money['invoice_id']) !== 1) {
            return $this->stop('ORIGINAL_INVOICE_ID_NOT_REPRESENTABLE_IN_OBSERVED_NFATURA_FACET');
        }
        $payment = $trade['nContCambio'];
        if (($payment === null && $declaration['payment_evidence_id'] !== null)
            || ($payment !== null && (! is_string($payment) || preg_match('/\A[0-9]{1,20}\z/', $payment) !== 1
                || ! is_string($declaration['payment_evidence_id']) || trim($declaration['payment_evidence_id']) === ''))) {
            return $this->stop('PAYMENT_REFERENCE_FACET_OR_EVIDENCE_REQUIRED');
        }
        $fields = [];
        foreach (array_diff($tradeKeys, ['nContCambio']) as $field) {
            $fields['DPS.infDPS.serv.comExt.'.$field] = $trade[$field];
        }
        if ($payment !== null) {
            $fields['DPS.infDPS.serv.comExt.nContCambio'] = $payment;
        }
        $fields['DPS.infDPS.serv.comExt.nFatura'] = $money['invoice_id'];
        $fields['DPS.infDPS.serv.comExt.vFatura'] = $money['original_service_price'];
        $fields['DPS.infDPS.valores.vServPrest.vServ'] = $money['iss_document_price_brl'];
        $fields['DPS.infDPS.valores.vAjusteBC.vAjusteBCIBSCBSComExt'] = $money['fx_adjustment_brl'];
        $schema = $this->coverage($sourceRoot.'/dps127-restricted-xsd-observed20261022-20261020.zip');
        if ($schema === null) {
            return $this->stop('OBSERVED_SCHEMA_NOT_READABLE');
        }

        return ['status' => $schema['absent_elements'] === [] ? 'FIELD_MODEL_REQUIRES_FULL_SCHEMA_PATH_VALIDATION' : 'FIELD_MODEL_PREPARED_OBSERVED_SCHEMA_INCOMPATIBLE',
            'contract' => self::CONTRACT, 'scope' => 'NT009_COMEXT_AND_MONETARY_FIELDS_ONLY',
            'fields' => $fields, 'monetary_reconciliation' => $money, 'declaration_sha256' => CanonicalJson::hash($declaration),
            'source_nt009_sha256' => self::NT009_SHA256, 'currency_reference_sha256' => self::CURRENCY_SHA256,
            'schema_coverage' => $schema, 'remaining' => ['Complete party/identity/DPS assembly', 'Compatible official XSD and full document validation', 'Other COMEX profiles and source qualification'],
            'comext_mode_fiscally_qualified' => false, 'currency_competence_validity_proven' => false,
            'payment_authenticated' => false, 'fiscal_decision_final' => false, 'xml_generated' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @return array<string,mixed>|null */
    private function coverage(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $names = [];
        $files = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name) || ! str_ends_with($name, '.xsd')) {
                    continue;
                }
                $body = $zip->getFromIndex($i);
                if (! is_string($body)) {
                    return null;
                }
                $dom = new DOMDocument;
                if (! $dom->loadXML($body, LIBXML_NONET)) {
                    return null;
                }
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
                $elements = $xpath->query('//xs:element[@name]');
                if ($elements === false) {
                    return null;
                }
                foreach ($elements as $element) {
                    $names[(string) $element->attributes?->getNamedItem('name')?->nodeValue] = true;
                }
                $files[] = $name;
            }
        } finally {
            $zip->close();
        }
        $required = ['nFatura', 'vFatura', 'nContCambio', 'vAjusteBCIBSCBSComExt'];

        return ['source_sha256' => self::XSD_SHA256, 'xsd_files_inspected' => $files,
            'absent_elements' => array_values(array_diff($required, array_keys($names))),
            'element_presence_proves_path_or_facets' => false, 'schema_modified' => false];
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            return null;
        }
        try {
            $instant = new DateTimeImmutable($value);

            return DateTimeImmutable::getLastErrors() === false ? $instant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'IMPORT_NT009142_'.$reason,
            'fiscal_decision_final' => false, 'xml_generated' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }
}
