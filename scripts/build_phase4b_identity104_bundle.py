"""Derive104 from accepted101 without changing historical resources or numeric policies."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019')
S = Path(r'D:\ProjetoSaasExports\phase4b-identity103-local-preparation-20261016')
P = Path(r'D:\ProjetoSaasExports\phase4b-identity102-primary-preparation-20261016')
V = '2026.10.19-104-draft1'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def canonical(v):
    return hashlib.sha256(json.dumps(v, sort_keys=True, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

def save(p, v):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(v, stream, ensure_ascii=False, indent=2)

assert (E / 'before-integration-archive.json').exists()
paths = [F / f'{d}/{stem}-{V}.json' for d, stem in [('catalogs', 'rtc-codes'), ('facts', 'consumption-phase4b'),
    ('questions', 'consumption-integrated'), ('production', 'phase4b-controls'), ('bundles', 'consumption-phase4b')]]
component = ROOT / 'app/Modules/Fiscal/ConsumptionReform/PartialExportDpsIdentity103.php'
fixture = ROOT / 'tests/Fixtures/identity103-cases.json'
assert not any(p.exists() for p in [*paths, component, fixture])
prep = read(S / 'identity103-preparation-validation.json')
assert sha(S / 'PartialExportDpsIdentity103.php') == prep['native_file_hashes']['PartialExportDpsIdentity103.php']
assert sha(S / 'identity103-cases.json') == prep['native_file_hashes']['identity103-cases.json']
bundle = read(F / 'bundles/consumption-phase4b-2026.10.16-101-draft1.json')
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
cases = read(S / 'identity103-cases.json')
policy = cases['cases'][0]['policy']
assert policy['contract'] == 'DPS-PARTIAL-EXPORT-LOCAL-IDENTITY-2026.10.16-103'
observations = read(P / 'identity102-research-validation-v2.json')['sources']
annex_observations = read(P / 'annex-observation.json')
# Research report already contains both annex captures as well as the manual/ZIP.
all_sources = {r['file']: r for r in observations}
required = [('municipal_manual', S, read(S / 'municipal-source-observation.json'))]
for key, filename in [('contributor_manual', 'manual-api.pdf'), ('restricted_annex', 'annex-restricted.xlsx'),
                      ('production_annex_comparison_only', 'annex-production.xlsx'), ('restricted_xsd', 'restricted-xsd.zip')]:
    required.append((key, P, all_sources[filename]))
installed_sources = {}
for key, directory, source in required:
    src = directory / source['file']
    assert sha(src) == source['sha256'] == policy['sources'][key]
    target = F / 'sources/official' / ('identity104-' + key + '-observed20261016' + src.suffix)
    assert not target.exists()
    shutil.copy2(src, target)
    assert sha(target) == source['sha256']
    source_id = 'DPS104_' + key.upper()
    bundle['official_sources'].append({'id': source_id, 'url': source['url'], 'file': target.name,
        'sha256': source['sha256'], 'version': 'OBSERVED_2026.10.16', 'observed_at': source['observed_at'],
        'published_on': None, 'effective_from': None,
        'proven_effective_scope': 'Local composition/reference mapping only;historical source capture,not deployment,cadastral authorization or production approval.'})
    codes['sources']['identity104_' + key] = {'source_id': source_id, 'file': target.name,
        'sha256': source['sha256'], 'version': 'OBSERVED_2026.10.16', 'retrieved_on': '2026-10-16'}
    installed_sources[target.relative_to(ROOT).as_posix()] = sha(target)
codes['version'] = V
codes['partial_export_dps_identity_policy'] = policy
added = [('rtc.assess_export_dps_identity', 'BOOLEAN', 'Conferir composição e vínculo local dos dois IDsDPS após os resultados ISS nacional e municipalSP?'),
         ('rtc.export_dps_identity_records', 'ARRAY', 'Informe dois mapeamentos locais RTC_FOREIGN/RTC_DOMESTIC com referência original,identidadeDPS e prova;isso não autentica cadastro ou emissão.')]
rank = max(q['rank'] for q in questions)
for i, (key, kind, prompt) in enumerate(added, 1):
    assert not any(f['key'] == key for f in facts)
    facts.append({'key': key, 'type': kind, 'material': True})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + i, 'prompt': prompt})
controls['version'] = V
next(c for c in controls['controls'] if c['id'] == '4B.7.DOCUMENT')['gap'] += '104:composição e vínculo local dos IDsDPS após96/98;cadastro,autorização,unicidade remota,data material,conversão,bases e XML continuam sem aprovação.'
for role, value, path in zip(['rtc_code_catalog', 'fact_definitions', 'questions'], [codes, facts, questions], paths[:3]):
    save(path, value)
    bundle['resource_paths'][role] = path.relative_to(F).as_posix()
    bundle['content_hashes'][role] = canonical(value)
save(paths[3], controls)
bundle['production_controls'] = {'path': paths[3].relative_to(F).as_posix(), 'sha256': canonical(controls)}
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V, version=V,
    knowledge_available_at=datetime.now(timezone.utc).isoformat())
bundle['catalog_versions']['rtc_codes'] = V
save(paths[4], bundle)
# Mechanical namespace-only installation of the audited component; no behavioral rewrite.
raw = (S / 'PartialExportDpsIdentity103.php').read_bytes()
assert raw.count(b'namespace Phase4bPrepared;') == 1
with component.open('xb') as stream:
    stream.write(raw.replace(b'namespace Phase4bPrepared;', b'namespace App\\Modules\\Fiscal\\ConsumptionReform;'))
shutil.copy2(S / 'identity103-cases.json', fixture)
save(E / 'identity104-bundle-installation.json', {'created_at': datetime.now(timezone.utc).isoformat(),
    'files': {p.relative_to(ROOT).as_posix(): sha(p) for p in [*paths, component, fixture]},
    'source_files': installed_sources, 'added_facts': [row[0] for row in added],
    'official_sources': len(bundle['official_sources']), 'component_change': 'NAMESPACE_ONLY_FROM_AUDITED103',
    'full_suite_complete': False, 'production_ready': False})
print('PASS:104 own bundle/two facts/questions;71 source entries;103 namespace-only installation', flush=True)
