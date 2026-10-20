"""Capture forty own65 active public monthly versions and exact replays, preserving all old evidence."""
from pathlib import Path
import ctypes,hashlib,json,os,subprocess
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
P=ROOT/'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
manifest=json.loads((E/'source-corpus-manifest.json').read_text(encoding='utf-8'))
bundle=json.loads((ROOT/'resources/fiscal/bundles/consumption-phase4b-2026.10.13-28-draft65.json').read_text(encoding='utf-8'))

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,value):
    if p.exists():
        assert read(p)==value,p
    else:
        with p.open('x',encoding='utf-8') as f:
            json.dump(value,f,ensure_ascii=False,indent=2)

def compact(p):
    if os.name!='nt' or p.stat().st_size<80_000:
        return
    marker=E/(p.name+'.compression.json')
    if marker.exists():
        assert read(marker)['sha256']==sha(p)
        return
    before=sha(p)
    result=subprocess.run(['compact.exe','/C','/EXE:LZX','/I','/Q',str(p)],capture_output=True,cwd=ROOT)
    assert before==sha(p),'Compression changed bytes'
    kernel=ctypes.WinDLL('kernel32',use_last_error=True)
    kernel.GetCompressedFileSizeW.argtypes=[ctypes.c_wchar_p,ctypes.POINTER(ctypes.c_uint32)]
    kernel.GetCompressedFileSizeW.restype=ctypes.c_uint32
    high=ctypes.c_uint32()
    low=kernel.GetCompressedFileSizeW(str(p),ctypes.byref(high))
    assert low!=0xFFFFFFFF or ctypes.get_last_error()==0
    save(marker,{'path':p.relative_to(ROOT).as_posix(),'sha256':before,'compact_exit_code':result.returncode,
        'logical_bytes':p.stat().st_size,'physical_bytes':(high.value<<32)+low,'content_preserved':True})

def run(label,args,json_output=True):
    log=E/(label+'.log')
    record=E/(label+'.json')
    if record.exists():
        metadata=read(record)
        assert metadata['exit_code']==0 and metadata['log_sha256']==sha(log),label
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
    compact(log)
    if json_output:
        return json.loads(stdout)
    assert 'PASS:' in stdout and 'own65 active' in stdout,label
    return None

records=[]
for case in manifest['cases']:
    name=case['name']
    for key in ['source_month','source_financial_input','source_monthly_report','source_d2101']:
        assert sha(ROOT/case[key]['path'])==case[key]['sha256']
    financial=read(P/(name+'-financial.json'))
    financial.update(scenario_id='SYNTHETIC-D9121-65-'+name,knowledge_at=bundle['knowledge_available_at'],rule_bundle_id=bundle['bundle_id'])
    financial['catalog_versions']=bundle['catalog_versions']
    input_path=E/(name+'-financial.json')
    snapshot=E/(name+'-snapshot.json')
    save(input_path,financial)
    actual=run(name+'-financial-capture',['artisan','fiscal:rtc:run',input_path.relative_to(ROOT).as_posix(),
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft65.json','--snapshot='+snapshot.relative_to(ROOT).as_posix()])
    assert actual['status']=='ACTION_REQUIRED' and actual['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
    compact(snapshot)
    replay=run(name+'-financial-replay',['artisan','fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
    assert replay['replay_matches'] is True
    previous=read(P/(name+'-monthly-report.json'))
    previous_hash=sha(P/(name+'-monthly-report.json'))
    for sequence in ['01','03']:
        stem=name+'-active'+sequence
        driver=(E/(name+'-capture.php')).relative_to(ROOT).as_posix()
        run(stem+'-native-capture',[driver,'capture',sequence],False)
        native_path=E/(stem+'-settlement.json')
        native=read(native_path)
        compact(native_path)
        run(stem+'-native-replay',[driver,'replay',sequence],False)
        assert native['result']['composition']['calculation_input']==previous['negative_base_settlement']['composition']['calculation_input']
        assert native['result']['composition']['calculation_input']['public_offer_debt_return']==case['original_public_total']
        assert native['result']['original_gross_debits']==previous['negative_base_settlement']['original_gross_debits']
        for key in ['ibs_nominal_proposal','cbs_nominal_proposal','assessed_pools','stock_after_proposed_consumption']:
            assert native['result'][key]==previous['negative_base_settlement'][key],name+':'+key
        month=E/(name+'-month.json')
        request=E/(stem+'-request.json')
        report_path=E/(stem+'-monthly-report.json')
        candidate=E/(stem+'-monthly.xml')
        args=['artisan','fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),month.relative_to(ROOT).as_posix(),
            '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft65.json',
            '--negative-base-settlement-request='+request.relative_to(ROOT).as_posix()]
        report=run(stem+'-monthly-capture',args+['--report='+report_path.relative_to(ROOT).as_posix(),'--candidate-output='+candidate.relative_to(ROOT).as_posix()])
        assert report==read(report_path) and report['negative_base_settlement']==native['result']
        compact(report_path)
        replay=run(stem+'-monthly-replay',args)
        keys=set(report)-{'candidate_output','event_outputs'}
        assert {k:report[k] for k in keys}=={k:replay[k] for k in keys}
        binding=native['result']['composition']['active_version_binding']
        assert binding['active_event']['sequence']==sequence
        assert binding['period_history_qualification']['reopening_count']==2
        assert (ROOT/report['event_outputs']['D2101']).read_bytes()==binding['active_event']['origin_xml'].encode('utf-8')
        assert report['production_ready'] is False
        hashes={event:sha(ROOT/path) for event,path in report['event_outputs'].items()}
        assert len(hashes)==6
        for event,path in report['event_outputs'].items():
            assert hashes[event]==report['event_validations'][event]['xml_sha256']
            assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
            if event!='D2101':
                assert hashes[event]==sha(ROOT/previous['event_outputs'][event]),name+':'+event
        assert sha(P/(name+'-monthly-report.json'))==previous_hash
        records.append({'name':name,'active_sequence':sequence,'public_total':case['original_public_total'],
            'native_settlement_sha256':sha(native_path),'report_sha256':sha(report_path),'xml_sha256':hashes,
            'financial_replay_matches':True,'exact_native_replay':True,'exact_monthly_replay':True,
            'five_previous_xmls_preserved':True,'original_economics_preserved':True})
        print('PASS:'+stem+'; native business/history, six XMLs, original economics and exact replays',flush=True)
save(E/'corpus-capture-validation.json',{'status':'TWENTY_NATIVE_PUBLIC_CASES_ACTIVE01_AND03_CAPTURED_AND_EXACTLY_REPLAYED',
    'cases':records,'financial_cases':20,'monthly_versions':40,'xml_hashes':240,'financial_replays':20,
    'native_replays':40,'monthly_replays':40,'source_manifest_sha256':sha(E/'source-corpus-manifest.json'),'production_ready':False})
print('PASS:forty active versions,240 schema-checked XMLs,100 financial/native/monthly replays and preserved predecessor economics')
