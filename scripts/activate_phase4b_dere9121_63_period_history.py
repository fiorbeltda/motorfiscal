"""Archive accepted61 sources before policy-gated, monthly-bound period-history integration."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'
APP=ROOT/'app/Modules/Fiscal/ConsumptionReform'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

accepted=read(ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013/version-development-validation.json')
assert accepted['full_suite_after61_complete'] is True
for item in accepted['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
assert (APP/'DerePublicOfferPeriodStateQualifier.php').exists()
assert not (E/'period-history-activation.json').exists()
changes=[]
for name in ['DerePublicOfferActiveEventBusinessBinder.php','DereFinancialPublicOfferCurrentBaseCompositionQualifier.php',
    'DereFinancialPublicOfferNegativeBaseSettlementPlanner.php']:
    path=APP/name
    source=path.read_text(encoding='utf-8')
    archive=E/('before-period-history-'+name)
    with archive.open('xb') as f:
        f.write(path.read_bytes())
    before=sha(path)
    if name=='DerePublicOfferActiveEventBusinessBinder.php':
        source=source.replace('bool $allowNoMovement = false): array','bool $allowNoMovement = false, ?array $periodHistory = null): array')
        source=source.replace('     * @return array<string,mixed>\n     */\n    public function bind','     * @param array<string,mixed>|null $periodHistory\n     * @return array<string,mixed>\n     */\n    public function bind',1)
        anchor='        $chain = (new DerePublicOfferEventVersionReconciler)->select('
        assert source.count(anchor)==1
        branch='''        $periodQualification = null;
        if ($periodHistory !== null) {
            $periodEvents = $periodHistory['events'] ?? null;
            $witness = $periodHistory['witness'] ?? null;
            if (($periodHistory['monthly_input_hash'] ?? null) !== CanonicalJson::hash($month)
                || ! is_array($periodEvents) || ! array_is_list($periodEvents) || ! is_array($witness)) {
                return $this->stop('MONTHLY_BOUND_PERIOD_HISTORY_REQUIRED');
            }
            $qualifiedEvents = [];
            foreach ($periodEvents as $periodEvent) {
                if (! is_array($periodEvent)) {
                    return $this->stop('PERIOD_EVENT_OBJECT_REQUIRED');
                }
                $qualifiedEvents[] = $periodEvent;
            }
            $periodQualification = (new DerePublicOfferPeriodStateQualifier)->qualify($qualifiedEvents, $events,
                $supplier, substr($end, 0, 7), $knownAt, $sources, $witness);
            if (($periodQualification['status'] ?? null) !== 'LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED') {
                return $periodQualification;
            }
        }
'''
        source=source.replace(anchor,branch+anchor)
        anchor="'status' => 'LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND', 'active_event' => $active, 'version_chain' => $chain,"
        assert source.count(anchor)==1
        source=source.replace(anchor,anchor+"\n            ...($periodQualification === null ? [] : ['period_history_qualification' => $periodQualification]),")
    elif name=='DereFinancialPublicOfferCurrentBaseCompositionQualifier.php':
        source=source.replace('bool $allowEventVersions = false): array','bool $allowEventVersions = false, bool $requirePeriodHistory = false): array')
        anchor='        $versionBinding = null;'
        assert source.count(anchor)==1
        source=source.replace(anchor,"        if ($requirePeriodHistory && ! array_key_exists('events', $returnBinding)) {\n            return $this->stop('PUBLIC_EVENT_CHAIN_REQUIRED_BY_PERIOD_HISTORY_POLICY');\n        }\n"+anchor)
        anchor='            $versionBinding = (new DerePublicOfferActiveEventBusinessBinder)->bind('
        assert source.count(anchor)==1
        branch='''            $periodHistory = null;
            if (array_key_exists('period_history', $returnBinding) && ! $requirePeriodHistory) {
                return $this->stop('PUBLIC_PERIOD_HISTORY_POLICY_REQUIRED');
            }
            if ($requirePeriodHistory) {
                $periodHistory = $returnBinding['period_history'] ?? null;
                if (! is_array($periodHistory)) {
                    return $this->stop('PUBLIC_PERIOD_HISTORY_REQUIRED');
                }
            }
'''
        source=source.replace(anchor,branch+anchor)
        assert source.count('$sources, $allowNoMovement);')==1
        source=source.replace('$sources, $allowNoMovement);','$sources, $allowNoMovement, $periodHistory);')
    else:
        anchor="        $versionRequest = is_array($returnBinding) && array_key_exists('events', $returnBinding);"
        assert source.count(anchor)==1
        branch='''
        $requirePeriodHistory = ($policy['require_public_period_history'] ?? null) === true;
        if ($requirePeriodHistory && ! $versionRequest) {
            return $this->stop('PUBLIC_EVENT_CHAIN_REQUIRED_BY_PERIOD_HISTORY_POLICY');
        }
        if (is_array($returnBinding) && array_key_exists('period_history', $returnBinding) && ! $requirePeriodHistory) {
            return $this->stop('PUBLIC_PERIOD_HISTORY_POLICY_REQUIRED');
        }'''
        source=source.replace(anchor,anchor+branch)
        anchor="($policy['allow_public_event_versions'] ?? null) === true);"
        assert source.count(anchor)==1
        source=source.replace(anchor,"($policy['allow_public_event_versions'] ?? null) === true, $requirePeriodHistory);")
    assert source!=path.read_text(encoding='utf-8')
    path.write_text(source,encoding='utf-8')
    changes.append({'source_path':path.relative_to(ROOT).as_posix(),'archive_path':archive.relative_to(ROOT).as_posix(),
        'before_sha256':before,'applied_sha256':sha(path)})
with (E/'period-history-activation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'MONTHLY_BOUND_PUBLIC_PERIOD_HISTORY_INTEGRATED_VALIDATION_PENDING','sources':changes,
        'predecessor_policies_preserved':True,'government_return_authenticated':False,'production_ready':False},f,indent=2)
print('PASS:monthly-bound contextual history integrated behind explicit policy; validation pending')
