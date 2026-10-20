"""Prepare public-offer composition successor outside loaded application while53 runs."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
source = (ROOT / 'app/Modules/Fiscal/ConsumptionReform/DereFinancialCurrentBaseCompositionQualifier.php').read_text(encoding='utf-8')

def replace_once(old, new):
    global source
    assert source.count(old) == 1, old
    source = source.replace(old, new)

replace_once('final readonly class DereFinancialCurrentBaseCompositionQualifier', 'final readonly class DereFinancialPublicOfferCurrentBaseCompositionQualifier')
replace_once('array $rules, string $annexPath): array', 'array $rules, string $annexPath, array $returnBinding, string $sources): array')
replace_once('     * @param  array<string, mixed>  $rules', '     * @param  array<string, mixed>  $returnBinding\n     * @param  array<string, mixed>  $rules')
replace_once("if (($month['prerequisites']['D2101_required'] ?? null) !== false)", "if (($month['prerequisites']['D2101_required'] ?? null) !== true)")
replace_once("$excluded = ['110112001', '118915099'];", "$excluded = ['110112001', '118915099', '110113001', '110113002'];")
anchor = "        // Exact observed Table11 rows, not a tax-code prefix classifier."
insertion = '''        $debtProjection = $projection['debt_yield_projection'] ?? null;
        if (! is_array($debtProjection) || ! is_string($debtProjection['xml'] ?? null)
            || ! is_string($returnBinding['xml'] ?? null) || ($returnBinding['synthetic'] ?? null) !== true
            || ($returnBinding['pgcc_source_hash'] ?? null) !== $pgcc['source_hash']
            || ($returnBinding['expected_sequence'] ?? null) !== '00') {
            return $this->stop('INITIAL_SYNTHETIC_D9121_AND_PGCC_BINDING_REQUIRED');
        }
        $origin = new \\DOMDocument;
        $origin->loadXML($debtProjection['xml'], LIBXML_NONET);
        $originPath = new \\DOMXPath($origin);
        $originPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        if ($originPath->evaluate('string(/d:DeRE/d:evtDebOpOfPublic/d:ideEvento/d:tpOper)') !== '1') {
            return $this->stop('D2101_VERSION_CHAIN_QUALIFICATION_REQUIRED');
        }
        $scope = ['synthetic' => true, 'supplier_id' => $operation['qualification']['supplier_id'],
            'period' => substr($operation['base']['period_end'], 0, 7),
            'origin_xml_sha256' => hash('sha256', $debtProjection['xml']), 'expected_sequence' => '00',
            'pgcc_receipt' => $returnBinding['pgcc_receipt'] ?? null];
        $debtReturn = (new DerePublicOfferDebtReturnXmlReader)->read($returnBinding['xml'], $debtProjection['xml'], $scope, $sources);
        if (($debtReturn['status'] ?? null) !== 'LOCAL_D9121_AGGREGATE_RECONCILED'
            || ($debtReturn['total_vApur'] ?? null) !== ($debtProjection['total_vApur'] ?? null)) {
            return $this->stop('D9121_NATIVE_PROJECTION_TOTAL_CONFLICT');
        }
'''
replace_once(anchor, insertion + anchor)
replace_once("$net = $totals['general_revenue']->minus", "$net = $totals['general_revenue']->plus($debtReturn['total_vApur'])->minus")
replace_once("if (! $net->isEqualTo($operation['base']['net_base']))", "if ($classification !== '0' || ! $net->isEqualTo($operation['base']['net_base']))")
replace_once("'public_offer_debt_return' => '0.00', 'rates' => $rates", "'public_offer_debt_return' => $debtReturn['total_vApur'], 'rates' => $rates")
replace_once("'contract' => 'DERE-FINANCIAL-COMPOSITION-LOCAL-1.3.0-51',", "'contract' => 'DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-PROTOTYPE-54', 'd9121_reconciliation' => $debtReturn,")
with (E / 'planned-DereFinancialPublicOfferCurrentBaseCompositionQualifier.php').open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS: native monthly proof and D9121 composition prototype prepared; application unchanged')
