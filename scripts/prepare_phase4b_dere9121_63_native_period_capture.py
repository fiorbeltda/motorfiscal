"""Prepare own63 native business and complete contextual period capture using preserved61/62 fixtures."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-63-period-integration-20261013'
P=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'
source=(P/'capture-native-version.php').read_text(encoding='utf-8')
anchor="$request=json_decode(file_get_contents(storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-negative-base-request.json')),true,512,JSON_THROW_ON_ERROR);"
assert source.count(anchor)==1
body=r'''
$periodFixture=json_decode(file_get_contents(storage_path('app/phase4b-dere9121-62-period-preparation-20261013/period-native-v2-result.json')),true,512,JSON_THROW_ON_ERROR)['cases']['two-reopenings']['input'];
$periodEvents=$periodFixture['period_events'];
$witness=$periodFixture['history'];
$witness['through']=$known->format('Y-m-d\TH:i:s.u').'0'.$known->format('P');
$witness['history_hash']=App\Modules\Fiscal\Core\Shared\CanonicalJson::hash([
    'supplier_id'=>$witness['supplier_id'],'period'=>$witness['period'],'initial_state'=>$witness['initial_state'],
    'from'=>$witness['from'],'through'=>$witness['through'],'events'=>$periodEvents]);
$binding['period_history']=['monthly_input_hash'=>App\Modules\Fiscal\Core\Shared\CanonicalJson::hash($month),
    'events'=>$periodEvents,'witness'=>$witness];
'''
source=source.replace(anchor,body+anchor)
anchor="if (($result['composition']['active_version_binding']['active_event']['sequence']??null)!=='03') throw new RuntimeException('Active version not selected');"
assert source.count(anchor)==1
body=r'''
$qualifiedPeriod=$result['composition']['active_version_binding']['period_history_qualification']??null;
if (!is_array($qualifiedPeriod) || $qualifiedPeriod['status']!=='LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED'
    || $qualifiedPeriod['closing_count']!==2 || $qualifiedPeriod['reopening_count']!==2
    || $qualifiedPeriod['period_state_externally_verified']!==false) throw new RuntimeException('Period history not qualified');
'''
source=source.replace(anchor,anchor+body)
source=source.replace('native-version-settlement.json','native-period-settlement.json')
source=source.replace('PASS:own61 version chain -> native business -> active03 -> BCN/tax proposal;',
    'PASS:own63 complete monthly-bound period history -> active03 native business -> BCN/tax proposal;')
with (E/'capture-native-period.php').open('x',encoding='utf-8') as f:
    f.write(source)
print('PASS:own63 capture driver prepared for two qualified reopening cycles and active03 economics')
