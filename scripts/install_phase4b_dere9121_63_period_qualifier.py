"""Promote the audited62 qualifier and permanent tests only after actual61 full acceptance."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'storage/app/phase4b-dere9121-62-period-preparation-20261013'
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

accepted=read(ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013/version-development-validation.json')
assert accepted['full_suite_after61_complete'] is True
for report in [accepted,read(P/'period-preparation-validation.json')]:
    for item in report['implementation']:
        assert sha(ROOT/item['path'])==item['sha256'],item['path']
E.mkdir(exist_ok=True)
source=(P/'planned-period-qualifier.php').read_text(encoding='utf-8')
assert source.count('class DerePublicOfferPeriodStatePrototype62')==1
source=source.replace('class DerePublicOfferPeriodStatePrototype62','class DerePublicOfferPeriodStateQualifier')
source=source.replace('/** Prototype: contextual synthetic period timeline;', '/** Contextual synthetic period timeline;')
target=ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferPeriodStateQualifier.php'
with target.open('x',encoding='utf-8') as f:
    f.write(source)
tests=r'''<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferPeriodStateQualifier;

it('qualifies complete contextual period histories and rejects invalid reopening proofs', function (string $name): void {
    $root=dirname(__DIR__,3);
    $capture=json_decode((string)file_get_contents($root.'/storage/app/phase4b-dere9121-62-period-preparation-20261013/period-native-v2-result.json'),true,512,JSON_THROW_ON_ERROR);
    $case=$capture['cases'][$name];
    $input=$case['input'];
    $result=(new DerePublicOfferPeriodStateQualifier)->qualify($input['period_events'],$input['public_events'],
        '11111111000191','2026-09',new DateTimeImmutable('2026-10-13T13:16:56+00:00'),$root.'/resources/fiscal/sources/official',$input['history']);
    expect($result)->toBe($case['result'])->and($result['production_ready'])->toBeFalse();
    if ($case['expected_reason']===null) {
        expect($result['period_state_locally_reconciled'])->toBeTrue()
            ->and($result['period_state_externally_verified'])->toBeFalse()
            ->and($result['closing_origin_economics_reconciled'])->toBeFalse()
            ->and($result['government_return_authenticated'])->toBeFalse();
    } else {
        expect($result['reason'])->toBe('RTC_DERE_PUBLIC_PERIOD_'.$case['expected_reason']);
    }
})->with(['initial-open','one-reopening','two-reopenings','closed','reopen-without-closing','reopening-sequence-reset',
    'wrong-origin-hash','wrong-return-id','return-error','real-context','history-hash','future-history',
    'public-outside-history','public-transition-overlap','stale-closing-receipt','missing-origin','wrong-supplier',
    'reopening-operation','reception-before-process','duplicate-close','history-ends-before-known','closed-after-public']);
'''
test=ROOT/'tests/Fiscal/Unit/DerePublicOfferPeriodState63Test.php'
with test.open('x',encoding='utf-8') as f:
    f.write(tests)
with (E/'period-source-installation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'AUDITED62_PERIOD_QUALIFIER_PROMOTED_PERMANENT_TESTS_PENDING',
        'predecessor_acceptance_sha256':sha(ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013/version-development-validation.json'),
        'prototype_sha256':sha(P/'planned-period-qualifier.php'),
        'implementation':[{'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)} for p in [target,test]],
        'policy_activated':False,'monthly_integrated':False,'production_ready':False},f,indent=2)
print('PASS:audited62 contextual period qualifier promoted after accepted61; policy and integration pending')
