"""Archive55 public runtime and gate native no-movement composition on successor policy."""
from pathlib import Path
import hashlib
import json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
E.mkdir(exist_ok=True)
APP=ROOT/'app/Modules/Fiscal/ConsumptionReform'
accepted=json.loads((ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json').read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

for item in accepted['implementation']:
    path=ROOT/item['path']
    if path==APP/'DerePublicOfferDebtReturnXmlReader.php':
        path=ROOT/'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/before-no-movement-reader.php'
    assert sha(path)==item['sha256']
records=[]
for name in ['DereFinancialPublicOfferCurrentBaseCompositionQualifier.php','DereFinancialPublicOfferNegativeBaseSettlementPlanner.php']:
    path=APP/name
    archive=E/('before-no-movement-'+name)
    with archive.open('xb') as stream:
        stream.write(path.read_bytes())
    source=path.read_text(encoding='utf-8')
    if 'Composition' in name:
        anchor='array $returnBinding, string $sources): array'
        assert source.count(anchor)==1
        source=source.replace(anchor,'array $returnBinding, string $sources, bool $allowNoMovement = false): array')
        anchor="        $debtProjection = $projection['debt_yield_projection'] ?? null;"
        assert source.count(anchor)==1
        source=source.replace(anchor,anchor+"""
        if ($debtProjection === null && $allowNoMovement) {
            $empty = $projection['debt_no_movement_projection'] ?? null;
            if (! is_array($empty) || ($empty['contract'] ?? null) !== \\App\\Modules\\Fiscal\\NFSe\\DereDebtNoMovementXmlProjector::CONTRACT) {
                return $this->stop('NATIVE_NO_MOVEMENT_PROJECTION_REQUIRED');
            }
            // Native book/projection checks prove empty unmoved public accounts; explicit D9121 must still reconcile zero.
            $debtProjection = [...$empty, 'total_vApur' => '0.00'];
        }""")
    else:
        anchor="$rules, $sources.'/dere-observed-20261005-annex-i.pdf', $returnBinding, $sources);"
        assert source.count(anchor)==1
        source=source.replace(anchor,"$rules, $sources.'/dere-observed-20261005-annex-i.pdf', $returnBinding, $sources,\n            ($policy['allow_native_no_movement'] ?? null) === true);")
    path.write_text(source,encoding='utf-8')
    records.append({'source_path':path.relative_to(ROOT).as_posix(),'archive_path':archive.relative_to(ROOT).as_posix(),
        'accepted55_sha256':sha(archive),'applied_sha256':sha(path)})
with (E/'source-activation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'POLICY_GATED_NO_MOVEMENT_COMPOSITION_APPLIED_VALIDATION_PENDING','sources':records,
        'old54_policy_default_allows_no_movement':False,'production_ready':False},stream,indent=2)
print('PASS: native no-movement composition gated by successor policy; accepted55 public sources archived')
