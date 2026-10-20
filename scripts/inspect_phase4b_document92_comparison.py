"""Record fresh documentary artifacts without changing regression91 inputs or activating schemas."""
from pathlib import Path
from datetime import datetime, timezone
from pypdf import PdfReader
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document92-primary-research-20261015')
OLD = ROOT / 'storage/app/phase4b-document74-primary-research-20261014'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p):
    with p.open('rb') as stream: return hashlib.file_digest(stream, 'sha256').hexdigest()
def save(name, value):
    with (E / name).open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
observed = read(E / 'primary-source-observation.json')
prior = {row['name']: row for row in read(OLD / 'primary-source-observation.json')['sources']}
comparison = []
for row in observed['sources']:
    assert sha(E / row['name']) == row['sha256']
    comparison.append({'name': row['name'], 'url': row['url'], 'observed_at': row['observed_at'],
        'content_verified': row['content_verified'], 'current_sha256': row['sha256'],
        'prior_sha256': prior.get(row['name'], {}).get('sha256'),
        'byte_identical_to74': row['sha256'] == prior.get(row['name'], {}).get('sha256')})
for name in ['nt009-v1-01.pdf', 'technical-act7.pdf', 'production-xsd-0.zip', 'restricted-xsd-0.zip']:
    row = next(row for row in comparison if row['name'] == name)
    assert row['content_verified'] and row['byte_identical_to74']
for name in ['nt009-v1-01.pdf', 'technical-act7.pdf']:
    pdf = PdfReader(E / name)
    with (E / (name + '.txt')).open('x', encoding='utf-8') as stream:
        for i, page in enumerate(pdf.pages, 1): stream.write(f'PHYSICAL PAGE {i}\n{page.extract_text()}\n\n')
closure = read(E / 'schema-closure-inspection.json')
fields = {row['archive']: {f['attributes']['name'] for f in row['observed_fields']} for row in closure['schemas']}
new_fields = {'nFatura', 'vFatura', 'nContCambio', 'nDUIMP', 'nDUE', 'vAjusteBCIBSCBSComExt'}
for archive, names in fields.items(): assert names.isdisjoint(new_fields), archive
saved = read(Path(r'D:\ProjetoSaasExports\phase4b-advance91-full-suite-20261015\preflight.json'))
assert all(sha(ROOT / path) == digest for path, digest in saved['input_hashes'].items())
save('document92-source-comparison.json', {'status': 'FRESH_DOCUMENTARY_RESEARCH_NOT_RUNTIME_IMPLEMENTATION',
    'created_at': datetime.now(timezone.utc).isoformat(), 'sources': comparison,
    'schema_closure_sha256': sha(E / 'schema-closure-inspection.json'),
    'new_comext_fields_missing_from_both_observed_closures': sorted(new_fields),
    'visual_review_pages': [16, 18],
    'visual_files': {name: sha(E / name) for name in ['nt009-page16.png', 'nt009-page18.png']},
    'schedule_fetch_verified': False, 'schedule_response_is_login_html_not_image': True,
    'previous_schedule_evidence_preserved_not_revalidated': True,
    'regression91_input_files_unchanged': len(saved['input_hashes']),
    'remote_deployment_verified': False, 'schema_activated': False,
    'document_obligation_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:4 binary artifacts unchanged;both XSD closures still lack6 NT009 fields;91 inputs intact')
