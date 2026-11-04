<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;

// B16 (4B.8.12/4B.8.5): period scenario matrix 2026-2033 over the active rate catalog (read-only use of the
// Agent A temporal selector). Three scenario kinds: official parameter available, unavailable (explicit
// refusal) and synthetic identified parameter. No future rate is invented.

function b16Catalog(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-rates-2026.09.25-1.json'), true, 512, JSON_THROW_ON_ERROR);
}

function b16Law(): string
{
    static $text = null;
    if ($text === null) {
        $raw = (string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm');
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $text;
}

$known = new DateTimeImmutable('2026-11-03T12:00:00+00:00');

it('matches the official 2026-2028 rates to the current law text', function (): void {
    foreach ([
        'Em relação aos fatos geradores ocorridos de 1º de janeiro a 31 de dezembro de 2026, o IBS será cobrado mediante aplicação da alíquota estadual de 0,1% (um décimo por cento).',
        'Em relação aos fatos geradores ocorridos de 1º de janeiro a 31 de dezembro de 2026, a CBS será cobrada mediante aplicação da alíquota de 0,9% (nove décimos por cento).',
        'Em relação aos fatos geradores ocorridos de 1º de janeiro de 2027 a 31 de dezembro de 2028, o IBS será cobrado à alíquota estadual de 0,05% (cinco centésimos por cento) e à alíquota municipal de 0,05% (cinco centésimos por cento).',
        'Em relação aos fatos geradores ocorridos de 1º de janeiro de 2027 a 31 de dezembro de 2028, a alíquota da CBS será aquela fixada nos termos do inciso I do caput e dos §§ 2º e 3º, todos do art. 14, reduzida em 0,1 (um décimo) ponto percentual',
    ] as $excerpt) {
        expect(substr_count(b16Law(), $excerpt))->toBe(1);
    }
});

it('resolves official parameters where they exist and refuses explicitly where they do not (2026-2033)', function (string $date, string $tax, ?string $percent) use ($known): void {
    $result = (new TemporalRateCatalog(b16Catalog()))->resolve($tax, 'BR', LocalDate::fromString($date), $known);
    if ($percent === null) {
        expect($result['status'])->toBe('NOT_FOUND')->and($result['reason'])->toBe('RTC_RATE_NOT_PROVEN:'.$tax)->and($result)->not->toHaveKey('row');
    } else {
        expect($result['status'])->toBe('RESOLVED')->and($result['row']['percent'])->toBe($percent)
            ->and($result['row']['source_id'])->toStartWith('LC214-ART');
    }
})->with(function (): array {
    $rows = [];
    $expected = ['2026' => ['IBS_STATE' => '0.10', 'IBS_MUNICIPAL' => '0.00', 'CBS' => '0.90'],
        '2027' => ['IBS_STATE' => '0.05', 'IBS_MUNICIPAL' => '0.05', 'CBS' => null],
        '2028' => ['IBS_STATE' => '0.05', 'IBS_MUNICIPAL' => '0.05', 'CBS' => null]];
    foreach (range(2026, 2033) as $year) {
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $tax) {
            foreach (['01-01', '06-30', '12-31'] as $day) {
                $rows["{$year}-{$day} {$tax}"] = ["{$year}-{$day}", $tax, $expected[(string) $year][$tax] ?? null];
            }
        }
    }

    return $rows;
});

it('switches exactly at the period boundary and respects knowledge and lifecycle', function () use ($known): void {
    $catalog = new TemporalRateCatalog(b16Catalog());
    expect($catalog->resolve('CBS', 'BR', LocalDate::fromString('2026-12-31'), $known)['status'])->toBe('RESOLVED')
        ->and($catalog->resolve('CBS', 'BR', LocalDate::fromString('2027-01-01'), $known)['status'])->toBe('NOT_FOUND')
        ->and($catalog->resolve('IBS_STATE', 'BR', LocalDate::fromString('2026-12-31'), $known)['row']['percent'])->toBe('0.10')
        ->and($catalog->resolve('IBS_STATE', 'BR', LocalDate::fromString('2027-01-01'), $known)['row']['percent'])->toBe('0.05')
        ->and($catalog->resolve('CBS', 'BR', LocalDate::fromString('2026-06-30'), new DateTimeImmutable('2025-01-15T23:59:59-03:00'))['status'])->toBe('NOT_FOUND');
    $data = b16Catalog();
    $data['rates'][2]['revoked_at'] = '2026-10-01T00:00:00+00:00';
    expect((new TemporalRateCatalog($data))->resolve('CBS', 'BR', LocalDate::fromString('2026-06-30'), $known)['status'])->toBe('NOT_FOUND');
    $dup = b16Catalog();
    $dup['rates'][] = $dup['rates'][2];
    expect((new TemporalRateCatalog($dup))->resolve('CBS', 'BR', LocalDate::fromString('2026-06-30'), $known)['status'])->toBe('CONFLICT');
});

it('refuses an ACTIVE synthetic row as an official rate after the A155 fix (H4)', function () use ($known): void {
    $data = b16Catalog();
    $data['rates'][] = ['tax' => 'CBS', 'jurisdiction' => 'BR', 'percent' => '8.80', 'valid_from' => '2027-01-01', 'valid_to' => '2027-12-31',
        'published_at' => '2026-11-01T00:00:00+00:00', 'status' => 'ACTIVE', 'source_id' => 'SYNTHETIC.TEST.B16', 'source_hash' => str_repeat('0', 64),
        'synthetic' => true];
    $result = (new TemporalRateCatalog($data))->resolve('CBS', 'BR', LocalDate::fromString('2027-06-30'), $known);
    // Historical gap (until 213f2c5): the synthetic row was RESOLVED like an official one. A155/H4 now skips it;
    // the period stays without a proven rate and the response flags the refused row as non-official.
    expect($result['status'])->toBe('NOT_FOUND')->and($result['reason'])->toBe('RTC_RATE_NOT_PROVEN:CBS')
        ->and($result['official'])->toBeFalse()->and($result)->not->toHaveKey('row');
    // The same row marked as official is still resolved, and catalogs without markers keep their legacy bytes.
    $official = $data;
    $official['rates'][array_key_last($official['rates'])] = [...$data['rates'][array_key_last($data['rates'])], 'synthetic' => false, 'source_kind' => 'OFFICIAL'];
    $resolved = (new TemporalRateCatalog($official))->resolve('CBS', 'BR', LocalDate::fromString('2027-06-30'), $known);
    expect($resolved['status'])->toBe('RESOLVED')->and($resolved['official'])->toBeTrue();
    expect((new TemporalRateCatalog(b16Catalog()))->resolve('CBS', 'BR', LocalDate::fromString('2026-06-30'), $known))->not->toHaveKey('official');
});
