"""Generate explicit independent Decimal answers and rejection boundaries for staged document-base memory."""
from pathlib import Path
from copy import deepcopy
from decimal import Decimal
import json

E = Path(r'D:\ProjetoSaasExports\phase4b-document94-base-reconciliation-20261016')
source = json.loads((E / 'exact-source-cells.json').read_text(encoding='utf-8'))
policy = {'contract':'NT009-LOCAL-DOCUMENT-BASE-2026.10.16-94','annex_sha256':source['annex']['sha256'],
    'nt_sha256':source['nt009']['sha256'],'observed_at':source['nt009']['observed_at']}
document = {'document_id':'SYNTHETIC-DPS-94','operation_id':'OP-94','evidence_id':'DECLARED-MEMORY-94',
    'competence_date':'2026-10-16','tpEmit':'1','tribISSQN':'1','vServ':'1000.00','descIncond':'0.00',
    'vCalcAjusteBCIBSCBS':None,'vCalcAjusteBCLocImoveis':None,'vISSQN':'20.00','vPIS':'1.65','vCOFINS':'7.60',
    'vAjusteBCIBSCBSComExt':None,'expected_rtc_base_brl':'970.75'}
cases = []
def case(name, changes=None, reason=None, *, remove=None, known='2026-10-16T14:00:00+00:00', policy_changes=None):
    doc = deepcopy(document); doc.update(changes or {})
    if remove: del doc[remove]
    rule = {**policy, **(policy_changes or {})}
    row = {'name':name,'document':doc,'policy':rule,'known_at':known,
        'expected_status':'ACTION_REQUIRED' if reason else 'LOCAL_DOCUMENT_BASE_MATCH',
        'expected_reason': 'RTC_DOCUMENT_BASE_94_' + (reason or 'MEMORY_RECONCILED')}
    if not reason or reason == 'RTC_DOCUMENT_BASE_MISMATCH':
        d = lambda key: Decimal(doc[key]) if doc[key] is not None else Decimal('0.00')
        total = d('vServ') - d('descIncond') - d('vCalcAjusteBCIBSCBS') - d('vCalcAjusteBCLocImoveis') - d('vISSQN') + d('vAjusteBCIBSCBSComExt')
        if doc['competence_date'] <= '2026-12-31': total -= d('vPIS') + d('vCOFINS')
        row['independent_decimal_base'] = format(total, '.2f')
        row['independent_decimal_difference'] = format(d('expected_rtc_base_brl') - total, '.2f')
    cases.append(row)
case('ordinary2026')
case('not-net-price1000', {'expected_rtc_base_brl':'1000.00'}, 'RTC_DOCUMENT_BASE_MISMATCH')
case('explicit-real-zeros', {'vISSQN':'0.00','vPIS':'0.00','vCOFINS':'0.00','expected_rtc_base_brl':'1000.00'})
case('discount-and-third-party', {'descIncond':'50.00','vCalcAjusteBCIBSCBS':'100.00','expected_rtc_base_brl':'820.75'})
case('alternative-real-estate-memory', {'vCalcAjusteBCLocImoveis':'100.00','expected_rtc_base_brl':'870.75'})
case('both-adjustments', {'vCalcAjusteBCIBSCBS':'0.00','vCalcAjusteBCLocImoveis':'0.00'}, 'ALTERNATIVE_BASE_ADJUSTMENTS_CONFLICT')
case('year2027-retains-only-iss', {'competence_date':'2027-01-01','expected_rtc_base_brl':'980.00'}, known='2027-01-01T12:00:00+00:00')
case('year2032-end', {'competence_date':'2032-12-31','expected_rtc_base_brl':'980.00'}, known='2033-01-01T12:00:00+00:00')
case('year2033-no-guess', {'competence_date':'2033-01-01'}, 'SOURCE_FORMULA_PERIOD_NOT_COVERED', known='2033-01-01T12:00:00+00:00')
case('year2025-no-retroactive-guess', {'competence_date':'2025-12-31'}, 'SOURCE_FORMULA_PERIOD_NOT_COVERED')
case('invalid-calendar', {'competence_date':'2026-02-30'}, 'EXACT_COMPETENCE_DATE_REQUIRED')
case('datetime-not-civil-date', {'competence_date':'2026-10-16T00:00:00Z'}, 'EXACT_COMPETENCE_DATE_REQUIRED')
case('future-document', {'competence_date':'2026-10-17'}, 'DOCUMENT_AFTER_KNOWLEDGE')
case('before-observation', reason='SOURCE_NOT_YET_OBSERVED', known='2026-10-16T13:52:59+00:00')
for field in ['contract','annex_sha256','nt_sha256','observed_at']:
    case('bad-policy-' + field, reason='SOURCE_CONTRACT_NOT_BOUND', policy_changes={field:'wrong'})
for field in document:
    case('missing-' + field, reason='EXPLICIT_DOCUMENT_MEMORY_REQUIRED', remove=field)
for field in ['document_id','operation_id','evidence_id']:
    case('blank-' + field, {field:' '}, 'DOCUMENT_OPERATION_EVIDENCE_IDS_REQUIRED')
case('numeric-issuer', {'tpEmit':1}, 'ISS_DOCUMENT_DOMAINS_NOT_BOUND')
case('unknown-iss-domain', {'tribISSQN':'5'}, 'ISS_DOCUMENT_DOMAINS_NOT_BOUND')
case('comext-even-zero-forbidden', {'vAjusteBCIBSCBSComExt':'0.00'}, 'COMEXT_ADJUSTMENT_PRESENCE_FORBIDDEN_BY_D601')
case('comext-positive-domestic-forbidden', {'vAjusteBCIBSCBSComExt':'100.00'}, 'COMEXT_ADJUSTMENT_PRESENCE_FORBIDDEN_BY_D601')
case('positive-export-adjustment-memory', {'tribISSQN':'3','vISSQN':'0.00','vAjusteBCIBSCBSComExt':'100.00','expected_rtc_base_brl':'1090.75'})
case('negative-export-adjustment-memory', {'tribISSQN':'3','vISSQN':'0.00','vAjusteBCIBSCBSComExt':'-100.00','expected_rtc_base_brl':'890.75'})
case('zero-export-adjustment-present', {'tribISSQN':'3','vAjusteBCIBSCBSComExt':'0.00'})
for name, value in [('float',1000.0),('null',None),('exponent','1e3'),('space',' 1000.00'),('scale','1000.001'),('leading-zero','01000.00'),('negative','-1000.00')]:
    case('money-' + name, {'vServ':value}, 'CANONICAL_DECIMAL_MEMORY_REQUIRED')
case('negative-zero', {'vAjusteBCIBSCBSComExt':'-0.00'}, 'CANONICAL_DECIMAL_MEMORY_REQUIRED')
case('negative-base-not-clamped', {'descIncond':'1001.00'}, 'RESULTING_BASE_OUTSIDE_SOURCE_MONEY_DOMAIN')
case('overflow-not-truncated', {'vServ':'999999999999999.99','tribISSQN':'3','vAjusteBCIBSCBSComExt':'100.00'}, 'RESULTING_BASE_OUTSIDE_SOURCE_MONEY_DOMAIN')
case('unknown-financial-field', {'financial_margin':'10.00'}, 'ADDITIONAL_DOCUMENT_MEMORY_NOT_MAPPED')
case('one-cent', {'vServ':'0.01','vISSQN':'0.00','vPIS':'0.00','vCOFINS':'0.00','expected_rtc_base_brl':'0.01'})
case('last2026-day', {'competence_date':'2026-12-31'}, known='2027-01-01T12:00:00+00:00')
assert cases[0]['independent_decimal_base'] == '970.75'
assert cases[1]['independent_decimal_difference'] == '29.25'
with (E / 'cases.json').open('x', encoding='utf-8') as stream: json.dump(cases, stream, ensure_ascii=False, indent=2)
print('Prepared', len(cases), 'native cases;', sum('independent_decimal_base' in c for c in cases), 'independent Decimal answers')
