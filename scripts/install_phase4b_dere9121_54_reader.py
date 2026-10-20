"""Install exact audited reader prototype after full53 acceptance, preserving evidence."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
S = ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

accepted = json.loads((S / 'command-development-validation.json').read_text(encoding='utf-8'))
assert accepted['status'] == 'NATIVE_LOCAL_MONTHLY_BCN_SETTLEMENT_INTEGRATION_VALIDATED'
for item in accepted['implementation']:
    assert sha(ROOT / item['path']) == item['sha256']
prototype = json.loads((E / 'reader-preparation-validation.json').read_text(encoding='utf-8'))
for item in prototype['implementation']:
    assert sha(ROOT / item['path']) == item['sha256']
source = E / 'planned-DerePublicOfferDebtReturnXmlReader.php'
target = ROOT / 'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferDebtReturnXmlReader.php'
assert not target.exists()
with target.open('xb') as stream:
    stream.write(source.read_bytes())
with (E / 'reader-source-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'EXACT_AUDITED_READER_INSTALLED_PERMANENT_TESTS_PENDING',
        'target_path': target.relative_to(ROOT).as_posix(), 'installed_sha256': sha(target),
        'prototype_sha256': sha(source), 'prior53_acceptance_sha256': sha(S / 'command-development-validation.json'),
        'composition_integrated': False, 'temporal_policy_activated': False, 'scope54_complete': False,
        'production_ready': False}, stream, indent=2)
print('PASS: exact audited reader installed after53 acceptance; permanent tests and composition pending')
