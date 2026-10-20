<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Source-bound ordinary-import conversion; no tax/document/credit authorization. */
final readonly class ImportFxQuoteConverter
{
    /** @param array<string,mixed> $context
     * @param  array<string,array<string,mixed>>  $sources
     * @return array<string,mixed>
     */
    public function convert(string $amount, string $currency, string $taxPoint, DateTimeImmutable $knownAt, array $context, array $sources): array
    {
        if (($context['synthetic'] ?? null) !== true || ($context['operation_kind'] ?? null) !== 'ORDINARY_SERVICE_IMPORT'
            || ($context['side'] ?? null) !== 'SALE' || ($context['bulletin'] ?? null) !== 'Fechamento'
            || ! preg_match('/^[A-Z]{3}$/', $currency) || $currency === 'BRL'
            || ! preg_match('/^\d{1,15}(?:\.\d{1,8})?$/', $amount)) {
            return $this->stop('EXPLICIT_SCOPE_AMOUNT_AND_SALE_CLOSING_REQUIRED');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $taxPoint);
        if ($date === false || $date->format('Y-m-d') !== $taxPoint) {
            return $this->stop('TAX_POINT_DATE_INVALID');
        }
        $reference = $date->modify('-1 day')->format('Y-m-d');
        $id = $context['source_id'] ?? null;
        if (! is_string($id) || ! isset($sources[$id])) {
            return $this->stop('TRUSTED_QUOTE_SOURCE_REQUIRED');
        }
        $source = $sources[$id];
        if (($source['currency'] ?? null) !== $currency || ! is_string($source['raw_json'] ?? null)
            || ! is_string($source['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $source['sha256'])
            || hash('sha256', $source['raw_json']) !== $source['sha256'] || ! is_string($source['url'] ?? null)
            || ! is_string($source['observed_at'] ?? null)) {
            return $this->stop('SOURCE_CURRENCY_BYTES_OR_HASH_CONFLICT');
        }
        $url = parse_url($source['url']);
        if ($url === false || ($url['scheme'] ?? null) !== 'https' || ($url['host'] ?? null) !== 'olinda.bcb.gov.br'
            || ($url['path'] ?? null) !== '/olinda/servico/PTAX/versao/v1/odata/CotacaoMoedaPeriodo(moeda=@moeda,dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)'
            || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || isset($url['port'])) {
            return $this->stop('SOURCE_ENDPOINT_UNPROVEN');
        }
        parse_str($url['query'] ?? '', $query);
        $keys = array_keys($query);
        sort($keys);
        $expected = ['$format', '@dataFinalCotacao', '@dataInicial', '@moeda'];
        sort($expected);
        if ($keys !== $expected || ($query['$format'] ?? null) !== 'json' || ($query['@moeda'] ?? null) !== "'".$currency."'") {
            return $this->stop('COMPLETE_SOURCE_QUERY_REQUIRED');
        }
        $from = $this->queryDate($query['@dataInicial'] ?? null);
        $through = $this->queryDate($query['@dataFinalCotacao'] ?? null);
        if ($from === null || $through === null || $from > $reference || $through < $reference || $from > $through) {
            return $this->stop('SOURCE_RANGE_DOES_NOT_COVER_REFERENCE_DAY');
        }
        try {
            $observed = new DateTimeImmutable($source['observed_at']);
            if ($observed > $knownAt || $taxPoint > $knownAt->format('Y-m-d')) {
                return $this->stop('SOURCE_OR_TAX_POINT_NOT_KNOWN');
            }
            $typed = json_decode($source['raw_json'], true, 512, JSON_THROW_ON_ERROR);
            $decoded = $this->exactNumberTokens($source['raw_json']);
        } catch (Throwable) {
            return $this->stop('SOURCE_DATE_OR_JSON_INVALID');
        }
        if (! is_array($decoded) || ! is_array($decoded['value'] ?? null) || ! array_is_list($decoded['value'])
            || array_key_exists('@odata.nextLink', $decoded)) {
            return $this->stop('COMPLETE_SOURCE_RESPONSE_REQUIRED');
        }
        $rows = [];
        foreach ($decoded['value'] as $index => $row) {
            if (! is_array($row) || ! is_string($row['tipoBoletim'] ?? null) || ! is_string($row['dataHoraCotacao'] ?? null)
                || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/', $row['dataHoraCotacao'])) {
                return $this->stop('QUOTE_ROW_STRUCTURE_INVALID');
            }
            $clock = $row['dataHoraCotacao'];
            if (! str_contains($clock, '.')) {
                $clock .= '.0';
            }
            $published = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $clock, new DateTimeZone('America/Sao_Paulo'));
            if ($published === false || $published->format('Y-m-d H:i:s') !== substr($clock, 0, 19)
                || $published > $knownAt || $published->format('Y-m-d') < $from || $published->format('Y-m-d') > $through) {
                return $this->stop('QUOTE_DATE_OR_KNOWLEDGE_CONFLICT');
            }
            if ($row['tipoBoletim'] !== 'Fechamento') {
                continue;
            }
            $rate = $row['cotacaoVenda'] ?? null;
            $typedRow = is_array($typed) && is_array($typed['value'] ?? null) ? ($typed['value'][$index] ?? null) : null;
            $typedRate = is_array($typedRow) ? ($typedRow['cotacaoVenda'] ?? null) : null;
            // The native decoder is used only to validate JSON numeric type. Arithmetic uses the original decimal token.
            if ((! is_int($typedRate) && ! is_float($typedRate)) || ! is_string($rate)
                || ! preg_match('/^\d{1,10}(?:\.\d{1,8})?$/', $rate) || BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {
                return $this->stop('POSITIVE_SALE_BRL_PER_UNIT_QUOTE_REQUIRED');
            }
            $day = $published->format('Y-m-d');
            if (isset($rows[$day])) {
                return $this->stop('AMBIGUOUS_CLOSING_QUOTES');
            }
            $normalizedRate = str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
            $rows[$day] = ['quote_date' => $day, 'published_at' => $published->format(DATE_ATOM), 'rate' => $normalizedRate];
        }
        $eligible = array_filter($rows, static fn (array $row): bool => $row['quote_date'] <= $reference);
        if ($eligible === []) {
            return $this->stop('CLOSING_QUOTE_NOT_AVAILABLE_IN_PROVEN_RANGE');
        }
        ksort($eligible);
        $selected = end($eligible);
        if ($currency !== 'USD' && $selected['quote_date'] !== $reference) {
            return $this->stop('NON_USD_PREVIOUS_DAY_FALLBACK_RULE_UNQUALIFIED');
        }
        if (array_key_exists('expected_quote_date', $context) && $context['expected_quote_date'] !== $selected['quote_date']) {
            return $this->stop('DECLARED_QUOTE_DATE_CONFLICT');
        }
        if (array_key_exists('expected_rate', $context) && $context['expected_rate'] !== $selected['rate']) {
            return $this->stop('DECLARED_RATE_CONFLICT');
        }
        $product = BigDecimal::of($amount)->multipliedBy($selected['rate']);
        $intermediate = $product->toScale(8, RoundingMode::HalfUp);
        $converted = $product->toScale(2, RoundingMode::HalfUp);

        return ['status' => 'LOCAL_SOURCE_BOUND_IMPORT_FX_CONVERSION', 'original_amount' => $amount, 'original_currency' => $currency,
            'tax_point_date' => $taxPoint, 'reference_date' => $reference, 'selected_quote' => $selected, 'side' => 'SALE', 'bulletin' => 'Fechamento',
            'rate_unit' => 'BRL_PER_ORIGINAL_CURRENCY_UNIT', 'used_usd_last_available' => $selected['quote_date'] !== $reference,
            'product_exact' => (string) $product, 'intermediate_brl' => (string) $intermediate, 'converted_gross_brl' => (string) $converted,
            'rounding' => 'EXPLICIT_RTC_HALF_UP_FINAL_FROM_EXACT_PRODUCT_8_DIGIT_MEMORY', 'source_id' => $id, 'source_url' => $source['url'],
            'raw_source_sha256' => $source['sha256'], 'source_observed_at' => $source['observed_at'],
            'context_hash' => CanonicalJson::hash($context), 'source_authenticity_externally_verified' => false,
            'operation_document_binding_verified' => false, 'tax_or_credit_authorized' => false, 'production_ready' => false];
    }

    private function exactNumberTokens(string $raw): mixed
    {
        $slash = chr(92);
        $stringToken = '"(?:[^"'.$slash.$slash.']|'.$slash.$slash.'.)*"';
        $pattern = '~'.$stringToken.'(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:[.][0-9]+)?(?:[eE][+-]?[0-9]+)?~';
        $preserved = preg_replace_callback($pattern,
            static fn (array $match): string => json_encode($match[0], JSON_THROW_ON_ERROR), $raw);
        if (! is_string($preserved)) {
            throw new \RuntimeException('Cannot preserve exact JSON number tokens.');
        }

        return json_decode($preserved, true, 512, JSON_THROW_ON_ERROR);
    }

    private function queryDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match("/^'(\\d{2}-\\d{2}-\\d{4})'$/", $value, $parts)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!m-d-Y', $parts[1]);

        return $date !== false && $date->format('m-d-Y') === $parts[1] ? $date->format('Y-m-d') : null;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_IMPORT_FX_'.$reason, 'production_ready' => false];
    }
}
