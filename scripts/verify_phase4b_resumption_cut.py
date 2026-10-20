"""Record the 2026-10-05 local cut from completed checks; preserve historical evidence."""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/phase4b-resumption-20261005-1'


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def log(name):
    value = (OUT / name).read_text(encoding='utf-8-sig')
    return re.sub(r'\x1b\[[0-9;]*m', '', value)


def tests(name):
    match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', log(name))
    assert match is not None, name
    return {'tests': int(match[1]), 'assertions': int(match[2]), 'log': name}


history = json.loads((OUT / 'history-before.json').read_text(encoding='utf-8-sig'))
changed = [row['path'] for row in history if not (ROOT / row['path']).is_file()
           or sha256(ROOT / row['path']) != row['sha256']]
assert changed == [], changed
directed = tests('contributor-monthly-final-tests.log')
assert directed['tests'] == 116 and directed['assertions'] == 236
assert '[OK] No errors' in log('phpstan-final.log')
assert re.search(r'PASS\s+\.+ 353 files', log('pint-after-command-test.log'))
replay = log('candidate17-final-verifier.log')
expected_xml = {
    'D1101': 'ae99d818eafb9ec05afd6a9ec008b625ade236e1a4ee8a6bb293b5223b96b0db',
    'D1121': 'e76f7ae1a1b2521f976f09344b65e062ed230f3db7e9acc6f9bbc4e234d2de25',
    'D1199': 'c3399dd2d66681d8ddedf9651f0792b39c888542584f2a50f46bf962ffe232cf',
}
for event, expected in expected_xml.items():
    assert event + ' ' + expected in replay
assert 'blockers: 119' in replay
gate = json.loads((ROOT / 'storage/app/phase4b-production-assessment-20261002-17.json').read_text(encoding='utf-8'))
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_source_hashes_valid'] == gate['official_sources_checked'] == 32
assert len(gate['blockers']) == 119
files = [
    'app/Modules/Fiscal/ConsumptionReform/DereContributorQualifier.php',
    'app/Modules/Fiscal/NFSe/DereContributorXmlProjector.php',
    'app/Modules/Fiscal/NFSe/DereMonthlyClosingXmlProjector.php',
    'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php',
    'tests/Fiscal/Unit/DereContributorQualifierTest.php',
    'tests/Fiscal/Unit/DerePgccQualifierTest.php',
    'tests/Fiscal/Unit/DereMonthlyClosingXmlProjectorTest.php',
    'tests/Fiscal/Integration/DereMonthlyClosingCommandTest.php',
]
report = {
    'date': '2026-10-05',
    'status': 'DEVELOPMENT_CUT_VERIFIED_PHASE4B_INCOMPLETE',
    'production_ready': False,
    'candidate_bundle': 'CONSUMPTION-PHASE4B-2026.10.02-17',
    'new_bundle_created': False,
    'new_paths_activated_in_candidate': False,
    'defaults': {'general': 14, 'D1101': 15, 'D1121': 16, 'monthly': 17},
    'runtime': 'Docker PHP 8.5.10 / PostgreSQL 18; existing volumes preserved',
    'directed': directed,
    'full_suite_before_last_command_test': tests('full-suite.log'),
    'phpstan': {'exit_code': 0, 'log': 'phpstan-final.log'},
    'pint': {'exit_code': 0, 'files': 353, 'log': 'pint-after-command-test.log'},
    'candidate17_verifier': {'exit_code': 0, 'log': 'candidate17-final-verifier.log', 'xml_sha256': expected_xml},
    'historical_artifacts': {'checked': len(history), 'changed': changed},
    'gate': {'ready': False, 'eligible_for_local_validation': True, 'sources': 32, 'blockers': 119},
    'new_positive_evidence_scope': 'SYNTHETIC_QUALIFIED_REGISTRATION_ACTIVITY_REFERENCE_AND_AUXILIARY_FIXTURES_WITH_OFFICIAL_XSD',
    'monthly_test_events': ['D1001', 'D1011', 'D1101', 'D1121', 'D1199'],
    'limitations': [
        'New PGCC/D1001 paths require qualified official catalogs and activation in a new version',
        'D1001 supports original inclusion and externally qualified ACTIVE registration only',
        'Historical reference rows use one interval; no automatic multiple version resolution',
        'D1106/D2101, negative bases, reopening and financial ISS/4A integration remain incomplete',
        'Remaining phase4B families, sources and homologation remain incomplete',
        'Unsigned XSD body check does not prove government reception',
    ],
    'actions': {'registration_performed': False, 'signature_created': False,
                'transmission_performed': False, 'collection_performed': False,
                'commit_created': False, 'push_performed': False},
    'files_sha256': {name: sha256(ROOT / name) for name in files},
    'logs_sha256': {path.name: sha256(path) for path in sorted(OUT.glob('*.log'))},
}
payload = json.dumps(report, ensure_ascii=False, indent=2) + '\n'
target = OUT / 'verification-report.json'
if target.exists():
    assert target.read_text(encoding='utf-8') == payload, 'Refusing to overwrite a different verification report'
else:
    with target.open('x', encoding='utf-8') as stream:
        stream.write(payload)
print(f"Verified {len(history)} historical hashes; {directed['tests']} directed tests; phase4B remains incomplete.")
