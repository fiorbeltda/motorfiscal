"""Check actual replayed projection against independently expected grouping and arithmetic."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '2026.10.02-14'
BUNDLE = f'bundles/consumption-phase4b-{VERSION}.json'


def execute(args):
    process = subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args], cwd=ROOT,
        capture_output=True,encoding='utf-8',check=False)
    value=json.loads(process.stdout)
    if 'error' in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target=ROOT/path;payload=json.dumps(value,ensure_ascii=False,indent=2)+'\n'
    if target.exists() and target.read_text(encoding='utf-8')!=payload:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True,exist_ok=True);target.write_text(payload,encoding='utf-8')


for family,event,code in [('loan','evtOperFinanc','1010'),('factoring','evtAntecReceb','1240'),('securitization','evtAntecReceb','1220')]:
    filename='financial-borrower-credit-loan-2026.json' if family=='loan' else f'financial-dere-{family}-2026.json'
    prefix=f'storage/app/phase4b-dere-{family}-20261002-14';snapshot=prefix+'-snapshot.json'
    args=['fiscal:rtc:run',f'resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{filename}',f'--bundle-manifest={BUNDLE}']
    if not (ROOT/snapshot).exists():args.append(f'--snapshot={snapshot}')
    result=execute(args)
    assert result['status']=='ACTION_REQUIRED' and result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'] and result['decision']==[],result
    stages={x['stage']:x for x in result['trace']};tax=stages['FINANCIAL_GROSS_DEBITS']
    assert tax['base']=='800.00' and tax['ibs_nominal']=='0.80' and tax['cbs_nominal']=='7.20'
    assert tax['rounding']=='DERE_HALF_EVEN_8_2'
    assert execute(['fiscal:rtc:replay',snapshot])['replay_matches']
    save(prefix+'-result.json',result)
    candidate=prefix+'-candidate.xml';report_path=prefix+'-report.json'
    report=json.loads((ROOT/report_path).read_text(encoding='utf-8')) if (ROOT/report_path).exists() else execute([
        'fiscal:dere:project-xml',snapshot,f'resources/fiscal/scenarios/dere-local-{VERSION}/qualified-allocation.json',
        f'--bundle-manifest={BUNDLE}',f'--candidate-output={candidate}',f'--report={report_path}'])
    assert report['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and report['production_ready'] is False
    assert report['schema_validation']['unsigned_candidate_structure_checked'] and len(report['schema_validation']['errors'])==1
    data=(ROOT/candidate).read_bytes();assert hashlib.sha256(data).hexdigest()==report['schema_validation']['xml_sha256']
    root=ET.fromstring(data);ns={'d':f'http://www.dere.gov.br/schemas/{event}/v0_0_1'}
    assert root.find('.//d:codBC',ns).text==code
    details=root.findall('.//d:detOper',ns)
    assert len(details)==2 and [d.get('seq') for d in details]==['001','002']
    assert [d.find('d:gBC/d:vBCApur',ns).text for d in details]==['500.00','300.00']
    assert [d.find('d:gTributos/d:vIBSUFTrib',ns).text for d in details]==['0.50','0.30']
    assert [d.find('d:gTributos/d:vCBSTrib',ns).text for d in details]==['4.50','2.70']
    key=root.find('.//d:chDeRE',ns).text
    assert len(key)==53 and key[31:35]==code and key[35:45]=='2026092021'
    total=sum((ord(c)-48)*(2+i%8) for i,c in enumerate(key[:49][::-1]));remainder=total%11
    assert key[49]==str(0 if remainder<2 else 11-remainder) and key[-3:]=='000'
    save(f'resources/fiscal/expected/consumption-phase4b-{VERSION}/{filename}',
        {k:result[k] for k in ['status','reason_codes','missing_facts','decision_hash']})
    print(family,result['decision_hash'],report['schema_validation']['xml_sha256'])
process=subprocess.run(['python','scripts/verify_phase4b_financial_credit_cut.py',VERSION],cwd=ROOT,check=False)
assert process.returncode==0
print('Verified3 qualified allocation candidates against unchanged official XSDs; full monthly/integrated approval still pending.')
