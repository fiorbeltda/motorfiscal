"""Verify borrower arithmetic before recording expected partial decisions and replays."""
import hashlib
import json
import subprocess
import sys
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = sys.argv[1] if len(sys.argv) > 1 else '2026.10.02-13'
CUT = VERSION.rsplit('-', 1)[-1]
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    process = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'artisan', *args],
        cwd=ROOT, capture_output=True, encoding='utf-8', check=False)
    value = json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT/path
    payload = json.dumps(value, ensure_ascii=False, indent=2)+'\n'
    if target.exists() and target.read_text(encoding='utf-8') != payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding='utf-8')


errors = {'benchmark-unproven': ('ACTION_REQUIRED', 'RTC_FINANCIAL_CREDIT_BENCHMARK_CALCULATION_UNPROVEN'),
    'identity-conflict': ('INVALID', 'RTC_FINANCIAL_CREDIT_PARTIES_OR_DOCUMENT_CONFLICT'),
    'category-conflict': ('INVALID', 'RTC_FINANCIAL_CREDIT_SCOPE_CONFLICT'),
    'missing-proof': ('ACTION_REQUIRED', 'RTC_FINANCIAL_CREDIT_PROOF_MISSING')}
for source in sorted((ROOT/f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}').glob('financial-borrower-credit-*.json')):
    name = source.stem.removeprefix('financial-borrower-credit-').removesuffix('-2026')
    prefix = f'storage/app/phase4b-financial-credit-{name}-20261002-{CUT}'
    snapshot = prefix+'-snapshot.json'
    captured = name in ['loan', 'receivable', 'mixed-extinction', 'public-debt', 'fee']
    args = ['fiscal:rtc:run', source.relative_to(ROOT).as_posix(), f'--bundle-manifest={BUNDLE}']
    if captured and not (ROOT/snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    status, reason = errors.get(name, ('ACTION_REQUIRED', 'RTC_FINANCIAL_DOCUMENT_PENDING'))
    assert value['status'] == status and value['reason_codes'] == [reason] and value['decision'] == [], value
    stages = {row['stage']: row for row in value['trace']}
    assert 'INPUT_CREDIT_TREATMENT' not in stages
    if name not in errors:
        credit = stages['FINANCIAL_BORROWER_CREDIT']
        if name == 'output-unassessed':
            assert credit['status'] == 'NOT_ASSESSED_ON_OUTPUT'
        else:
            expected = {'public-debt': 'DENIED', 'currency-denied': 'DENIED', 'unpaid': 'CONDITIONAL', 'mixed-extinction': 'MIXED'}.get(name, 'ALLOWED')
            assert credit['eligibility'] == expected and credit['appropriation_performed'] is False and credit['posting_performed'] is False
            for tax, rate in [('IBS', '0.10'), ('CBS', '0.90')]:
                amount = (Decimal('150')*Decimal(rate)/100).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
                if expected in ['DENIED', 'CONDITIONAL'] or (name == 'mixed-extinction' and tax == 'IBS'):
                    amount = Decimal('0.00')
                assert Decimal(credit['components'][tax]['requested_credit']) == amount, value
            if expected == 'ALLOWED' and name != 'fee':
                assert Decimal(credit['credit_base']) == Decimal('1200')-Decimal('1000')-Decimal('50')
            if name == 'fee':
                assert credit['credit_base'] is None
    if captured:
        save(prefix+'-result.json', value)
        assert execute(['fiscal:rtc:replay', snapshot])['replay_matches']
        print(name, value['decision_hash'])
    save(f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{source.name}',
        {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})

for name in ['bank-software', 'savings-bank-software', 'payment-institution-software']:
    filename = f'integrated-financial-credit-general-{name}-2026.json'
    prefix = f'storage/app/phase4b-financial-credit-general-{name}-20261002-{CUT}'
    snapshot = prefix+'-snapshot.json'
    args = ['fiscal:scenario:run', f'resources/fiscal/scenarios/consumption-integrated-{VERSION}/{filename}', f'--bundle-manifest={BUNDLE}']
    if not (ROOT/snapshot).exists():
        args.append(f'--snapshot={snapshot}')
    value = execute(args)
    assert value['status'] == 'VALID_WITH_WARNINGS' and value['missing_facts'] == [], value
    rtc = value['decision']['consumption_reform']['decision']
    assert rtc['financial_operation']['qualification']['regime'] == 'GENERAL_ART184'
    assert rtc['ibs']['nominal_amount'] == '1.00' and rtc['cbs']['nominal_amount'] == '9.00'
    assert value['decision']['iss']['calculation']['tax_amount'] == '20.00'
    assert value['decision']['federal_withholdings']['total_withholding'] == '61.50'
    save(prefix+'-result.json', value)
    assert execute(['fiscal:decision:replay', snapshot])['replay_matches']
    report_path, candidate = prefix+'-xml-report.json', prefix+'-candidate.xml'
    report = json.loads((ROOT/report_path).read_text(encoding='utf-8')) if (ROOT/report_path).exists() else execute([
        'fiscal:dps:project-xml', snapshot, 'resources/fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json',
        f'--bundle-manifest={BUNDLE}', f'--candidate-output={candidate}', f'--report={report_path}'])
    assert report['reason'] == 'RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT' and len(report['schema_validation']['errors']) == 1
    assert report['production_ready'] is False and report['decision_hash'] == value['decision_hash']
    assert hashlib.sha256((ROOT/candidate).read_bytes()).hexdigest() == report['schema_validation']['xml_sha256']
    save(f'resources/fiscal/expected/consumption-integrated-{VERSION}/{filename}',
        {key: value[key] for key in ['status', 'reason_codes', 'missing_facts', 'decision_hash']})
    print(name, value['decision_hash'])
for family, command in [('consumption-phase4b', 'fiscal:rtc:corpus'), ('consumption-integrated', 'fiscal:corpus:run')]:
    corpus = execute([command, f'resources/fiscal/scenarios/{family}-{VERSION}',
        f'--expected=resources/fiscal/expected/{family}-{VERSION}', f'--bundle-manifest={BUNDLE}'])
    assert corpus['valid'], corpus
    save(f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{CUT}.json', corpus)
gate = execute(['fiscal:rtc:production-assess', f'--bundle-manifest={BUNDLE}', f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json'])
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked'] == gate['official_source_hashes_valid'] == len(json.loads((ROOT/'resources/fiscal'/BUNDLE).read_text(encoding='utf-8'))['official_sources'])
save(f'storage/app/phase4b-production-assessment-20261002-{CUT}.json', gate)
print('Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates; blockers:', len(gate['blockers']))
