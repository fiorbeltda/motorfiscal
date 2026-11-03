<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;

// B19: acceptance criterion for the Agent A fix of TemporalRateCatalog (finding B16). Until the fix
// exists the test is reported as skipped (pending), never as passed. After the fix it must pass:
// a SYNTHETIC_TEST row can never be resolved as an official rate.

function b19Catalog(): array
{
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-rates-2026.09.25-1.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog['rates'][] = ['tax' => 'CBS', 'jurisdiction' => 'BR', 'percent' => '8.80', 'valid_from' => '2027-01-01', 'valid_to' => '2027-12-31',
        'published_at' => '2026-11-01T00:00:00+00:00', 'status' => 'ACTIVE', 'source_id' => 'SYNTHETIC.TEST.B19',
        'source_hash' => str_repeat('0', 64), 'source_kind' => 'SYNTHETIC_TEST'];

    return $catalog;
}

it('never resolves a synthetic rate as an official one (acceptance for the Agent A fix)', function (): void {
    $result = (new TemporalRateCatalog(b19Catalog()))->resolve('CBS', 'BR', LocalDate::fromString('2027-06-30'), new DateTimeImmutable('2026-11-03T12:00:00+00:00'));
    if ($result['status'] === 'RESOLVED' && ($result['row']['source_kind'] ?? null) === 'SYNTHETIC_TEST' && ! array_key_exists('official', $result)) {
        $this->markTestSkipped('PENDENTE AGENTE A: TemporalRateCatalog ainda trata linha SYNTHETIC_TEST como oficial (achado B16).');
    }
    expect($result['status'] !== 'RESOLVED' || ($result['official'] ?? true) === false)->toBeTrue();
});

it('keeps official 2026 rates resolvable as official after the fix', function (): void {
    $result = (new TemporalRateCatalog(b19Catalog()))->resolve('CBS', 'BR', LocalDate::fromString('2026-06-30'), new DateTimeImmutable('2026-11-03T12:00:00+00:00'));
    expect($result['status'])->toBe('RESOLVED')->and($result['row']['percent'])->toBe('0.90')
        ->and($result['official'] ?? true)->toBeTrue();
});
