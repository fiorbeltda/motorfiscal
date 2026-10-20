"""Freeze source requirements for current financial bases before BCN consumption."""
from pathlib import Path
import hashlib
import json
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
S = ROOT / 'resources/fiscal/sources/official'
E = ROOT / 'storage/app/phase4b-negative-base51-current-bases-preparation-20261009'
P = ROOT / 'storage/app/phase4b-negative-base50-closing-draft-20261009'
E.mkdir(exist_ok=True)


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def preserve(name, raw):
    path = E / name
    if path.exists():
        assert path.read_bytes() == raw, path
    else:
        with path.open('xb') as stream:
            stream.write(raw)
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}


sources = []
for filename, pages in [('dere-observed-20261005-mod.pdf', [43, 44, 45, 46, 47, 150]),
                        ('dere-observed-20261005-annex-i.pdf', [63])]:
    path = S / filename
    reader = PdfReader(path)
    text = '\n\n'.join(f'PHYSICAL PAGE {page}\n{reader.pages[page-1].extract_text()}' for page in pages)
    excerpt = preserve(filename + '.excerpt.txt', text.encode('utf-8'))
    sources.append({'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path),
                    'physical_pages': pages, 'excerpt': excerpt})

snapshot_path = P / 'command-financial-snapshot.json'
snapshot = json.loads(snapshot_path.read_text(encoding='utf-8'))
stages = {row['stage']: row for row in snapshot['result']['trace']}
operation = stages['FINANCIAL_OPERATION_AND_BASE']
gross = stages['FINANCIAL_GROSS_DEBITS']
assert operation['base']['net_base'] == gross['base'] == '900.00'
assert gross['scope'] == 'GROSS_DEBITS_BEFORE_CREDITS_PERIOD_SETTLEMENT_AND_DOCUMENT'
annex = (E / 'dere-observed-20261005-annex-i.pdf.excerpt.txt').read_text(encoding='utf-8')
assert all(token in annex for token in ['1005I', '1005C', '1010I', '1010C', 'Gross-Down'])

record = {
    'status': 'CURRENT_FINANCIAL_BASES_SOURCE_CONTRACT_PREPARED_NOT_IMPLEMENTED',
    'sources': sources,
    'current_snapshot': {'path': snapshot_path.relative_to(ROOT).as_posix(), 'sha256': sha(snapshot_path)},
    'observed_current_base': '900.00',
    'observed_current_scope': gross['scope'],
    'requirements': {
        'roots': {'1005': ['1005I', '1005C'], '1010': ['1010I', '1010C']},
        'classification': 'Prove forming codBC and ISS incidence using captured PGCC and official tables',
        'composition': ['taxable receipts', 'transition receipts', 'public-body receipts',
                        'deductible expenses', 'allocated funding expenses', 'deduction reversals'],
        '1010_additional_input': 'Public-offer debt revenue from qualified D9121 vApurTot, without duplication',
        'transition': 'Prove general/transition proportions before embedded-tax extraction',
        'positive_ibs': 'General net base plus transition net base, after gross-down',
        'positive_cbs': 'General net base only, after gross-down',
        'bcn_order': 'Gross-down first, prior BCN consumption second, tax calculation third',
        'generation': 'Negative current base yields zero taxable positive base and separately generated BCN',
        'conservation': 'Preserve forming code, tax pool, supplier, period and source-bound composition',
        'rounding': 'Prove intermediate and final precision against official contract',
        'integration': 'Native snapshot/replay, prior active return chain and existing consumption planner',
    },
    'unproven_current_snapshot_requirements': [
        'general versus transition composition', 'root-specific gross-down result',
        'separate current IBS and CBS bases', 'authoritative D9121 total where applicable'],
    'existing_net_base_may_be_used_as_post_gross_down_bcn_base': False,
    'current_bases_implementation_complete': False,
    'bcn_tax_recalculation_integrated': False,
    'scope28_complete': False, 'phase4b_complete': False, 'production_ready': False,
}
preserve('current-bases-research-contract.json',
         (json.dumps(record, ensure_ascii=False, indent=2) + '\n').encode('utf-8'))
print('PASS: official current-base requirements frozen; 900.00 baseline scope identified; '
      'post-gross-down BCN bases and tax integration remain unproven')
