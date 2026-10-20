"""Single119 full regression after terminal native and post-codec independent evidence."""
from pathlib import Path
from datetime import datetime,timezone
import hashlib
import json
import re
import shutil
import subprocess
import time
from verify_phase4b_material119_postfix_evidence_v2 import ROOT as R,BASE,E,C,F,A112,read,sha,verify,inputs as expanded_inputs
from verify_phase4b_material119_short_postfix_evidence import verify as verify_short

D=r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
REVIEW120=BASE/'phase4b-requirements120-credit-audit-20261020-v1/requirements-review-v2/requirements120-credit-review.json'
def inputs():
    current=expanded_inputs()
    for p in REVIEW120.parent.parent.rglob('*'):
        if p.is_file()and'__pycache__'not in p.parts:current[str(p)]=sha(p)
    return dict(sorted(current.items()))
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,ensure_ascii=False,indent=2)
def clean(p):return re.sub(r'\x1b\[[0-9;]*m','',p.read_text(encoding='utf-8',errors='strict'))

if __name__=='__main__':
    assert not F.exists(),'Exclusive full119 attempt;never restart a live suite'
    corpus,bundle,audit=verify();short=verify_short()
    assert short['runtime_hashes']==audit['runtime_hashes']and len(audit['runtime_hashes'])==92
    assert corpus['native_decisions']==corpus['exact_replays']==248 and corpus['historical_exact_replays']==1071
    assert short['native_decisions']==short['exact_replays']==122
    recorded_short=read(E/'material119-short-validation.json')
    assert recorded_short['runtime_hashes']==short['runtime_hashes']and recorded_short['native_decisions']==122
    credit=read(REVIEW120)
    assert len(credit['cumulative_unique_reviewed_ids'])==54 and credit['cumulative_not_reviewed_count']==26
    assert credit['formal_requirements_closed']==0 and credit['native_runner_exceptions']==6
    top=subprocess.run([D,'compose','top','app'],cwd=R,capture_output=True,text=True,check=True).stdout
    assert 'vendor/bin/pest'not in top,'A suite is already live'
    assert shutil.disk_usage(R).free>256*1024*1024
    before=inputs();F.mkdir();start=datetime.now(timezone.utc).isoformat()
    save(F/'preflight.json',{'created_at':start,'input_hashes':before,'runtime_hashes':audit['runtime_hashes'],
        'bundle':corpus['bundle'],'bundle_sha256':corpus['bundle_sha256'],'expected_total_tests':8614,
        'new_tests_since112':876,'corpus_sha256':sha(C/'material119-expanded-cli-validation.json'),
        'short_validation_sha256':sha(E/'material119-short-validation.json'),
        'snapshot_fix_sha256':sha(E/'material119-snapshot-fidelity-validation.json'),
        'requirements120_review_sha256':sha(REVIEW120),'known_credit120_findings_remain_open':True,
        'independent_expanded_audit_checks':audit['audit_checks'],'independent_short_audit_checks':short['audit_checks'],
        'independent_decimal_checks':audit['independent_decimal_checks']+short['independent_decimal_checks'],
        'full_suite_complete':False,'production_ready':False})
    command=[D,'compose','exec','-T','app','php','-d','memory_limit=512M','vendor/bin/pest','--colors=never',
        '--do-not-cache-result','--cache-directory=/tmp/phase4b-material119-pest-cache']
    begin=time.monotonic()
    with(F/'full-suite.log').open('xb')as log:
        process=subprocess.Popen(command,cwd=R,stdout=log,stderr=subprocess.STDOUT)
        save(F/'process-start.json',{'host_docker_pid':process.pid,'started_at':start,'command':command})
        print(f'LIVE:full119;host Docker PID:{process.pid};frozen files:{len(before)}',flush=True)
        code=process.wait()
    after=inputs();match=re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)',clean(F/'full-suite.log'))
    changed=[p for p in before if before[p]!=after.get(p)];added=sorted(set(after)-set(before));removed=sorted(set(before)-set(after))
    suite={'exit_code':code,'started_at':start,'finished_at':datetime.now(timezone.utc).isoformat(),'seconds':time.monotonic()-begin,
        'tests':int(match[1])if match else None,'assertions':int(match[2])if match else None,'log_sha256':sha(F/'full-suite.log'),
        'input_files_changed':changed,'input_files_added':added,'input_files_removed':removed,
        'full_suite_complete':code==0 and match is not None and not changed and not added and not removed}
    save(F/'full-suite.json',suite);print(json.dumps(suite),flush=True)
    assert suite['full_suite_complete']and suite['tests']==8614 and suite['assertions']>116599
    def final(label,args):
        cmd=[D,'compose','exec','-T','app','php','-d','memory_limit=512M',*args]
        receipt={'command':cmd,'started_at':datetime.now(timezone.utc).isoformat()}
        p=subprocess.run(cmd,cwd=R,capture_output=True)
        receipt.update(exit_code=p.returncode,finished_at=datetime.now(timezone.utc).isoformat())
        for stream in ['stdout','stderr']:
            with(F/(label+'.'+stream)).open('xb')as f:f.write(getattr(p,stream))
            receipt[stream+'_sha256']=sha(F/(label+'.'+stream))
        save(F/(label+'.json'),receipt);assert p.returncode==0,label
        return clean(F/(label+'.stdout'))
    assert '[OK] No errors'in final('final-phpstan',['vendor/bin/phpstan','analyse','--no-progress'])
    assert re.search(r'PASS.*597 files',final('final-pint',['vendor/bin/pint','--test']))
    assessment=json.loads(final('final-production-assess119',['artisan','fiscal:rtc:production-assess',
        '--bundle-manifest='+corpus['bundle'],'--controls=resources/fiscal/'+bundle['production_controls']['path'],'--target=local-validation']))
    assert assessment['ready']is False and assessment['eligible_for_local_validation']is True
    assert assessment['official_sources_checked']==assessment['official_source_hashes_valid']==98 and len(assessment['blockers'])==127
    assert set(assessment['blockers'])==set(read(C/'production-assess119-expanded.stdout')['blockers'])
    assert inputs()==before
    save(F/'material119-regression-validation.json',{'created_at':datetime.now(timezone.utc).isoformat(),
        'status':'MATERIAL119_LOCAL_INPUT_AND_SNAPSHOT_REGRESSION_VERIFIED','bundle':corpus['bundle'],
        'bundle_sha256':corpus['bundle_sha256'],'runtime_hashes':audit['runtime_hashes'],'full_suite':suite,'full_suite_complete':True,
        'unchanged_input_files':len(before),'native_decisions':370,'exact_replays':370,'historical_exact_replays':1071,
        'independent_expanded_audit_checks':audit['audit_checks'],'independent_short_audit_checks':short['audit_checks'],
        'independent_decimal_checks':audit['independent_decimal_checks']+short['independent_decimal_checks'],
        'official_sources_valid':98,'production_blocker_count':127,'requirements_reviewed_not_approved':54,
        'requirements_not_reviewed':26,'known_credit120_findings_fixed':False,'document_obligation_complete':False,
        'phase4b_complete':False,'production_ready':False})
    print('PASS:full119 and final quality;independent development acceptance still required',flush=True)
