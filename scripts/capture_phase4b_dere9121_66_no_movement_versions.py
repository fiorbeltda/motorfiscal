"""Capture six active no-movement monthly versions against accepted58 economics and exact replays."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-66-no-movement-versions-20261013'
P=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,v):
    if p.exists():
        assert read(p)==v,p
    else:
        with p.open('x',encoding='utf-8') as f:
            json.dump(v,f,ensure_ascii=False,indent=2)

def run(label,args,native=False):
    log=E/(label+'.log')
    record=E/(label+'.json')
    if record.exists():
        metadata=read(record)
        assert metadata['exit_code']==0 and metadata['log_sha256']==sha(log)
        stdout=log.read_text(encoding='utf-8')
    else:
        assert not log.exists()
        command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M',*args]
        result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
        with log.open('x',encoding='utf-8') as f:
            f.write(result.stdout+result.stderr)
        save(record,{'command':command,'exit_code':result.returncode,'log_sha256':sha(log)})
        assert result.returncode==0,label+':'+result.stderr
        stdout=result.stdout
    if native:
        assert 'PASS:' in stdout and 'own66 active' in stdout
        return None
    return json.loads(stdout)

assert read(ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013/version-corpus-development-validation.json')['local_corpus65_complete']
financial=run('financial66-capture',['artisan','fiscal:rtc:run',(E/'financial-input.json').relative_to(ROOT).as_posix(),
    '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft66.json','--snapshot='+(E/'financial-snapshot.json').relative_to(ROOT).as_posix()])
assert financial['status']=='ACTION_REQUIRED' and financial['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
assert run('financial66-replay',['artisan','fiscal:rtc:replay',(E/'financial-snapshot.json').relative_to(ROOT).as_posix()])['replay_matches']
records=[]
for name in ['qualified-direct-only','qualified-fund-only','qualified-both-codes']:
    previous=read(P/(name+'-monthly-report.json'))
    previous_hash=sha(P/(name+'-monthly-report.json'))
    for sequence in ['01','03']:
        stem=name+'-active'+sequence
        driver=(E/(name+'-capture-v2.php')).relative_to(ROOT).as_posix()
        run(stem+'-native-capture-v2',[driver,'capture',sequence],True)
        run(stem+'-native-replay',[driver,'replay',sequence],True)
        native=read(E/(stem+'-settlement.json'))
        proposal=native['result']
        binding=proposal['composition']['active_version_binding']
        assert binding['active_event']['sequence']==sequence
        assert binding['active_event']['reconciliation']['title_count']==0
        assert proposal['composition']['calculation_input']['public_offer_debt_return']=='0.00'
        assert proposal['ibs_nominal_proposal']=='0.64' and proposal['cbs_nominal_proposal']=='6.15'
        assert proposal['original_gross_debits']==previous['negative_base_settlement']['original_gross_debits']
        assert proposal['stock_after_proposed_consumption']==previous['negative_base_settlement']['stock_after_proposed_consumption']
        report_path=E/(stem+'-monthly-report.json')
        candidate=E/(stem+'-monthly.xml')
        args=['artisan','fiscal:dere:project-monthly-closing',(E/'financial-snapshot.json').relative_to(ROOT).as_posix(),
            (E/(name+'-month.json')).relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft66.json',
            '--negative-base-settlement-request='+(E/(stem+'-request.json')).relative_to(ROOT).as_posix()]
        report=run(stem+'-monthly-capture',args+['--report='+report_path.relative_to(ROOT).as_posix(),'--candidate-output='+candidate.relative_to(ROOT).as_posix()])
        assert report==read(report_path) and report['negative_base_settlement']==proposal
        replay=run(stem+'-monthly-replay',args)
        keys=set(report)-{'candidate_output','event_outputs'}
        assert {k:report[k] for k in keys}=={k:replay[k] for k in keys}
        assert (ROOT/report['event_outputs']['D2101']).read_bytes()==binding['active_event']['origin_xml'].encode('utf-8')
        hashes={event:sha(ROOT/path) for event,path in report['event_outputs'].items()}
        assert len(hashes)==6
        for event,path in report['event_outputs'].items():
            assert hashes[event]==report['event_validations'][event]['xml_sha256']
            assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
            if event!='D2101':
                assert hashes[event]==sha(ROOT/previous['event_outputs'][event])
        assert sha(P/(name+'-monthly-report.json'))==previous_hash
        assert report['production_ready'] is False
        records.append({'name':name,'active_sequence':sequence,'report_sha256':sha(report_path),
            'native_settlement_sha256':sha(E/(stem+'-settlement.json')),'xml_sha256':hashes,
            'exact_native_replay':True,'exact_monthly_replay':True,'five_previous_xmls_preserved':True,'title_count':0,'public_total':'0.00'})
        print('PASS:'+stem+'; explicit no movement, active bytes, six schemas, preserved economics and exact replays',flush=True)
save(E/'no-movement-version-capture-validation.json',{'status':'THREE_NO_MOVEMENT_PROFILES_ACTIVE01_AND03_CAPTURED_AND_EXACTLY_REPLAYED',
    'cases':records,'monthly_versions':6,'xml_hashes':36,'financial_replays':1,'native_replays':6,'monthly_replays':6,'production_ready':False})
print('PASS:six native no-movement active versions,36 XMLs and13 exact financial/native/monthly replays')
