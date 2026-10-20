"""Select explicit108 engine scenarios; native builder recalculates binding inputs,never expected outcomes."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1]
E=Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
TARGET=E/'scenario-seed.json'
assert not TARGET.exists()
fixtures=json.loads((ROOT/'tests/Fixtures/dated107-cases.json').read_text(encoding='utf-8'))
byname={c['name']:c for c in fixtures['cases']}
positive=['inherit106-inherit105-coincident-material-dates','inherit106-inherit105-iss-both-before-rtc',
    'inherit106-inherit105-iss-both-after-rtc','inherit106-inherit105-iss-distinct-by-role',
    'inherit106-inherit105-fulfilled-monthly-stage-without-final-delivery','inherit106-inherit105-same-unit-equivalent-offsets',
    'inherit106-inherit105-civil-date-not-utc-date','inherit106-foreign-result-with-br-software-link-remains-exception',
    'emission-at-material-event','series-valid-49999','number-valid-maximum']
negative=['series-0','competence-not-material','mapping-resigned-wrong-dated_pair_binding_hash',
    'mapping-hash-tamper','emission-before-same-day-material-event','civil-date-is-not-issuer-offset-date',
    'mapping-before-national-source-observation','same-canonical-dps-id','duplicate-role']
cases=[]
for name in [*positive,*negative]:
    source=byname[name]
    cases.append({'name':name,'source_case':source,
        'status':'ACTION_REQUIRED' if name in positive else source['expected_status'],
        'reason':'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' if name in positive else source['expected_reason'],
        'dated_status':source['expected_status'],'dated_reason':source['expected_reason']})
for name,mutation,reason in [
    ('legacy-context-conflict',{'rtc.assess_export_iss_results':True},'RTC_EXPORT_DATED108_SEPARATE_LEGACY_CONTEXT_REQUIRED'),
    ('temporal-context-conflict',{'rtc.assess_tax_point_events':True},'RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED')]:
    cases.append({'name':name,'source_case':byname[positive[0]],'fact_mutations':mutation,
        'status':'ACTION_REQUIRED','reason':reason,'dated_status':None,'dated_reason':None})
with TARGET.open('x',encoding='utf-8') as f:json.dump({'created_at':datetime.now(timezone.utc).isoformat(),
    'source_fixture_sha256':hashlib.sha256((ROOT/'tests/Fixtures/dated107-cases.json').read_bytes()).hexdigest(),
    'cases':cases,'source_bundle':'bundles/consumption-phase4b-2026.10.19-108-draft1.json',
    'full_upstream_corpus_complete':False},f,ensure_ascii=False,indent=2)
print('Prepared22 explicit scenario expectations;upstreamUSD/other routes still require expansion',flush=True)
