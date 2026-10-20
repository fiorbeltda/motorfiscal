"""Prepare permanent planner and command gates using an actual own63 native period capture."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'
assert (E/'native-period-settlement.json').exists()
unit=r'''<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

it('requires policy and complete monthly-bound period history before public settlement', function (string $case): void {
    $input=json_decode((string)file_get_contents(storage_path('app/phase4b-dere9121-63-period-integration-20261013/native-period-settlement.json')),true,512,JSON_THROW_ON_ERROR)['input'];
    $expected=null;
    if ($case==='disabled') {
        $input['policy']['require_public_period_history']=false;
        unset($input['policy']['source_hash']);
        $input['policy']['source_hash']=CanonicalJson::hash($input['policy']);
        $expected='RTC_DERE_BCN_SETTLEMENT_PUBLIC_PERIOD_HISTORY_POLICY_REQUIRED';
    } elseif ($case==='missing') {
        unset($input['request']['current_public_offer_return']['period_history']);
        $expected='RTC_DERE_CURRENT_COMPOSITION_PUBLIC_PERIOD_HISTORY_REQUIRED';
    } elseif ($case==='month-hash') {
        $input['request']['current_public_offer_return']['period_history']['monthly_input_hash']=str_repeat('0',64);
        $expected='RTC_DERE_PUBLIC_BINDING_MONTHLY_BOUND_PERIOD_HISTORY_REQUIRED';
    } elseif ($case==='history-hash') {
        $input['request']['current_public_offer_return']['period_history']['witness']['history_hash']=str_repeat('0',64);
        $expected='RTC_DERE_PUBLIC_PERIOD_HISTORY_HASH_OR_KNOWLEDGE_CONFLICT';
    } elseif ($case==='closed') {
        $h=&$input['request']['current_public_offer_return']['period_history'];
        array_pop($h['events']);
        $h['witness']['history_hash']=CanonicalJson::hash(['supplier_id'=>$h['witness']['supplier_id'],'period'=>$h['witness']['period'],
            'initial_state'=>$h['witness']['initial_state'],'from'=>$h['witness']['from'],'through'=>$h['witness']['through'],'events'=>$h['events']]);
        unset($h);
        $expected='RTC_DERE_PUBLIC_PERIOD_PUBLIC_EVENT_RECEIVED_WHILE_PERIOD_CLOSED';
    } elseif ($case==='scalar-event') {
        $input['request']['current_public_offer_return']['period_history']['events']=[1];
        $expected='RTC_DERE_PUBLIC_BINDING_PERIOD_EVENT_OBJECT_REQUIRED';
    } elseif ($case==='single-xml') {
        $input['request']['current_public_offer_return']=['synthetic'=>true,'xml'=>'<unsupported/>'];
        $expected='RTC_DERE_BCN_SETTLEMENT_PUBLIC_EVENT_CHAIN_REQUIRED_BY_PERIOD_HISTORY_POLICY';
    }
    $result=(new DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan($input['operation'],$input['month'],$input['gross'],
        new DateTimeImmutable($input['known_at']),$input['rules'],$input['request'],$input['policy'],resource_path('fiscal/sources/official'));
    if ($expected===null) {
        expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
            ->and($result['composition']['active_version_binding']['period_history_qualification']['reopening_count'])->toBe(2)
            ->and($result['ibs_nominal_proposal'])->toBe('0.74')->and($result['cbs_nominal_proposal'])->toBe('7.02')
            ->and($result['production_ready'])->toBeFalse();
    } else {
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($expected)
            ->and($result['production_ready'])->toBeFalse();
    }
})->with(['valid','disabled','missing','month-hash','history-hash','closed','scalar-event','single-xml']);
'''
integration=r'''<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Support\Facades\Artisan;

it('emits active public bytes only after contextual reopening proof and refuses failures before writes', function (string $case): void {
    $base=storage_path('app/phase4b-dere9121-63-period-integration-20261013/');
    $request=json_decode((string)file_get_contents($base.'command-request.json'),true,512,JSON_THROW_ON_ERROR);
    if ($case==='missing') {
        unset($request['current_public_offer_return']['period_history']);
    } elseif ($case==='month-hash') {
        $request['current_public_offer_return']['period_history']['monthly_input_hash']=str_repeat('0',64);
    } elseif ($case==='history-hash') {
        $request['current_public_offer_return']['period_history']['witness']['history_hash']=str_repeat('0',64);
    } elseif ($case==='closed') {
        $h=&$request['current_public_offer_return']['period_history'];
        array_pop($h['events']);
        $h['witness']['history_hash']=CanonicalJson::hash(['supplier_id'=>$h['witness']['supplier_id'],'period'=>$h['witness']['period'],
            'initial_state'=>$h['witness']['initial_state'],'from'=>$h['witness']['from'],'through'=>$h['witness']['through'],'events'=>$h['events']]);
        unset($h);
    } elseif ($case==='single-xml') {
        $request['current_public_offer_return']=['synthetic'=>true,'xml'=>'<unsupported/>'];
    }
    $file=tempnam(sys_get_temp_dir(),'dere63-period-');
    if ($file===false) throw new RuntimeException('Cannot create test request.');
    $candidate=$file.'.xml';
    $report=$file.'.report.json';
    try {
        file_put_contents($file,json_encode($request,JSON_THROW_ON_ERROR));
        $exit=Artisan::call('fiscal:dere:project-monthly-closing',['snapshot'=>$base.'financial-snapshot.json','month'=>$base.'command-month.json',
            '--bundle-manifest'=>'bundles/consumption-phase4b-2026.10.13-28-draft63.json','--negative-base-settlement-request'=>$file,
            '--candidate-output'=>$candidate,'--report'=>$report]);
        $result=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        if ($case==='valid') {
            $events=$request['current_public_offer_return']['events'];
            expect($exit)->toBe(0)->and(count($result['event_outputs']))->toBe(6)
                ->and((string)file_get_contents($candidate.'.D2101.xml'))->toBe($events[array_key_last($events)]['origin_xml'])
                ->and($result['negative_base_settlement']['composition']['active_version_binding']['period_history_qualification']['reopening_count'])->toBe(2)
                ->and($result['negative_base_settlement']['ibs_nominal_proposal'])->toBe('0.74')
                ->and($result['negative_base_settlement']['cbs_nominal_proposal'])->toBe('7.02')
                ->and($result['production_ready'])->toBeFalse();
        } else {
            expect($exit)->toBe(1)->and(is_file($candidate))->toBeFalse()->and(is_file($report))->toBeFalse();
            foreach (['D1001','D1011','D1101','D1121','D2101'] as $event) {
                expect(is_file($candidate.'.'.$event.'.xml'))->toBeFalse();
            }
        }
    } finally {
        foreach ([$file,$candidate,$report,$candidate.'.D1001.xml',$candidate.'.D1011.xml',$candidate.'.D1101.xml',$candidate.'.D1121.xml',$candidate.'.D2101.xml'] as $path) {
            if (is_file($path)) unlink($path);
        }
    }
})->with(['valid','missing','month-hash','history-hash','closed','single-xml']);
'''
for path,source in [(ROOT/'tests/Fiscal/Unit/DerePublicOfferPeriodSettlement63Test.php',unit),
    (ROOT/'tests/Fiscal/Integration/DerePublicOfferPeriodMonthly63Test.php',integration)]:
    with path.open('x',encoding='utf-8') as f:
        f.write(source)
print('PASS:own63 planner and monthly command tests prepared from captured contextual period proofs')
