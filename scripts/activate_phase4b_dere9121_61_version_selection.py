"""Archive accepted sources and integrate policy-gated native-business-bound event versions."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
E.mkdir(exist_ok=True)
APP=ROOT/'app/Modules/Fiscal/ConsumptionReform'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

for report in [ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013/no-movement-development-validation.json',
    ROOT/'storage/app/phase4b-dere9121-60-version-binding-20261013/business-binding-development-validation.json']:
    for item in read(report)['implementation']:
        assert sha(ROOT/item['path'])==item['sha256']
command=ROOT/'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'
old55=read(ROOT/'storage/app/phase4b-dere9121-55-command-integration-20261013/monthly-development-validation.json')
assert sha(command)==next(i['sha256'] for i in old55['implementation'] if ROOT/i['path']==command)
changes=[]
for path in [APP/'DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',APP/'DereFinancialPublicOfferNegativeBaseSettlementPlanner.php',command]:
    archive=E/('before-version-selection-'+path.name)
    with archive.open('xb') as stream:
        stream.write(path.read_bytes())
    source=path.read_text(encoding='utf-8')
    if 'CompositionQualifier' in path.name:
        source=source.replace('bool $allowNoMovement = false): array','bool $allowNoMovement = false, bool $allowEventVersions = false): array')
        start=source.index('        if (! is_array($debtProjection) || ! is_string(')
        end=source.index('        // Exact observed Table11 rows',start)
        old=source[start:end]
        branch="""        $versionBinding = null;
        if (array_key_exists('events', $returnBinding)) {
            if (! $allowEventVersions || ! is_array($returnBinding['events']) || array_key_exists('xml', $returnBinding)) {
                return $this->stop('PUBLIC_EVENT_VERSION_POLICY_OR_SCOPE_REQUIRED');
            }
            $versionBinding = (new DerePublicOfferActiveEventBusinessBinder)->bind($operation, $month, $knownAt,
                $rules, $returnBinding['events'], $returnBinding, $sources, $allowNoMovement);
            if (($versionBinding['status'] ?? null) !== 'LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND') {
                return $versionBinding;
            }
            $debtReturn = $versionBinding['active_event']['reconciliation'];
            if (isset($returnBinding['expected_sequence'])
                && $returnBinding['expected_sequence'] !== $versionBinding['active_event']['sequence']) {
                return $this->stop('ACTIVE_PUBLIC_SEQUENCE_EXPECTATION_CONFLICT');
            }
        } else {
"""+old+"        }\n"
        source=source[:start]+branch+source[end:]
        anchor="'contract' => 'DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-LOCAL-1.3.0-54', 'd9121_reconciliation' => $debtReturn,"
        assert source.count(anchor)==1
        source=source.replace(anchor,anchor+"\n            ...($versionBinding === null ? [] : ['active_version_binding' => $versionBinding]),")
    elif 'SettlementPlanner' in path.name:
        anchor="        $returnBinding = $request['current_public_offer_return'] ?? null;"
        assert source.count(anchor)==1
        source=source.replace(anchor,anchor+"\n        $versionRequest = is_array($returnBinding) && array_key_exists('events', $returnBinding);\n        if ($versionRequest && ($policy['allow_public_event_versions'] ?? null) !== true) {\n            return $this->stop('PUBLIC_EVENT_VERSION_POLICY_REQUIRED');\n        }")
        source=source.replace("|| ! is_array($returnBinding) || ! is_string($returnBinding['xml'] ?? null)","|| ! is_array($returnBinding) || (! $versionRequest && ! is_string($returnBinding['xml'] ?? null))")
        anchor="        if (! $this->returnKnownBy($returnBinding['xml'], $knownAt)) {\n            return $this->stop('PUBLIC_RETURN_PROCESSING_NOT_KNOWN_OR_CHRONOLOGY_INVALID');\n        }"
        assert source.count(anchor)==1
        source=source.replace(anchor,"        if (! $versionRequest) {\n            $returnXml = $returnBinding['xml'] ?? null;\n            if (! is_string($returnXml) || ! $this->returnKnownBy($returnXml, $knownAt)) {\n                return $this->stop('PUBLIC_RETURN_PROCESSING_NOT_KNOWN_OR_CHRONOLOGY_INVALID');\n            }\n        }")
        source=source.replace("($policy['allow_native_no_movement'] ?? null) === true);","($policy['allow_native_no_movement'] ?? null) === true, ($policy['allow_public_event_versions'] ?? null) === true);")
    else:
        anchor="            $validations = [];"
        assert source.count(anchor)==1
        overlay="""            if (isset($settlement['composition']['active_version_binding'])) {
                $binding = $settlement['composition']['active_version_binding'];
                $active = $binding['active_event'] ?? null;
                if (! is_array($active) || ! is_string($active['origin_xml'] ?? null) || ! isset($components['D2101'])) {
                    throw new RuntimeException('Active public document output is not bound to a qualified monthly component.');
                }
                $components['D2101']['xml'] = $active['origin_xml'];
                $projection['active_public_event_output'] = ['sequence' => $active['sequence'], 'receipt' => $active['receipt'],
                    'origin_xml_sha256' => hash('sha256', $active['origin_xml']),
                    'native_origin_xml_sha256' => $binding['native_origin_xml_sha256'],
                    'business_payload_hash' => $binding['business_payload_hash'], 'government_return_authenticated' => false];
            }
"""
        source=source.replace(anchor,overlay+anchor)
    path.write_text(source,encoding='utf-8')
    changes.append({'source_path':path.relative_to(ROOT).as_posix(),'archive_path':archive.relative_to(ROOT).as_posix(),
        'before_sha256':sha(archive),'applied_sha256':sha(path)})
with (E/'source-selection-activation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'POLICY_GATED_ACTIVE_VERSION_SELECTION_APPLIED_VALIDATION_PENDING','sources':changes,
        'native_baseline_preserved_for_D1199_request_binding':True,'active_D2101_bytes_selected_for_output':True,'production_ready':False},stream,indent=2)
print('PASS:policy-gated active versions, native economic binding and active D2101 output applied; accepted sources archived')
