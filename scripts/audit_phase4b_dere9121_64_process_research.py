"""Audit primary package observations and explicit process guard, without claiming unavailable D1021 qualification."""
from pathlib import Path
import hashlib,json,zipfile
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-64-process-preparation-20261013'
P=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def ref(p):
    return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

primary=read(E/'process-research-sources.json')
for item in primary['sources']:
    assert sha(ROOT/item['path'])==item['sha256']
    assert sha(ROOT/item['excerpt_path'])==item['excerpt_sha256']
events=(E/'dere-observed-20261005-events.pdf.process-excerpt.txt').read_text(encoding='utf-8')
validation=(E/'dere-observed-20261005-validation.pdf.process-excerpt.txt').read_text(encoding='utf-8')
assert 'evento D-1021' in events and 'EXIGIR_NUMERO_PROCESSO' in validation
live=read(E/'live-package-observation.json')
for package,count in zip(live['packages'],[5,9,32],strict=True):
    assert package['status']=='CAPTURED' and len(package['inventory'])==count
    assert sha(ROOT/package['path'])==package['sha256']
    with zipfile.ZipFile(ROOT/package['path']) as z:
        actual=[{'name':n,'sha256':hashlib.sha256(z.read(n)).hexdigest()} for n in z.namelist() if not n.endswith('/')]
        assert actual==package['inventory']
        assert not any('proc' in n.lower() for n in z.namelist())
        assert not any(b'DeRE1021' in z.read(n) for n in z.namelist() if n.endswith('.xsd'))
assert live['packages'][2]['sha256']==primary['schema_archive_sha256']
labels=['process64-native-capture','process64-native-replay']
for label in labels:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    assert (E/(label+'.log')).read_text(encoding='utf-8').count('PASS:')==4
native=read(E/'process-guard-native-result.json')
assert len(native['cases'])==4
for name,case in native['cases'].items():
    result=case['result']
    assert result['production_ready'] is False
    if name=='ordinary-exclusion':
        assert result['status']=='LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED' and result['active_event'] is None
    else:
        assert result['reason']=='RTC_DERE_PUBLIC_VERSION_EXCLUSION_PROCEEDING_QUALIFICATION_REQUIRED'
for item in read(P/'integration-progress-validation.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
files=[E/'exercise-process-guard.php',ROOT/'scripts/prepare_phase4b_dere9121_64_process_research.py',
    ROOT/'scripts/capture_phase4b_dere9121_64_live_packages.py',ROOT/'scripts/capture_phase4b_dere9121_64_check.py',
    ROOT/'docs/fiscal/phase4b-dere9121-64-process-source-gap.md',Path(__file__)]
with (E/'process-research-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'PROCESS_PRIMARY_RESEARCH_AND_EXISTING_GUARD_VERIFIED_ORIGIN_QUALIFICATION_PENDING',
        'live_packages':3,'live_package_files':[5,9,32],'documentary13_hash_matches_preserved':True,
        'native_guard_cases':4,'exact_replay':True,'quality':[ref(E/(label+'.json')) for label in labels],
        'primary_evidence':[ref(E/name) for name in ['process-research-sources.json','live-package-observation.json','process-guard-native-result.json']],
        'implementation':[ref(p) for p in files],'current63_implementation_preserved':True,
        'questions':['Official D1021 origin layout and namespace','Active process validity for supplier and competence',
            'Origin-return and environment receipt qualification','Effects of process-table alteration and exclusion'],
        'process_origin_schema_qualified':False,'process_exclusion_activated':False,'scope64_complete':False,
        'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:three official package inventories, preserved13 hash, four exact guard cases and unchanged63; D1021 qualification pending')
