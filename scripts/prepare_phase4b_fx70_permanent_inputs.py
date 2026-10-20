"""Capture complete own70 test inputs using audited prototypes; no current-runtime test registration."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
for filename,target,result,anchor,replacement in [
    ('exercise-origin-successor-v2.php','capture-origin-permanent-inputs.php','origin-permanent-inputs.json',
     "['expected_reason' => $expected, 'result' => $result, 'synthetic' => true]",
     "['expected_reason' => $expected, 'result' => $result, 'synthetic' => true, 'facts' => $facts, 'policy' => $policy, 'tax_point' => $input['tax_point'], 'known_at' => $input['known']->format('Y-m-d\\TH:i:s.uP')]") ,
    ('exercise-origin-offset-v2.php','capture-offset-permanent-inputs.php','offset-permanent-inputs.json',
     "['offset'=>$offset,'valid'=>$valid,'result'=>$result]",
     "['offset'=>$offset,'valid'=>$valid,'result'=>$result,'facts'=>$facts,'policy'=>$policy,'tax_point'=>$input['tax_point'],'known_at'=>$input['known']->format('Y-m-d\\TH:i:s.uP')]")]:
    source=(E/filename).read_text(encoding='utf-8')
    assert source.count(anchor)==1
    source=source.replace(anchor,replacement)
    source=source.replace('origin-native-result.json',result).replace('offset-native-result.json',result)
    with (E/target).open('x',encoding='utf-8') as f:f.write(source)
print('PASS:complete material-origin/offset test-input capture drivers prepared without modifying live69')
