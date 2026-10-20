"""Prepare policy-selected material-origin guard separately from the69 suite generation."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
path=ROOT/'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
source=path.read_text(encoding='utf-8')
with (E/'before-origin-transaction.php').open('xb') as f:f.write(path.read_bytes())
source=source.replace('final readonly class ImportFxTransactionQualifier','final readonly class ImportFxQuoteOriginPrototype70')
anchor="        $converted = (new ImportFxQuoteConverter)->convert("
assert source.count(anchor)==1
guard="""        $originQualification = null;
        if (($policy['require_material_quote_origin'] ?? null) === true) {
            $selectedSource = $trusted[$facts['rtc.import_fx_source_id']] ?? null;
            $origins = $policy['qualified_quote_origins'] ?? null;
            $origin = is_array($origins) ? ($origins[$facts['rtc.import_fx_source_id']] ?? null) : null;
            if (! is_array($selectedSource) || ! is_array($origin) || ($origin['status'] ?? null) !== 'QUALIFIED_LOCAL_SOURCE_SELECTION'
                || ($origin['currency'] ?? null) !== $facts['rtc.gross_currency']
                || ($origin['quote_source_sha256'] ?? null) !== ($selectedSource['sha256'] ?? null)
                || ! is_string($origin['primary_evidence_sha256'] ?? null)
                || ! preg_match('/^[a-f0-9]{64}$/', $origin['primary_evidence_sha256'])
                || ! is_string($origin['known_from'] ?? null) || ! $this->timestamp($origin['known_from'])) {
                return $this->stop('MATERIAL_QUOTE_ORIGIN_UNQUALIFIED');
            }
            $originMaterial = $origin;
            unset($originMaterial['source_hash']);
            if (($origin['source_hash'] ?? null) !== CanonicalJson::hash($originMaterial)
                || new DateTimeImmutable($origin['known_from']) > $knownAt) {
                return $this->stop('MATERIAL_QUOTE_ORIGIN_HASH_OR_KNOWLEDGE_CONFLICT');
            }
            $expectedStrategy = $facts['rtc.gross_currency'] === 'USD'
                ? 'BCB_USD_BRL_PTAX' : 'FOREIGN_MONETARY_AUTHORITY_RATE_DISCLOSED_BY_BCB';
            if (($origin['strategy'] ?? null) !== $expectedStrategy
                || ($facts['rtc.gross_currency'] !== 'USD' && (! is_string($origin['foreign_authority_id'] ?? null)
                    || trim($origin['foreign_authority_id']) === '' || ! is_string($origin['foreign_authority_evidence_sha256'] ?? null)
                    || ! preg_match('/^[a-f0-9]{64}$/', $origin['foreign_authority_evidence_sha256']))) {
                return $this->stop('MATERIAL_QUOTE_ORIGIN_STRATEGY_OR_AUTHORITY_UNQUALIFIED');
            }
            $originQualification = $origin;
        }
"""
source=source.replace(anchor,guard+anchor)
anchor="'status' => 'LOCAL_IMPORT_FX_TRANSACTION_BOUND', 'conversion' => $converted,"
assert source.count(anchor)==1
source=source.replace(anchor,anchor+"\n            ...($originQualification === null ? [] : ['material_quote_origin' => $originQualification]),")
target=E/'planned-origin-transaction.php'
with target.open('x',encoding='utf-8') as f:f.write(source)
with (E/'origin-guard-preparation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'POLICY_SELECTED_MATERIAL_QUOTE_ORIGIN_GUARD_PREPARED_NO_RUNTIME_CHANGE',
        'before_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'prototype_sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
        'non_usd_qualified_source_available':False,'cross_currency_qualified_source_available':False,'production_ready':False},f,indent=2)
print('PASS:material-origin guard prepared without changing live69 generation')
