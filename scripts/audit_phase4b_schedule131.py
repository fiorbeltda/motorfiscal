"""Compare32 schedule dates independently to the preserved act,then audit native/evidence integrity."""
import argparse
import copy
import hashlib
import json
import os
import re
from datetime import datetime, timezone
from pathlib import Path
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
parser = argparse.ArgumentParser()
parser.add_argument('--evidence', type=Path, required=True)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--draft2', action='store_true')
args = parser.parse_args()
assert not args.output.exists()
checks = 0

def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

def require(test, label):
    global checks
    if not test:
        raise AssertionError(label)
    checks += 1

version = '2026.10.22-131-draft2' if args.draft2 else '2026.10.22-131-draft1'
native_folder = 'native-draft2-v1' if args.draft2 else 'native-v1'
bundle_path = F / ('bundles/consumption-phase4b-'+version+'.json')
bundle = read(bundle_path)
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
policy = codes['document_schedule_policy131']
require(canonical(policy) == 'c3c1b77a4e391686bdf7581a0fe482c08f3a0db12d9e9b7fc740531926af1f56', 'policy131 exact binding')
require(canonical(codes) == bundle['content_hashes']['rtc_code_catalog'], 'catalog131 exact binding')
base = read(ROOT / 'storage/app/phase4b-import130-development-20261022/import130-candidate-validation.json')
prior_bundle = read(F / base['fiscal_bundle'])
require(sha(F / base['fiscal_bundle']) == base['fiscal_bundle_sha256'], 'prior manifest unchanged')
prior_codes = read(F / prior_bundle['resource_paths']['rtc_code_catalog'])
expected = copy.deepcopy(prior_codes)
expected['version'] = version
expected['document_schedule_policy131'] = policy
require(expected == codes, 'only declared catalog additions')
if args.draft2:
    for key in ['fact_definitions', 'questions']:
        old_resource=read(F/prior_bundle['resource_paths'][key]);new_resource=read(F/bundle['resource_paths'][key])
        require(new_resource[:-1]==old_resource, 'preserved prior '+key)
        require(new_resource[-1].get('key',new_resource[-1].get('fact_key'))=='rtc.recipient_regime','recipient fact/question appended')
        require(canonical(new_resource)==bundle['content_hashes'][key],'new fact/question bound')
for source in bundle['official_sources']:
    require(sha(F / 'sources/official' / source['file']) == source['sha256'], 'official file '+source['id'])
require(bundle['official_sources'] == prior_bundle['official_sources'], 'source inventory unchanged')
pdf = F / 'sources/official' / policy['source_file']
require(sha(pdf) == policy['source_sha256'], 'primary act source bytes')
reader = PdfReader(pdf)
require(len(reader.pages) == 3, 'three complete act pages')
text = re.sub(r'\s+', ' ', ' '.join(page.extract_text() for page in reader.pages))
require('Publicado em: 31/07/2026' in text and 'entra em vigor na data de sua publicação' in text, 'publication and effect explicit')
start = text.index('Art. 1º')
stop = text.index('§ 1º Para os contribuintes', start)
body = text[start:stop]
matches = list(re.finditer(r' (?P<roman>[IVX]+) - ', body))
sections = {m['roman']: body[m.end():matches[i+1].start() if i+1 < len(matches) else len(body)] for i,m in enumerate(matches)}
months = {'janeiro':'01','fevereiro':'02','março':'03','abril':'04','maio':'05','junho':'06','julho':'07','agosto':'08','setembro':'09','outubro':'10','novembro':'11','dezembro':'12'}

def last_date(segment):
    dates = re.findall(r'(\d{1,2})(?:º)? de ('+'|'.join(months)+r') de (\d{4})', segment)
    assert dates, segment
    day, month, year = dates[-1]
    return f'{year}-{months[month]}-{int(day):02d}'

for row in policy['rows']:
    locator = row['locator'].removeprefix('art1 ')
    if locator == '§3':
        require('em substituição ao prazo previsto no inciso I' in text and 'prazo previsto no inciso XIV' in text, 'import-goods exception refers to XIV')
        segment = sections['XIV']
    elif locator.startswith('§'):
        number = locator[1:]
        segment = text.split('§ '+number+'º',1)[1].split('§ ',1)[0]
    else:
        parts = locator.split()
        segment = sections[parts[0]]
        if len(parts) > 1:
            letter = parts[1]
            segment = re.split(r' '+letter+r'\) ', ' '+segment, maxsplit=1)[1]
            segment = re.split(r' [a-h]\) ', segment, maxsplit=1)[0]
    require(last_date(segment) == row['mandatory_from'], 'date from primary text '+locator)
require(len(policy['rows']) == 32 and len({r['document'] for r in policy['rows']}) == 18, 'full document/profile inventory')
require(last_date(text.split('§ 1º Para os contribuintes',1)[1].split('§ 2º',1)[0]) == policy['simples_mandatory_from'], 'Simples exception from text')
require('dia 15 do mês seguinte' in text and 'período de apuração de outubro de 2026' in text, 'monthly deadline and first period explicit')
for page in range(1,4):
    require((args.evidence / f'primary-source-v2/page-{page}.png').is_file(), 'rendered page available')
native = read(args.evidence / native_folder / 'validation.json')
require(native['complete'] and native['exit_code'] == 0 and native['native_decisions'] == native['exact_replays'] == (18 if args.draft2 else 12), 'native terminal population')
snapshots = {}
for item in native['snapshots']:
    p = args.evidence / native_folder / item['file']
    require(sha(p) == item['sha256'] and item['replay_matches'], 'saved native snapshot')
    key = item['case'], item['runner'], item['version']
    require(key not in snapshots, 'unique native context')
    snapshots[key] = read(p)

def assessment(s, runner):
    return s['result'] if runner == 'rtc' else next(x['payload'] for x in s['result']['trace'] if x['type']=='CONSUMPTION_REFORM')

for case in ['domestic', 'import', 'import-platform']:
    for runner in ['rtc','integrated']:
        old,new = snapshots[case,runner,'129'],snapshots[case,runner,'131']
        require(old['input']['facts']==new['input']['facts'], 'literal native facts unchanged')
        a,b=assessment(old,runner),assessment(new,runner)
        expected_decision=copy.deepcopy(a['decision']);actual_decision=copy.deepcopy(b['decision'])
        if case=='domestic':
            expected_decision['catalog_versions']['codes']=version
            expected_decision['document'].pop('obligation')
            actual_obligation=actual_decision['document'].pop('obligation')
            require(actual_obligation['proof_kind']=='PRIMARY_ACT_REVIEWED' and not actual_obligation['full_act_review_required'], 'primary-act replacement integrated')
            if runner=='integrated':
                for key in ['iss','federal_withholdings']:
                    require(old['result']['decision'][key]==new['result']['decision'][key], 'legacy fiscal decisions unchanged')
        else:
            schedule=next(x for x in b['trace'] if x['stage']=='IMPORT_DOCUMENT_SCHEDULE131')
            require(schedule['mandatory_from']==('2026-12-01' if case=='import-platform' else '2026-10-01'), 'separate platform/import dates')
            require(not schedule['required_by_schedule'] and not schedule['document_obligation_complete'], 'date does not establish fulfilment')
        require(expected_decision==actual_decision, 'entire RTC fiscal decision except declared schedule/version')
        for key in ['status','reason_codes','missing_facts']:
            require(a[key]==b[key], 'unchanged outcome '+key)
if args.draft2:
    for case in ['import-simples','recipient-simples','recipient-regime-missing']:
        for runner in ['rtc','integrated']:
            s=snapshots[case,runner,'131'];a=assessment(s,runner)
            if case=='recipient-regime-missing':
                require(a['reason_codes']==['RTC_DOCUMENT131_TAXPAYER_REGIME_REQUIRED'],'unknown taxpayer regime controlled')
                require('rtc.recipient_regime' in [q['fact_key'] for q in s['result']['questions']],'recipient question delivered')
            else:
                row=next(x for x in a['trace'] if x['stage']=='IMPORT_DOCUMENT_SCHEDULE131')
                require(row['mandatory_from']=='2027-01-01' and row['exception_article']=='art1 §1','Brazilian taxpayer Simples exception')
folders=['directed-draft2-v1','phpstan-draft2-v1','pint-draft2-v1','production-assess-draft2-v1'] if args.draft2 else ['directed-v2','phpstan-v1','pint-v1','production-assess-v1']
for folder in folders:
    r=read(args.evidence / folder / 'execution.json')
    require(r['status']=='TERMINAL' and r['exit_code']==0, 'successful terminal '+folder)
    for channel in ['stdout','stderr']:
        require(sha(args.evidence/folder/(channel+'.log'))==r[channel+'_sha256'], 'raw log '+folder+channel)
log=re.sub(r'\x1b\[[0-?]*[ -/]*[@-~]', '', (args.evidence/folders[0]/'stdout.log').read_text(encoding='utf-8'))
tests,assertions=(112,809) if args.draft2 else (106,793)
require(f'{tests} passed' in log and f'{assertions} assertions' in log, 'directed test counts')
for folder,count in [('historical130-v1',1),('historical129-v1',28)]+([('historical-draft1-v1',12)] if args.draft2 else []):
    h=read(args.evidence/folder/'validation.json');require(h['exit_code']==0 and h['exact_replays']==count,'historical replays')
current=read(args.evidence/folders[3]/'stdout.log')
require(not current['ready'] and current['eligible_for_local_validation'] and current['official_source_hashes_valid']==107, 'local-only readiness')
changed={p for p,h in base['runtime_hashes'].items() if sha(ROOT/p)!=h}
require(changed=={'app/Modules/Fiscal/ConsumptionReform/'+name+'.php' for name in ['ConsumptionReformEngine','CreditInputPolicy122','CIndOpReference126','DpsLayoutPolicy127','DpsForeignPolicy128','ImportResponsibility129','DocumentObligationEngine']}, 'declared inherited runtime changes')
runtime=set(base['runtime_hashes'])|{'app/Modules/Fiscal/ConsumptionReform/DocumentSchedule131.php'}
report={'created_at':datetime.now(timezone.utc).isoformat(),'status':'SCOPED131_SCHEDULE_VALIDATED_NOT_PHASE_CLOSURE','audit_checks':checks,
    'tests':tests,'assertions':assertions,'quality':{'phpstan':'PASS','pint':'PASS'},'full_suite_executed':False,
    'native_decisions':18 if args.draft2 else 12,'native_exact_replays':18 if args.draft2 else 12,'historical_sample_exact_replays':41 if args.draft2 else 29,'act_rows':32,
    'official_sources_valid':107,'production_blockers':len(current['blockers']),'ready':False,'eligible_for_local_validation':True,
    'bundle':'bundles/'+bundle_path.name,'bundle_sha256':sha(bundle_path),'runtime_hashes':{p:sha(ROOT/p) for p in sorted(runtime)},
    'formal_phase4b_groups_closed':0,'phase4b_complete':False,'document_obligation_complete':False,'evidence_root':str(args.evidence)}
args.output.parent.mkdir(parents=True,exist_ok=True)
with args.output.open('x',encoding='utf-8') as f:json.dump(report,f,ensure_ascii=False,indent=2);f.flush();os.fsync(f.fileno())
print(json.dumps({k:report[k] for k in ['status','audit_checks','tests','assertions','act_rows']}))
