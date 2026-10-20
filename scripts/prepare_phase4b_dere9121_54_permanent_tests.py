"""Promote independent prototype fixtures and mutations to permanent application tests."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
driver = (E / 'exercise-planned-reader-v2.php').read_text(encoding='utf-8')
fixture = driver.split('$context = ', 1)[1].split('$cases = [];', 1)[0]
fixture = '$context = ' + fixture
fixture = fixture.replace('__DIR__', "storage_path('app/phase4b-dere9121-54-preparation-20261013')")
marker = " as $case) {\n"
assert driver.count(marker) == 1
body = driver.split(marker, 1)[1].split("    $cases[$case] = $result;", 1)[0]
before = body.split('    $expected = ', 1)[0]
source = '''<?php
declare(strict_types=1);
use Tests\\TestCase;
uses(TestCase::class);

/** @return array<string, mixed> */
function dere9121Fixture54(): array
{
'''
source += fixture
source += "return compact('scope', 'return', 'origin', 'positiveReturn', 'positiveOrigin', 'positiveScope', 'reader', 'sources');\n}\n"
source += "it('reconciles only explicit synthetic D9121 against exact native D2101 bytes and context', function (string $case): void {\n"
source += "$fixture = dere9121Fixture54();\n"
for name in ['scope', 'return', 'origin', 'positiveReturn', 'positiveOrigin', 'positiveScope', 'reader', 'sources']:
    source += '$' + name + " = $fixture['" + name + "'];\n"
source += before
source += '''
    $expected = in_array($case, ['zero', 'positive'], true) ? 'LOCAL_D9121_AGGREGATE_RECONCILED' : 'ACTION_REQUIRED';
    expect($result['status'])->toBe($expected)->and($result['production_ready'])->toBeFalse()
        ->and($result['received_return_verified'])->toBeFalse()->and($result['eligible_for_posting'])->toBeFalse();
    if (in_array($case, ['zero', 'positive'], true)) {
        expect($result['total_vApur'])->toBe($case === 'zero' ? '0.00' : '98.17')
            ->and($result['composition_integrated'])->toBeFalse();
    } else {
        $reason = match ($case) {
            'real', 'origin-hash' => 'EXPLICIT_SYNTHETIC_BOUND_SCOPE_REQUIRED',
            'amount' => 'ORIGIN_AGGREGATE_AMOUNT_CONFLICT',
            'missing-total' => 'TOTALIZATION_REQUIRED',
            'production-origin', 'excluded-origin' => 'SANDBOX_NON_EXCLUSION_ORIGIN_REQUIRED',
            default => 'RETURN_OR_ORIGIN_CONTEXT_CONFLICT',
        };
        expect($result['reason'])->toBe('RTC_DERE_D9121_'.$reason);
    }
})->with(['zero', 'positive', 'real', 'origin-hash', 'supplier', 'period', 'sequence', 'pgcc', 'amount', 'return-hash',
    'event-id', 'error', 'wrong-type', 'missing-total', 'production-origin', 'excluded-origin']);
'''
target = ROOT / 'tests/Fiscal/Unit/DerePublicOfferDebtReturn54Test.php'
with target.open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS:16 permanent reader tests prepared from independently exercised fixtures')
