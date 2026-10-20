"""Independently verify wider public arithmetic, stock conservation, XML economics and immutable source bytes."""
from pathlib import Path
from decimal import Decimal, localcontext, ROUND_HALF_EVEN
import hashlib
import json
from xml.etree import ElementTree as ET
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}

def normalize_xml(path, removed):
    root = ET.fromstring(path.read_bytes())
    for parent in root.iter():
        for child in list(parent):
            if child.tag.split('}')[-1] in removed:
                parent.remove(child)
    if 'gUtilizBCN' in removed:
        for parent in root.iter():
            for child in list(parent):
                if child.tag.split('}')[-1] == 'infoFechamento' and len(child) == 0 and not (child.text or '').strip():
                    parent.remove(child)
    return ET.canonicalize(ET.tostring(root, encoding='unicode'), strip_text=True)

manifest = read(E / 'source-corpus-manifest.json')
capture = read(E / 'corpus-capture-validation.json')
assert capture['source_manifest_hash'] == digest(manifest)
assert len(manifest['cases']) == len(capture['cases']) == 20
assert {c['name'] for c in manifest['cases']} == {c['name'] for c in capture['cases']}
results = []
for case in manifest['cases']:
    name = case['name']
    for key in ['source_month', 'source_financial_input', 'source_monthly_report', 'source_d2101']:
        assert sha(ROOT / case[key]['path']) == case[key]['sha256']
    entry = next(c for c in capture['cases'] if c['name'] == name)
    snapshot_path = E / (name + '-snapshot.json')
    settlement_path = E / (name + '-settlement.json')
    report_path = E / (name + '-monthly-report.json')
    assert sha(snapshot_path) == entry['snapshot_sha256']
    assert sha(settlement_path) == entry['settlement_sha256'] and sha(report_path) == entry['report_sha256']
    for label in ['financial-check', 'settlement-capture-check', 'settlement-replay-check', 'monthly-capture-check', 'monthly-replay-check']:
        record = read(E / (name + '-' + label + '.json'))
        assert record['exit_code'] == 0 and record['log_sha256'] == sha(E / (name + '-' + label + '.log'))
    settlement = read(settlement_path)
    report = read(report_path)
    proposal = settlement['result']
    assert proposal == report['negative_base_settlement']
    assert proposal['original_gross_debits'] == settlement['input']['gross']
    assert proposal['original_gross_debits_hash'] == digest(settlement['input']['gross'])
    snapshot = read(snapshot_path)
    assert snapshot['input']['rule_bundle_id'] == 'CONSUMPTION-PHASE4B-2026.10.13-28-draft54'
    values = proposal['composition']['calculation_input']
    assert values['public_offer_debt_return'] == case['original_public_total']
    assert values['transition_present'] is False and values['iss_incident'] is False
    assert values['export_reversal'] == values['cooperative_reversal'] == '0.00'
    with localcontext() as context:
        context.prec = 100
        partial = Decimal(values['general_revenue']) + Decimal(values['public_offer_debt_return']) - Decimal(values['other_deductible_expenses']) - Decimal(values['allocated_funding_expenses'])
        divisor = Decimal(1) + sum((Decimal(r) for r in values['rates'].values()), Decimal(0)) / 100
        net = (partial / divisor).quantize(Decimal('.00000001'), rounding=ROUND_HALF_EVEN)
        positive = max(net, Decimal(0)).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN)
        assert Decimal(settlement['input']['operation']['base']['net_base']) == partial
        assert Decimal(proposal['composition']['calculation']['total_partial']) == partial
        taxes = {}
        for root, deduction in [('1010I', '50.00'), ('1010C', '10.00')]:
            pool = proposal['assessed_pools'][root]
            assert Decimal(pool['positive_base']) == positive and pool['deduction'] == deduction
            assert Decimal(pool['assessed_base']) == positive - Decimal(deduction)
        for tax, rate in values['rates'].items():
            root = '1010C' if tax == 'CBS' else '1010I'
            taxes[tax] = (Decimal(proposal['assessed_pools'][root]['assessed_base']) * Decimal(rate) / 100).quantize(Decimal('.01'), rounding=ROUND_HALF_EVEN)
            assert Decimal(proposal['proposed_tax_components'][tax]['nominal_amount']) == taxes[tax]
        assert Decimal(proposal['ibs_nominal_proposal']) == taxes['IBS_STATE'] + taxes['IBS_MUNICIPAL']
        assert Decimal(proposal['cbs_nominal_proposal']) == taxes['CBS']
    assert {row['codBCN']: row['available_after_proposed_consumption'] for row in proposal['stock_after_proposed_consumption']} == {
        '1010I20260600': '0.00', '1010C20260600': '80.00', '1010C20260801': '0.00'}
    before = read(ROOT / case['source_monthly_report']['path'])
    for event, output in report['event_outputs'].items():
        path = ROOT / output
        assert sha(path) == entry['xml_hashes'][event] == report['event_validations'][event]['xml_sha256']
        prior = ROOT / before['event_outputs'][event]
        if event == 'D1011':
            flags = [node for node in ET.fromstring(path.read_bytes()).iter() if node.tag.split('}')[-1] == 'indTribISS']
            assert len(flags) == 1 and flags[0].text == '0'
            assert not [node for node in ET.fromstring(prior.read_bytes()).iter() if node.tag.split('}')[-1] == 'indTribISS']
            assert normalize_xml(path, {'indTribISS'}) == normalize_xml(prior, set())
        elif event == 'D1199':
            assert not [node for node in ET.fromstring(prior.read_bytes()).iter() if node.tag.split('}')[-1] == 'infoFechamento']
            assert normalize_xml(path, {'gUtilizBCN', 'detBCNeg'}) == normalize_xml(prior, set())
        else:
            assert sha(path) == sha(prior), name + ':' + event
    assert not report['production_ready'] and not proposal['received_return_verified'] and not proposal['eligible_for_posting']
    assert entry['exact_settlement_replay'] and entry['exact_monthly_replay']
    results.append({'name': name, 'public_total': str(values['public_offer_debt_return']), 'base_after_gross_down': str(positive),
        'ibs': proposal['ibs_nominal_proposal'], 'cbs': proposal['cbs_nominal_proposal'], 'source_and_xml_economics_preserved': True})
with (E / 'corpus-development-validation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'WIDER_PUBLIC_SETTLEMENT_CORPUS_LOCALLY_VALIDATED', 'cases': results,
        'case_count': 20, 'xml_hashes': 120, 'exact_settlement_replays': 20, 'exact_monthly_replays': 20,
        'source_manifest': ref(E / 'source-corpus-manifest.json'), 'native_capture': ref(E / 'corpus-capture-validation.json'),
        'implementation': [ref(Path(__file__)), ref(ROOT / 'scripts/capture_phase4b_dere9121_56_public_corpus.py')],
        'no_movement_path_complete': False, 'public_version_chain_complete': False,
        'scope54_complete': False, 'phase4b_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS:20 own54 public cases,120 XML hashes,40 exact replays, independent decimal arithmetic and source economics')
