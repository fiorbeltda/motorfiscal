"""Preserve exact IBS regulation pages supporting conversion and partial export."""
from pathlib import Path
from pypdf import PdfReader
import hashlib, json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
source = ROOT / 'storage/app/phase4b-fx67-preparation-20261013/ibs-regulation.pdf'
expected = '9ed7032ef9c25bbee51bdb5e90f60e1ad01a2bf56cb41a28221c3db7f61d419a'
assert hashlib.sha256(source.read_bytes()).hexdigest() == expected
reader = PdfReader(source)
pages = {n: reader.pages[n - 1].extract_text() for n in [15, 16, 46]}
assert 'Art. 93.' in pages[46] and 'apenas a parcela' in pages[46] and '80' in pages[46]
conversion_text = ' '.join((pages[15] + pages[16]).split())
assert 'moeda estrangeira' in conversion_text and 'Banco Central' in conversion_text
target = E / 'ibs-conversion-and-partial-export-pages.txt'
with target.open('x', encoding='utf-8') as stream:
    for n, text in pages.items(): stream.write(f'PHYSICAL PAGE {n}\n{text}\n\n')
with (E / 'ibs-conversion-partial-export-source.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PRESERVED_PRIMARY_IBS_REGULATION_ART13_PAR4_AND_ART93_EXTRACTED',
               'source_path': source.relative_to(ROOT).as_posix(), 'source_sha256': expected,
               'physical_pages': list(pages), 'excerpt_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
               'observation_basis': 'PRIMARY_CAPTURE67_PRESERVED_NOT_NEW_DOWNLOAD_OR_TRANSACTION_AUTHENTICATION',
               'production_ready': False}, stream, indent=2)
print('PASS:IBS conversion and partial-export primary clauses preserved with physical pages and source hash')
