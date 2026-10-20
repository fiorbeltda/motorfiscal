"""Preserve terminal116 as intermediate evidence and archive the runtime before119."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import shutil

R=Path(__file__).resolve().parents[1]
BASE=Path(r'D:\ProjetoSaasExports')
E=BASE/'phase4b-geographic116-integration-20261020'
N=BASE/'phase4b-material-input119-integration-20261020'
def read(p):return json.loads(p.read_text(encoding='utf-8-sig'))
def sha(p):
    with p.open('rb')as f:return hashlib.file_digest(f,'sha256').hexdigest()
def save(p,v):
    with p.open('x',encoding='utf-8')as f:json.dump(v,f,ensure_ascii=False,indent=2)
assert not N.exists(),'Exclusive119 archive;never overwrite an earlier attempt'
corpus=read(E/'expanded-cli-v1/geographic116-expanded-cli-validation.json')
audit=read(E/'expanded-independent-review-v1.log')
short=read(E/'geographic116-targeted-validation.json')
assert audit['audit_checks']==35072 and audit['independent_decimal_checks']==568 and audit['full108stage_comparisons']==232
assert corpus['native_decisions']==corpus['exact_replays']==248 and corpus['historical_exact_replays']==791
assert corpus['input_inventory_unchanged']is True and corpus['runtime_hashes']==short['runtime_hashes']
assert sha(E/'geographic116-targeted-validation.json')=='b463b84750378b09d0a32e535ba0eda5f56549191998861d6e4967fcba07a003'
runtime=corpus['runtime_hashes'];assert len(runtime)==88
for p,h in runtime.items():assert sha(R/p)==h,p
assert not (BASE/'phase4b-geographic116-full-suite-20261020').exists(),'116 full suite was not started'
bundle=read(R/'resources/fiscal'/corpus['bundle'])
resources=[R/'resources/fiscal'/corpus['bundle'],R/'resources/fiscal'/bundle['resource_paths']['rtc_code_catalog'],
           R/'resources/fiscal'/bundle['production_controls']['path']]
N.mkdir()
archived={}
for source in [R/p for p in runtime]+resources:
    relative=source.relative_to(R)
    destination=N/'before-integration'/relative
    destination.parent.mkdir(parents=True,exist_ok=True)
    assert not destination.exists()
    shutil.copy2(source,destination)
    assert sha(source)==sha(destination)
    archived[relative.as_posix()]=sha(destination)
save(N/'before-integration-archive.json',{'created_at':datetime.now(timezone.utc).isoformat(),
    'runtime_hashes':runtime,'archived_files':archived,'archive_count':len(archived),
    'source116_corpus_sha256':sha(E/'expanded-cli-v1/geographic116-expanded-cli-validation.json'),
    'independent116_audit_log_sha256':sha(E/'expanded-independent-review-v1.log'),
    'next_generation_installed':False,'production_ready':False})
report={'created_at':datetime.now(timezone.utc).isoformat(),'status':'GEOGRAPHIC116_TERMINAL_CORPUS_VERIFIED_INTERMEDIATE_NOT_FULL_REGRESSION_ACCEPTANCE',
    'bundle':corpus['bundle'],'bundle_sha256':corpus['bundle_sha256'],'runtime_hashes':runtime,
    'expanded_native_decisions':248,'expanded_own_exact_replays':248,'historical_exact_replays':791,
    'earlier_geography_native_decisions':32,'earlier_geography_exact_replays':32,
    'independent_expanded_audit_checks':35072,'independent_decimal_checks':568,'full108_nominal_fx_comparisons':232,
    'tool_observed_independent_audit_exit_code':0,'independent_audit_log_sha256':sha(E/'expanded-independent-review-v1.log'),
    'corpus_sha256':sha(E/'expanded-cli-v1/geographic116-expanded-cli-validation.json'),
    'preparation_sha256':sha(E/'geographic116-expanded-preparation-validation.json'),
    'earlier_geography_validation_sha256':sha(E/'geographic116-targeted-validation.json'),
    'official_sources_valid':97,'production_blocker_count':126,
    'next119_before_archive_sha256':sha(N/'before-integration-archive.json'),
    'open_subjects117_review_sha256':sha(BASE/'phase4b-requirements117-subjects-audit-20261020-v1/requirements-review-v2/requirements117-subjects-review.json'),
    'open_base118_review_sha256':sha(BASE/'phase4b-requirements118-base-audit-20261020-v1/requirements-review-v1/requirements118-base-review.json'),
    'full116_suite_started':False,'full_suite_complete':False,'formal_phase4b_groups_closed':0,
    'reason_next_acceptance_must_include_fixes':'117/118 found material basis and adjustment input-contract gaps,including exceptions for boolean amounts;preserve116 intermediate and implement a versioned correction before next full acceptance.',
    'phase4b_complete':False,'production_ready':False}
save(E/'geographic116-intermediate-validation.json',report)
for p,h in runtime.items():assert sha(R/p)==h,p
print(json.dumps({'status':report['status'],'archived_files':len(archived),
    'intermediate_sha256':sha(E/'geographic116-intermediate-validation.json'),'runtime_changed':False,'production_ready':False}),flush=True)
