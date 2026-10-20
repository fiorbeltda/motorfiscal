"""Prepare stricter contextual dates separately from the live68 generation under full test."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx69-temporal-preparation-20261014'
E.mkdir(exist_ok=True)
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
source=path.read_text(encoding='utf-8')
with (E/'before-temporal-transaction.php').open('xb') as f:f.write(path.read_bytes())
source=source.replace('final readonly class ImportFxTransactionQualifier','final readonly class ImportFxTransactionTemporalPrototype69')
anchor='        $material = $policy;'
assert source.count(anchor)==1
source=source.replace(anchor,"""        if (! $this->date($taxPoint) || ! $this->date($policy['valid_from'] ?? null)
            || (isset($policy['valid_to']) && ! $this->date($policy['valid_to']))
            || (isset($policy['valid_to']) && $policy['valid_from'] > $policy['valid_to'])
            || ! $this->timestamp($policy['published_at'] ?? null)) {
            return $this->stop('POLICY_EXACT_DATE_REQUIRED');
        }
"""+anchor)
anchor='        $proofMaterial = $proof;'
assert source.count(anchor)==1
source=source.replace(anchor,"""        if (! $this->date($proof['valid_from'] ?? null) || ! $this->date($proof['valid_to'] ?? null)
            || $proof['valid_from'] > $proof['valid_to'] || ! $this->timestamp($proof['observed_at'] ?? null)) {
            return $this->stop('TRANSACTION_EXACT_DATE_REQUIRED');
        }
"""+anchor)
anchor='    /** @param list<string> $missing'
assert source.count(anchor)==1
methods="""    private function date(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function timestamp(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^(\\d{4}-\\d{2}-\\d{2})T(\\d{2}:\\d{2}:\\d{2})(?:\\.(\\d{1,6}))?([+-]\\d{2}:\\d{2})$/', $value, $parts)) {
            return false;
        }
        $fraction = str_pad($parts[3] ?? '', 6, '0');
        $normalized = $parts[1].'T'.$parts[2].'.'.$fraction.$parts[4];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.uP', $normalized);
        return $date !== false && $date->format('Y-m-d\\TH:i:s.uP') === $normalized;
    }

"""
source=source.replace(anchor,methods+anchor)
target=E/'planned-temporal-transaction.php'
with target.open('x',encoding='utf-8') as f:f.write(source)
with (E/'temporal-preparation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'STRICT_FX_CONTEXT_DATE_PROTOTYPE_PREPARED_NO_APP_CHANGE',
        'current_transaction_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),
        'prototype_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),'production_ready':False},f,indent=2)
print('PASS:strict policy/proof date prototype prepared separately; current68 source preserved')
