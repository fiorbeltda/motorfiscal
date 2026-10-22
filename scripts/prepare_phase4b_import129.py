"""Fork129 with exact import-responsibility references and a new immutable source observation."""
import argparse
import hashlib
import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
VERSION = '2026.10.22-129-draft1'
parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True, type=Path)
parser.add_argument('--capture', required=True, type=Path)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

def write(path, value):
    with path.open('x', encoding='utf-8', newline='\n') as handle:
        handle.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

capture = read(args.capture / 'capture.json')
raw = (args.capture / 'lc214compilado.htm').read_bytes()
assert len(raw) == capture['bytes'] == 4604674
assert hashlib.sha256(raw).hexdigest() == capture['sha256'] == '027797fa2a1f5ce9ebe34c7236766be4ef850127beb510eedb3b8315cf18d6ed'
assert capture['observed_at'] == '2026-10-22T16:40:39.031505+00:00'

def article64(content):
    text = html.unescape(re.sub('<[^>]+>', ' ', content.decode('windows-1252')))
    text = re.sub(r'\s+', ' ', text)
    return text[text.index('Art. 64.'):text.index('Art. 65.')].strip()

old = (F / 'sources/official/input119-lc214-compilado-observed117.html').read_bytes()
current_article = article64(raw)
assert current_article == article64(old), 'Review material legal changes before activation'
source_file = 'import129-lc214-compilado-observed20261022.html'
with (F / 'sources/official' / source_file).open('xb') as handle:
    handle.write(raw)
references = {}
for role, roman in [('ACQUIRER', 'V'), ('RECIPIENT', 'VI')]:
    article = f'LC214 art. 64, §5º, {roman}'
    references[role] = {'article': article, 'aliases': [f'LC214-ART64-{roman}', article, f'LC214.2025.ART64.PAR5.{roman}']}
policy = {'contract': 'IMPORT-RESPONSIBILITY-129-V1', 'available_at': capture['observed_at'],
    'source': {'id': 'IMPORT129_LC214_OBSERVED20261022', 'url': capture['url'],
        'sha256': capture['sha256'], 'observed_at': capture['observed_at']},
    'references': references, 'scope': 'QUALIFIED_IMPORT_TAXPAYER_AND_DECLARED_COLLECTION_ROUTE',
    'payment_proven': False, 'document_obligation_complete': False}
catalog = read(F / 'catalogs/rtc-codes-2026.10.22-128-draft1.json')
bundle = read(F / 'bundles/consumption-phase4b-2026.10.22-128-draft1.json')
controls = read(F / bundle['production_controls']['path'])
catalog['version'] = VERSION
catalog['import_responsibility_policy129'] = policy
catalog_path = F / f'catalogs/rtc-codes-{VERSION}.json'
write(catalog_path, catalog)
controls['version'] = VERSION
controls_path = F / f'production/phase4b-controls-{VERSION}.json'
write(controls_path, controls)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-'+VERSION,
    version=VERSION, knowledge_available_at=capture['observed_at'],
    notice='Import taxpayer and source-reference129;document obligation,actual discharge and full import XML remain separate work.')
bundle['catalog_versions']['rtc_codes'] = VERSION
bundle['resource_paths']['rtc_code_catalog'] = 'catalogs/'+catalog_path.name
bundle['content_hashes']['rtc_code_catalog'] = canonical(catalog)
bundle['production_controls'] = {'path': 'production/'+controls_path.name, 'sha256': canonical(controls)}
bundle['official_sources'].append({'id': policy['source']['id'], 'file': source_file, 'version': 'OBSERVED20261022',
    'url': capture['url'], 'sha256': capture['sha256'], 'observed_at': capture['observed_at'],
    'published_on': None, 'effective_from': None,
    'proven_effective_scope': 'Observed compiled law;art64 text matches preserved119. Local responsibility qualification;no production approval.'})
write(F / f'bundles/consumption-phase4b-{VERSION}.json', bundle)
write(args.output / 'preparation.json', {'status': 'PREPARED', 'source': capture,
    'article64_text_identical_to_preserved119': True,
    'article64_text_sha256': hashlib.sha256(current_article.encode()).hexdigest(),
    'policy': policy, 'policy_sha256': canonical(policy), 'official_sources': len(bundle['official_sources']),
    'full_suite_executed': False})
(args.output / 'article64-reviewed.txt').write_text(current_article, encoding='utf-8')
print(json.dumps({'status': 'PREPARED', 'policy_sha256': canonical(policy), 'sources': len(bundle['official_sources'])}))
