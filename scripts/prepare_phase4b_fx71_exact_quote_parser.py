"""Prepare exact JSON number tokens for rate calculation; live69 converter remains unchanged."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx71-exact-quote-preparation-20261014';E.mkdir(exist_ok=True)
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
source=path.read_text(encoding='utf-8')
with (E/'before-exact-quote-converter.php').open('xb') as f:f.write(path.read_bytes())
source=source.replace('final readonly class ImportFxQuoteConverter','final readonly class ImportFxExactQuotePrototype71')
anchor="            $decoded = json_decode($source['raw_json'], true, 512, JSON_THROW_ON_ERROR);"
assert source.count(anchor)==1
source=source.replace(anchor,"""            $typed = json_decode($source['raw_json'], true, 512, JSON_THROW_ON_ERROR);
            $decoded = $this->exactNumberTokens($source['raw_json']);""")
source=source.replace("foreach ($decoded['value'] as $row)","foreach ($decoded['value'] as $index => $row)")
anchor="""            if ((! is_int($rate) && ! is_float($rate)) || ! is_finite((float) $rate)
                || ! preg_match('/^\\d{1,10}(?:\\.\\d{1,8})?$/', (string) $rate) || BigDecimal::of((string) $rate)->isLessThanOrEqualTo(0)) {"""
assert source.count(anchor)==1
source=source.replace(anchor,"""            $typedRow = is_array($typed) && is_array($typed['value'] ?? null) ? ($typed['value'][$index] ?? null) : null;
            $typedRate = is_array($typedRow) ? ($typedRow['cotacaoVenda'] ?? null) : null;
            // The native decoder is used only to validate JSON numeric type. Arithmetic uses the original decimal token.
            if ((! is_int($typedRate) && ! is_float($typedRate)) || ! is_string($rate)
                || ! preg_match('/^\\d{1,10}(?:\\.\\d{1,8})?$/', $rate) || BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {""")
anchor="'rate' => (string) $rate"
assert source.count(anchor)==1
source=source.replace(anchor,"'rate' => (string) BigDecimal::of($rate)->stripTrailingZeros()")
anchor='    private function queryDate(mixed $value): ?string'
assert source.count(anchor)==1
helper="""    private function exactNumberTokens(string $raw): mixed
    {
        $slash = chr(92);
        $stringToken = '\"(?:[^\"'.$slash.$slash.']|'.$slash.$slash.'.)*\"';
        $pattern = '~'.$stringToken.'(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:[.][0-9]+)?(?:[eE][+-]?[0-9]+)?~';
        $preserved = preg_replace_callback($pattern,
            static fn (array $match): string => json_encode($match[0], JSON_THROW_ON_ERROR), $raw);
        if (! is_string($preserved)) {
            throw new \\RuntimeException('Cannot preserve exact JSON number tokens.');
        }
        return json_decode($preserved, true, 512, JSON_THROW_ON_ERROR);
    }

"""
source=source.replace(anchor,helper+anchor)
target=E/'planned-exact-quote-converter.php'
with target.open('x',encoding='utf-8') as f:f.write(source)
with (E/'exact-quote-preparation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'EXACT_QUOTE_NUMBER_TOKEN_PROTOTYPE_PREPARED_NO_RUNTIME_CHANGE',
        'before_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'prototype_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
        'typed_decoder_used_for_schema_type_only':True,'arithmetic_uses_exact_source_token':True,'production_ready':False},f,indent=2)
print('PASS:exact rate-token prototype prepared without changing live69 generation')
