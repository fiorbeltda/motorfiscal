"""Prepare conversion before partial allocation; keep the live engine unchanged."""
from pathlib import Path
import hashlib, json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
live = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
text = live.read_text(encoding='utf-8')
with (E / 'before-partial-export-engine.php').open('xb') as stream: stream.write(live.read_bytes())
before = """                if ($facts->value('rtc.gross_currency') !== 'BRL') {
                    return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_CURRENCY_CONVERSION_NOT_MATERIALIZED'], [], $trace);
                }
                $gross = DecimalValue::fromString((string) $facts->value('rtc.gross_amount'));"""
after = """                $exportGross = (string) $facts->value('rtc.gross_amount');
                if ($facts->value('rtc.gross_currency') !== 'BRL') {
                    $policy = $this->codes['ordinary_partial_export_fx'] ?? null;
                    if (! is_array($policy)) {
                        return $this->stop('ACTION_REQUIRED', ['RTC_PARTIAL_EXPORT_CURRENCY_CONVERSION_NOT_MATERIALIZED'], [], $trace);
                    }
                    $fx = (new PartialExportFxTransactionPrototype73)->qualify($facts->canonicalValues(), $date, $knownAt, $policy);
                    $trace[] = ['stage' => 'PARTIAL_EXPORT_FX_TRANSACTION', ...$fx];
                    if (($fx['status'] ?? null) !== 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND') {
                        $missing = is_array($fx['missing_facts'] ?? null)
                            ? array_values(array_filter($fx['missing_facts'], static fn (mixed $key): bool => is_string($key))) : [];
                        $reason = $fx['reason'] ?? 'RTC_PARTIAL_EXPORT_FX_TRANSACTION_UNPROVEN';
                        return $this->stop('ACTION_REQUIRED', [is_string($reason) ? $reason : 'RTC_PARTIAL_EXPORT_FX_TRANSACTION_UNPROVEN'], $missing, $trace);
                    }
                    $exportGross = $fx['converted_gross_brl'];
                }
                $gross = DecimalValue::fromString($exportGross);"""
assert text.count(before) == 1
text = text.replace(before, after).replace('class ConsumptionReformEngine', 'class PartialExportEnginePrototype73')
target = E / 'planned-partial-export-engine.php'
with target.open('x', encoding='utf-8') as stream: stream.write(text)
with (E / 'partial-export-engine-preparation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'EXACT_CONVERSION_BEFORE_PARTIAL_ALLOCATION_ENGINE_PROTOTYPE_NOT_INSTALLED',
               'before_sha256': hashlib.sha256(live.read_bytes()).hexdigest(),
               'prototype_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
               'app_installed': False, 'production_ready': False}, stream, indent=2)
print('PASS:engine prototype converts before foreign/domestic allocation; live engine unchanged')
