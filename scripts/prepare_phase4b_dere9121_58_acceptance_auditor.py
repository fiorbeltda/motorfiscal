"""Prepare final audit using current progress checks plus mandatory terminal full-suite evidence."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'scripts/audit_phase4b_dere9121_58_integration_progress.py').read_text(encoding='utf-8')
anchor="with (E/'no-movement-integration-progress.json').open('x',encoding='utf-8') as stream:"
assert source.count(anchor)==1
extra="""full=read(E/'activation58-full-suite.json')
assert full['exit_code']==0 and full['log_sha256']==sha(E/'activation58-full-suite.log')
text=re.sub(r'\\x1b\\[[0-9;]*m','',(E/'activation58-full-suite.log').read_text(encoding='utf-8'))
counts=re.search(r'(\\d+) passed \\((\\d+) assertions\\)',text)
assert counts and int(counts[1])==2087 and int(counts[2])==45063
for item in read(E/'no-movement-integration-progress.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256']
quality.append(ref(E/'activation58-full-suite.json'))
"""
source=source.replace(anchor,extra+"with (E/'no-movement-development-validation.json').open('x',encoding='utf-8') as stream:")
source=source.replace('POLICY_GATED_NATIVE_NO_MOVEMENT_MONTHLY_INTEGRATION_VALIDATED_FULL_SUITE_PENDING',
    'POLICY_GATED_NATIVE_NO_MOVEMENT_MONTHLY_INTEGRATION_FULL_QUALITY_VALIDATED')
source=source.replace("'full_suite_after58_complete':False,'scope57_complete':False", "'full_tests':int(counts[1]),'full_assertions':int(counts[2]),'full_suite_after58_complete':True,'scope57_complete':True")
source=source.replace('archives; full suite pending', 'archives and full suite; wider54 pending')
target=ROOT/'scripts/audit_phase4b_dere9121_58_acceptance.py'
with target.open('x',encoding='utf-8') as stream:
    stream.write(source)
print('PASS:final58 acceptance auditor prepared; terminal full-suite proof required before acceptance')
