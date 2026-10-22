<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CIndOpReference126;
use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\ConsumptionReform\DocumentSchedule131;
use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\ConsumptionReform\ImportResponsibility129;
use App\Modules\Fiscal\Core\Shared\LocalDate;

it('selects every statutory row exactly on and before its mandatory date', function (): void {
    $engine = new DocumentSchedule131;
    $codes = ['version' => DocumentSchedule131::VERSION, 'document_schedule_policy131' => DocumentSchedule131::policy()];
    foreach (DocumentSchedule131::rows() as $row) {
        $date = new DateTimeImmutable($row['mandatory_from']);
        foreach ([[$date, true], [$date->modify('-1 day'), false]] as [$day, $required]) {
            $result = $engine->resolve($row['document'], $row['profile'], 'REGULAR', LocalDate::fromString($day->format('Y-m-d')),
                new DateTimeImmutable('2026-10-22T18:00:00+00:00'), $codes);
            expect($result['status'])->toBe('RESOLVED')->and($result['required_by_schedule'])->toBe($required)
                ->and($result['source_article'])->toBe($row['locator'])->and($result['document_obligation_complete'])->toBeFalse();
        }
    }
    expect(count(DocumentSchedule131::rows()))->toBe(32);
});

it('applies Simples and MEI dates independently of the ordinary profile date', function (string $regime): void {
    $engine = new DocumentSchedule131;
    $codes = ['document_schedule_policy131' => DocumentSchedule131::policy()];
    foreach (DocumentSchedule131::rows() as $row) {
        $result = $engine->resolve($row['document'], $row['profile'], $regime, LocalDate::fromString('2026-12-31'),
            new DateTimeImmutable('2026-10-22T18:00:00+00:00'), $codes);
        expect($result['mandatory_from'])->toBe('2027-01-01')->and($result['required_by_schedule'])->toBeFalse()
            ->and($result['exception_article'])->toBe('art1 §1');
    }
})->with(['SIMPLES_NACIONAL', 'MEI']);

it('rejects missing altered policies early knowledge and prior legislation contexts', function (string $case): void {
    $codes = ['version' => DocumentSchedule131::VERSION, 'document_schedule_policy131' => DocumentSchedule131::policy()];
    $known = new DateTimeImmutable('2026-10-22T18:00:00+00:00');
    $date = '2026-10-01';
    switch ($case) {
        case 'removed': unset($codes['document_schedule_policy131']);
            break;
        case 'changed': $codes['document_schedule_policy131']['rows'][0]['mandatory_from'] = '2026-01-01';
            break;
        case 'early': $known = new DateTimeImmutable('2026-10-22T17:49:15+00:00');
            break;
        case 'prior': $date = '2026-07-30';
            break;
    }
    expect(DocumentSchedule131::required($codes))->toBeTrue()
        ->and((new DocumentSchedule131)->resolve('NFSE', 'ISS_GENERAL', 'REGULAR', LocalDate::fromString($date), $known, $codes)['status'])->toBe('ACTION_REQUIRED');
})->with(['removed', 'changed', 'early', 'prior']);

it('matches independently specified dates and precedence from art1', function (string $document, string $profile, string $expected): void {
    $result = (new DocumentSchedule131)->resolve($document, $profile, 'REGULAR', LocalDate::fromString('2026-10-22'),
        new DateTimeImmutable('2026-10-22T18:00:00+00:00'), ['document_schedule_policy131' => DocumentSchedule131::policy()]);
    expect($result['mandatory_from'])->toBe($expected);
})->with([
    ['NFSE', 'ISS_GENERAL', '2026-10-01'], ['NFSE', 'PLATFORM_ART22', '2026-12-01'],
    ['NFSE', 'ISS_0103_0105_0109', '2026-12-01'], ['NFSE', 'ISS_1601', '2026-12-01'],
    ['NFE', 'GENERAL', '2026-08-03'], ['NFE', 'MONOPHASIC', '2027-01-01'],
    ['NFE', 'IMPORT_GOODS', '2027-01-01'], ['NFE', 'NON_ICMS_TAXPAYER', '2026-12-01'],
    ['DERE', 'MONTHLY', '2026-11-15'], ['DERE', 'TABLE', '2026-10-01'], ['DERE', 'OTHER', '2027-01-01'],
]);

it('does not collapse a qualified platform schedule into ordinary ISS services', function (): void {
    $engine = new DocumentSchedule131;
    $codes = ['document_schedule_policy131' => DocumentSchedule131::policy()];
    $on = LocalDate::fromString('2026-10-01');
    $known = new DateTimeImmutable('2026-10-22T18:00:00+00:00');
    expect($engine->nfse('010101', 'REGULAR', $on, $known, $codes)['required_by_schedule'])->toBeTrue()
        ->and($engine->nfse('010101', 'REGULAR', $on, $known, $codes, true)['required_by_schedule'])->toBeFalse();
    $monthly = $engine->resolve('DERE', 'MONTHLY', 'REGULAR', $on, $known, $codes);
    expect($monthly['monthly_schedule']['first_period'])->toBe('2026-10')
        ->and($monthly['monthly_schedule']['following_month_due_day'])->toBe(15)
        ->and($monthly['monthly_schedule']['business_day_adjustment_assessed'])->toBeFalse();
});

it('keeps inherited version guards even without optional policy markers', function (): void {
    $codes = ['version' => DocumentSchedule131::VERSION];
    expect(ImportResponsibility129::required($codes))->toBeTrue()->and(DpsForeignPolicy128::required($codes))->toBeTrue()
        ->and(DpsLayoutPolicy127::required($codes))->toBeTrue()->and(CIndOpReference126::required($codes))->toBeTrue()
        ->and(CreditInputPolicy122::required($codes))->toBeTrue();
});
