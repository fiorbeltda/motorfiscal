<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Reads an exact closing sale quote for a supplied reference day; contains no fiscal date rule. */
final readonly class PtaxClosingQuote140
{
    /** @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function select(array $source, string $currency, string $referenceDay, DateTimeImmutable $known): array
    {
        $fail = static fn (string $reason): array => ['status' => 'ACTION_REQUIRED', 'reason' => 'PTAX140_'.$reason];
        if (! $this->day($referenceDay) || $referenceDay > $known->format('Y-m-d')
            || ! is_string($source['raw_json'] ?? null) || ! is_string($source['sha256'] ?? null)
            || hash('sha256', $source['raw_json']) !== $source['sha256'] || ($source['currency'] ?? null) !== $currency) {
            return $fail('SOURCE_BYTES_CURRENCY_OR_DAY_CONFLICT');
        }
        $url = is_string($source['url'] ?? null) ? parse_url($source['url']) : false;
        if ($url === false || ($url['scheme'] ?? null) !== 'https' || ($url['host'] ?? null) !== 'olinda.bcb.gov.br'
            || ($url['path'] ?? null) !== '/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)'
            || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])) {
            return $fail('OFFICIAL_SOURCE_ENDPOINT_REQUIRED');
        }
        parse_str($url['query'] ?? '', $query);
        $keys = array_keys($query);
        sort($keys);
        if ($keys !== ['$format', '@dataFinalCotacao', '@dataInicial', '@moeda']
            || ($query['$format'] ?? null) !== 'json' || ($query['@moeda'] ?? null) !== "'".$currency."'") {
            return $fail('COMPLETE_CURRENCY_QUERY_REQUIRED');
        }
        $from = $this->queryDay($query['@dataInicial'] ?? null);
        $to = $this->queryDay($query['@dataFinalCotacao'] ?? null);
        if ($from === null || $to === null || $from > $referenceDay || $to < $referenceDay) {
            return $fail('SOURCE_RANGE_MUST_COVER_EXACT_REFERENCE_DAY');
        }
        try {
            $observed = $this->instant($source['observed_at'] ?? null);
            if ($observed === null || $observed > $known) {
                return $fail('SOURCE_NOT_KNOWN');
            }
            $typed = json_decode($source['raw_json'], true, 512, JSON_THROW_ON_ERROR);
            $slash = chr(92);
            $stringToken = '"(?:[^"'.$slash.$slash.']|'.$slash.$slash.'.)*"';
            $preserved = preg_replace_callback('~'.$stringToken.'(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:[.][0-9]+)?(?:[eE][+-]?[0-9]+)?~',
                static fn (array $m): string => json_encode($m[0], JSON_THROW_ON_ERROR), $source['raw_json']);
            $exact = is_string($preserved) ? json_decode($preserved, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (Throwable) {
            return $fail('SOURCE_JSON_OR_DATE_INVALID');
        }
        if (! is_array($exact) || ! is_array($exact['value'] ?? null) || ! array_is_list($exact['value'])
            || array_key_exists('@odata.nextLink', $exact)) {
            return $fail('COMPLETE_QUOTE_ROWS_REQUIRED');
        }
        $selected = [];
        foreach ($exact['value'] as $index => $row) {
            if (! is_array($row) || ! is_string($row['tipoBoletim'] ?? null)) {
                return $fail('QUOTE_ROW_INVALID');
            }
            if ($row['tipoBoletim'] !== 'Fechamento') {
                continue;
            }
            $stamp = $row['dataHoraCotacao'] ?? null;
            if (! is_string($stamp) || preg_match('/\A(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/', $stamp, $parts) !== 1) {
                return $fail('EXACT_CLOSING_TIMESTAMP_REQUIRED');
            }
            $normalized = $parts[1].'.'.str_pad($parts[2] ?? '', 6, '0');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $normalized, new DateTimeZone('America/Sao_Paulo'));
            if ($date === false || $date->format('Y-m-d H:i:s.u') !== $normalized || $date > $known || $date > $observed) {
                return $fail('CLOSING_QUOTE_TIME_INVALID_OR_NOT_OBSERVED');
            }
            if ($date->format('Y-m-d') !== $referenceDay) {
                continue;
            }
            $rate = $row['cotacaoVenda'] ?? null;
            $number = $typed['value'][$index]['cotacaoVenda'] ?? null;
            if ((! is_int($number) && ! is_float($number)) || ! is_string($rate)
                || preg_match('/\A\d{1,10}(?:\.\d{1,8})?\z/', $rate) !== 1
                || BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {
                return $fail('EXACT_POSITIVE_NUMERIC_SALE_RATE_REQUIRED');
            }
            $selected[] = ['quote_date' => $referenceDay, 'rate' => $rate, 'published_at' => $date->format('Y-m-d\TH:i:s.uP')];
        }
        if (count($selected) !== 1) {
            return $fail($selected === [] ? 'EXACT_DAY_CLOSING_NOT_AVAILABLE' : 'AMBIGUOUS_CLOSING_QUOTES');
        }

        return ['status' => 'EXACT_DAY_BCB_CLOSING_SALE_QUOTE_SELECTED', ...$selected[0],
            'side' => 'SALE', 'bulletin' => 'Fechamento', 'raw_source_sha256' => $source['sha256'],
            'source_observed_at' => $source['observed_at'], 'source_context_hash' => CanonicalJson::hash($source),
            'previous_day_fallback_used' => false];
    }

    private function day(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{6})?[+-]\d{2}:\d{2}\z/', $value) !== 1) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    private function queryDay(mixed $value): ?string
    {
        if (! is_string($value) || preg_match("/\A'(\d{2}-\d{2}-\d{4})'\z/", $value, $m) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!m-d-Y', $m[1]);

        return $date !== false && $date->format('m-d-Y') === $m[1] ? $date->format('Y-m-d') : null;
    }
}
