"""Archive pre-FX engine and enable transaction-bound conversion only for a selected policy."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-import-fx68-integration-20261013'
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php'
source=path.read_text(encoding='utf-8')
anchor="""        if ($market === 'IMPORT' && $facts->value('rtc.gross_currency') !== 'BRL') {
            return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_FX_CONVERSION_NOT_MATERIALIZED'], [], $trace);
        }"""
assert source.count(anchor)==1
assert not (E/'engine-fx-activation.json').exists()
archive=E/'before-import-fx-ConsumptionReformEngine.php'
with archive.open('xb') as f:f.write(path.read_bytes())
before=hashlib.sha256(path.read_bytes()).hexdigest()
replacement="""        $fxTransaction = null;
        if ($market === 'IMPORT' && $facts->value('rtc.gross_currency') !== 'BRL') {
            $fxPolicy = $this->codes['ordinary_import_fx'] ?? null;
            if (! is_array($fxPolicy)) {
                return $this->stop('ACTION_REQUIRED', ['RTC_IMPORT_FX_CONVERSION_NOT_MATERIALIZED'], [], $trace);
            }
            $fxTransaction = (new ImportFxTransactionQualifier)->qualify($facts->canonicalValues(), $date, $knownAt, $fxPolicy);
            $trace[] = ['stage' => 'IMPORT_FX_TRANSACTION', ...$fxTransaction];
            if (($fxTransaction['status'] ?? null) !== 'LOCAL_IMPORT_FX_TRANSACTION_BOUND'
                || ! is_string($fxTransaction['converted_gross_brl'] ?? null)) {
                $reason = $fxTransaction['reason'] ?? 'RTC_IMPORT_FX_CONVERSION_UNPROVEN';
                $missing = $facts->missing(['rtc.import_fx_source_id', 'rtc.import_fx_proof', 'rtc.operation_amount_reconciliation_id']);
                return $this->stop('ACTION_REQUIRED', [is_string($reason) ? $reason : 'RTC_IMPORT_FX_CONVERSION_UNPROVEN'], $missing, $trace);
            }
            $gross = DecimalValue::fromString($fxTransaction['converted_gross_brl']);
        }"""
source=source.replace(anchor,replacement)
# The original mismatch guard is satisfied only after converted gross matches operation.amount;
# the qualifier requires that equality and an explicit reconciliation proof.
path.write_text(source,encoding='utf-8')
with (E/'engine-fx-activation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'POLICY_GATED_IMPORT_FX_ENGINE_INTEGRATION_VALIDATION_PENDING',
        'source_path':path.relative_to(ROOT).as_posix(),'archive_path':archive.relative_to(ROOT).as_posix(),
        'before_sha256':before,'applied_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),
        'old_bundles_preserve_unconverted_refusal':True,'production_ready':False},f,indent=2)
print('PASS:import FX integration gated by own policy; previous engine archived')
