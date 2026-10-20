"""Verify mixed public history by actual replays, gross monthly journals and XSD bodies."""
import hashlib
import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from decimal import Decimal, ROUND_HALF_EVEN
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
V = '2026.10.08-28-draft21'
E = ROOT / 'storage/app/phase4b-realization-public-mixed-history-draft-20261008-28'
S = F / ('scenarios/tvm-public-mixed-history-' + V)
B = 'bundles/consumption-phase4b-' + V + '.json'
CASES = ['negative-partial', 'negative-complete', 'positive-partial', 'zero-partial', 'fund-partial']
VARIANTS = ['net-negative', 'net-positive', 'net-zero', 'fund-net-negative']

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def save(path, value):
    if path.exists():
        assert read(path) == value, path
    else:
        with path.open('x', encoding='utf-8') as stream:
            stream.write(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

docker = ['docker', 'compose', 'exec', '-T', 'app']
commands = {
    'tests': docker + ['vendor/bin/pest', '--compact'],
    'mixed-history-tests': ['docker','compose','exec','-T','-e','TVM_PUBLIC_MIXED_HISTORY_CAPTURE=1','app','vendor/bin/pest','tests/Fiscal/Unit/DereSecuritiesPublicMixedHistoryTest.php','--compact'],
    'phpstan': docker + ['vendor/bin/phpstan','analyse','--memory-limit=512M','--no-progress'],
    'pint': docker + ['vendor/bin/pint','--test'],
    'negative-public-replays': ['python','scripts/verify_phase4b_tvm_public_negative_yield_draft.py','corpus'],
    'intraday-replays': ['python','scripts/verify_phase4b_tvm_intraday_yield_draft.py','corpus'],
    'public-deferral-replays': ['python','scripts/verify_phase4b_tvm_public_deferral_draft.py','corpus'],
    'public-fair-replays': ['python','scripts/verify_phase4b_tvm_public_fair_draft.py','corpus'],
    'regression27': ['python','scripts/verify_phase4b_dere_variable_yield_cut.py'],
    'regression26': ['python','scripts/verify_phase4b_dere_daily_memory_cut.py'],
    'regression25': ['python','scripts/verify_phase4b_dere_yield_cut.py'],
}
name = sys.argv[1]
if name in commands:
    log = E / (name + '.log')
    record = E / (name + '.json')
    assert not log.exists() and not record.exists(), 'Refusing to overwrite evidence.'
    process = subprocess.run(commands[name], cwd=ROOT, capture_output=True, encoding='utf-8')
    with log.open('x', encoding='utf-8') as stream:
        stream.write(process.stdout + process.stderr)
    summary = {'command':commands[name], 'exit_code':process.returncode, 'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest()}
    match = re.search(r'(\d+) passed.*?\((\d+) assertions\)', re.sub(r'\x1b\[[0-9;]*m', '', process.stdout + process.stderr))
    if match:
        summary.update(tests=int(match[1]), assertions=int(match[2]))
    save(record, summary)
    print(json.dumps(summary))
    sys.exit(process.returncode)

def run(args, code=0):
    process = subprocess.run(docker + ['php','artisan',*args], cwd=ROOT, capture_output=True, encoding='utf-8')
    assert process.returncode == code, process.stdout[:2000] + process.stderr
    return json.loads(process.stdout)

if name == 'corpus':
    prior_cases, cases = [], []
    xml_count = 0
    for variant in VARIANTS:
        for period in ['2026-07', '2026-08']:
            snapshot = E / (variant + '-' + period + '-snapshot.json')
            stored = read(snapshot)
            base = next(t['base'] for t in stored['result']['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE')
            binding = base['entries'][0]['d2101_binding']
            assert base['net_base']==binding['taxable_amount'] and base['deductions_before_art188']=='0.00'
            if binding['gross_nature']=='D':
                assert binding['taxable_amount']=='0.00' and binding['art195_excess']=='0.00' and Decimal(binding['unoffset_title_loss'])>0
            replay = run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
            assert replay['replay_matches']
            save(E / (variant + '-' + period + '-replay.json'), replay)
            prior_cases.append({'variant':variant,'period':period,'gross_nature':binding['gross_nature'],'base':binding['taxable_amount'],'replay_matches':True})
    for case in CASES:
        fixture = read(S / (case + '-current.json'))
        source = S / (case + '-financial.json')
        snapshot = E / (case + '-snapshot.json')
        args = ['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
        if not snapshot.exists():
            args += ['--snapshot='+snapshot.relative_to(ROOT).as_posix()]
        result = run(args)
        assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
        stages = {t['stage']:t for t in result['trace']}
        base = stages['FINANCIAL_OPERATION_AND_BASE']['base']
        tax = stages['FINANCIAL_GROSS_DEBITS']
        assert base['net_base']==fixture['expected']['base'] and base['deductions_before_art188']=='300.00' and tax['base']==base['net_base']
        for field, rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:
            assert Decimal(tax[field])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
        parent = base['realization_bindings']['tvm1']
        history = parent['analysis']['replayed_fiscal_history']
        binding = next(e['d2101_binding'] for e in base['entries'] if e['id']=='yield1')
        assert parent['financial_entries']==[] and parent['revenue']=='0.00' and parent['deductions']=='0.00'
        assert history['public_position_chain_replayed'] and history['fiscal_snapshots_replayed']==2
        assert history['opening_fiscal_components']==fixture['expected']['opening_fiscal_components']
        assert not history['historical_public_loss_current_deduction_created'] and not history['historical_public_loss_cross_title_offset_created']
        assert parent['analysis']['recognition_inventory']['closing_fiscal_components']==fixture['recognition_inventory']['closing_fiscal_components']
        assert binding['xml_title']['vVarMensal']=='50.00' and binding['book_apur_amount']=='0.00' and binding['taxable_amount']==fixture['expected']['auxiliary_base']
        replay = run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
        assert replay['replay_matches']
        save(E / (case + '-replay.json'), replay)
        month = S / (case + '-month.json')
        report = E / (case + '-monthly-report.json')
        args = ['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
        if not report.exists():
            args += ['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
        projected = run(args)
        stored = read(report)
        for field in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:
            assert projected[field]==stored[field]
        assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'} and not stored['production_ready'] and not stored['projection']['period_closed']
        assert stored['projection']['debt_yield_projection']['total_vApur']==binding['taxable_amount']
        for event, file in stored['event_outputs'].items():
            raw = (ROOT/file).read_bytes()
            validation = stored['event_validations'][event]
            assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
            assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest() and validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
            xml = ET.fromstring(raw)
            ns = {'d':xml.tag.split('}')[0][1:]}
            if event=='D2101':
                titles = xml.findall('.//d:detTitulo',ns)
                assert len(titles)==1
                for field, value in binding['xml_title'].items():
                    assert (titles[0].get(field) if field in ['idTitulo','cCta'] else titles[0].find('d:'+field,ns).text)==value
            if event=='D1101':
                accounts = {r.find('d:cCta',ns).text:r for r in xml.findall('.//d:infoConta',ns)}
                assert accounts['curve000'].find('d:vApur',ns).text=='0.00'
                for account in read(month)['book']['accounts']:
                    for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:
                        assert accounts[account['cCta']].find('d:'+field,ns).text==account[field]
            xml_count += 1
        cases.append({'case':case,'base':base['net_base'],'auxiliary_base':binding['taxable_amount'],'opening_components':history['opening_fiscal_components'],
                      'closing_components':fixture['recognition_inventory']['closing_fiscal_components'],'prior_return_retaxed':False,'prior_loss_current_deduction_created':False,'replay_matches':True,'monthly_projected':True})
    save(E / 'corpus.json', {'prior_cases':prior_cases,'cases':cases,'financial_replays':13,'xml_bodies':xml_count,'scope28_completed':False,'production_ready':False})
    print(json.dumps({'prior_cases':len(prior_cases),'current_cases':len(cases),'financial_replays':13,'xml_bodies':xml_count}))
    sys.exit(0)

if name == 'gate':
    result = run(['fiscal:rtc:production-assess','--bundle-manifest='+B,'--controls=resources/fiscal/production/phase4b-controls-'+V+'.json'],1)
    assert not result['ready'] and result['official_source_hashes_valid']==49 and 'error' not in result
    save(E / 'production-assessment.json', result)
    print(json.dumps({'ready':False,'official_sources':49}))
    sys.exit(0)

assert name == 'report'
quality = {key:read(E/(key+'.json')) for key in commands}
assert all(v['exit_code']==0 for v in quality.values())
baseline = read(E / 'historical-before.json')
changed = [p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h]
assert not changed, changed
corpus = read(E / 'corpus.json')
gate = read(E / 'production-assessment.json')
assert len(corpus['cases'])==5 and len(corpus['prior_cases'])==8 and corpus['xml_bodies']==30 and not gate['ready']
for official in read(F/B)['official_sources']:
    assert hashlib.sha256((F/'sources/official'/official['file']).read_bytes()).hexdigest()==official['sha256']
report = {'status':'DRAFT_REPLAYED_CONSECUTIVE_PUBLIC_MIXED_HISTORY_PROGRESS_ONLY','scope28_completed':False,'latest_locally_accepted_candidate':'2026.10.06-27',
          'prior_development_bundles':['2026.10.08-28-draft19','2026.10.08-28-draft20'],'development_bundle':V,'quality':quality,'corpus':corpus,
          'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'production_ready':False,
          'limits':['Consecutive fully retained public yield months; only purchases in the first month and no intermediate movements',
                    'Separate prior fiscal components are released without current retaxation or automatic deduction of unoffset title loss',
                    'Synthetic proofs and unsigned XML do not establish PGCC reception, transmission or homologation'],
          'pending':['Bare new produced return at sale without proved appropriation','Intervening movements and additional titles/funds/rounding combinations','Integral scope28 acceptance and remaining phase4b requirements']}
save(E / 'progress-report.json', report)
print(json.dumps({'status':report['status'],'historical_artifacts':len(baseline),'official_sources':49,'production_ready':False}))
