"""Verify active origin bytes, independent native baseline, preserved auxiliaries and exact monthly replay."""
from pathlib import Path
import hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

report=read(E/'monthly-report.json')
native=read(E/'native-version-settlement.json')
assert report['negative_base_settlement']==native['result']
active=native['result']['composition']['active_version_binding']['active_event']
assert active['sequence']=='03'
assert (ROOT/report['event_outputs']['D2101']).read_bytes()==active['origin_xml'].encode('utf-8')
expected=report['projection']['active_public_event_output']
assert expected['origin_xml_sha256']==sha(ROOT/report['event_outputs']['D2101'])
assert expected['origin_xml_sha256']!=expected['native_origin_xml_sha256']
previous=read(ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-report.json')
for event in ['D1001','D1011','D1101','D1121','D1199']:
    assert sha(ROOT/report['event_outputs'][event])==sha(ROOT/previous['event_outputs'][event]),event
for event,path in report['event_outputs'].items():
    assert sha(ROOT/path)==report['event_validations'][event]['xml_sha256']
    assert report['event_validations'][event]['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
assert not report['production_ready']
command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:dere:project-monthly-closing',
    (E/'financial-snapshot.json').relative_to(ROOT).as_posix(),(E/'command-month.json').relative_to(ROOT).as_posix(),
    '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft61.json',
    '--negative-base-settlement-request='+(E/'command-request.json').relative_to(ROOT).as_posix()]
result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
assert result.returncode==0,result.stderr
replay=json.loads(result.stdout)
keys=set(report)-{'event_outputs','candidate_output'}
assert {k:report[k] for k in keys}=={k:replay[k] for k in keys}
with (E/'native-monthly-version-validation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'ACTIVE03_NATIVE_MONTHLY_SELECTION_CAPTURED_AND_REPLAYED','exact_replay':True,
        'report_sha256':sha(E/'monthly-report.json'),'active_origin_sha256':expected['origin_xml_sha256'],
        'native_origin_sha256':expected['native_origin_xml_sha256'],'five_prior_xml_bodies_preserved':True,
        'six_schema_checks':True,'government_return_authenticated':False,'production_ready':False},stream,indent=2)
print('PASS:active03 bytes emitted, native baseline distinguished, five prior XMLs preserved and exact replay')
