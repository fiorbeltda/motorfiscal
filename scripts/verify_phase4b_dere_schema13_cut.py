"""Run migrated monthly corpus and compare repeated projections without rewriting evidence."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
VERSION='2026.10.06-22'
E=ROOT/'storage/app/phase4b-schema13-20261006-22'
S=ROOT/f'resources/fiscal/scenarios/dere-schema13-{VERSION}'
BUNDLE=f'bundles/consumption-phase4b-{VERSION}.json'


def run(args,code=0):
    r=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
    assert r.returncode==code,r.stdout+r.stderr
    v=json.loads(r.stdout);assert 'error' not in v,v;return v


def save(p,v):
    if p.exists():assert json.loads(p.read_text(encoding='utf-8'))==v,p;return
    with p.open('x',encoding='utf-8') as f:f.write(json.dumps(v,ensure_ascii=False,indent=2)+'\n')


snapshots={}
for source in sorted(S.glob('financial-scenario*.json')):
    p=E/f'{source.stem}-snapshot.json';args=['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={BUNDLE}']
    if not p.exists():args.append(f'--snapshot={p.relative_to(ROOT).as_posix()}')
    result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
    assert run(['fiscal:rtc:replay',p.relative_to(ROOT).as_posix()])['replay_matches']
    snapshots[source.name]=p.relative_to(ROOT).as_posix()
cases=[];bodies=0
for source in sorted(S.glob('*.json')):
    v=json.loads(source.read_text(encoding='utf-8'))
    if 'corpus_expectation' not in v:continue
    positive=v['corpus_expectation']['status']=='PROJECTED'
    args=['fiscal:dere:project-monthly-closing',snapshots[v.get('financial_scenario','financial-scenario.json')],source.relative_to(ROOT).as_posix(),f'--bundle-manifest={BUNDLE}']
    p=E/f'{source.stem}-monthly-report.json'
    if positive and not p.exists():args += [f'--report={p.relative_to(ROOT).as_posix()}',f'--candidate-output={E.relative_to(ROOT).as_posix()}/{source.stem}-candidate.xml']
    r=run(args,0 if positive else 1)
    assert r.get('production_ready') is not True
    if positive:
        stored=json.loads(p.read_text(encoding='utf-8'))
        for key in ['decision_hash','snapshot_hash','monthly_input_hash','projection','event_validations']:assert r[key]==stored[key]
        expected={'D1001','D1011','D1101','D1199'}
        if v.get('deductions') is not None and v['prerequisites']['D1121_required']:expected.add('D1121')
        if v['prerequisites']['D2101_required']:expected.add('D2101')
        assert set(stored['event_outputs'])==expected
        for event,filename in stored['event_outputs'].items():
            data=(ROOT/filename).read_bytes();val=stored['event_validations'][event]
            assert val['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(val['errors'])==1
            assert val['xml_sha256']==hashlib.sha256(data).hexdigest()
            assert val['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
            xml=ET.fromstring(data);ns={'d':xml.tag.split('}')[0][1:]}
            if event=='D1011':assert ns['d'].endswith('/v1_0_4')
            if event=='D1121':
                assert ns['d'].endswith('/v0_0_2')
                assert xml.find('.//d:infoDFe',ns).get('chDFe')==v['deductions']['documents'][0]['chDFe']
                row=xml.find('.//d:desdobDeducao',ns);assert row.get('tpAtiv')=='01'
                assert row.find('d:cCta',ns).text=='correspondent000'
                assert xml.find('.//d:vDed',ns).text=='100.00'
                assert (xml.find('.//d:vDedTotal',ns) is None)==('previous_deduction' in v['deductions']['documents'][0])
            bodies+=1
    else:
        assert r['status']=='ACTION_REQUIRED' and r['reason']==v['corpus_expectation']['reason'],(source.name,r)
    cases.append({'case':source.stem,'status':r['status'],'reason':r['reason']})
assert len(cases)==46 and bodies==41
save(E/'corpus.json',{'valid':True,'cases':cases,'xml_bodies':bodies,'financial_replays':len(snapshots)})
gate=run(['fiscal:rtc:production-assess',f'--bundle-manifest={BUNDLE}',f'--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json','--target=local-validation'])
assert not gate['ready'] and gate['official_source_hashes_valid']==43;save(E/'production-assessment.json',gate)
baseline=json.loads((E/'historical-before.json').read_text(encoding='utf-8'))
changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h]
assert not changed,changed
save(E/'historical-verification.json',{'checked':len(baseline),'changed':changed})
print('Candidate22 PASS:46 cases,41 XML bodies,4 financial replays; historical artifacts preserved.')
