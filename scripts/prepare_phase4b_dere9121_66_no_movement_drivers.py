"""Reuse native reconstruction and audited lifecycle generation for each no-movement profile."""
from pathlib import Path
import ast
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-66-no-movement-versions-20261013'
P=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
prefix=(P/'capture-no-movement-settlement.php').read_text(encoding='utf-8').split('$originXml = ',1)[0]
tree=ast.parse((ROOT/'scripts/prepare_phase4b_dere9121_65_corpus_drivers.py').read_text(encoding='utf-8'))
body=next(node.value.value for node in tree.body if isinstance(node,ast.Assign)
    and any(isinstance(target,ast.Name) and target.id=='body' for target in node.targets))
assert isinstance(body,str)
body=body.replace("$projection['debt_yield_projection']['xml']","$projection['debt_no_movement_projection']['xml']")
body=body.replace("$nativeTotal=$projection['debt_yield_projection']['total_vApur'];","$nativeTotal='0.00';")
body=body.replace('own65 active','own66 active')
for name in ['qualified-direct-only','qualified-fund-only','qualified-both-codes']:
    source=prefix
    anchor="$name=$argv[2]??'';"
    assert source.count(anchor)==1
    source=source.replace(anchor,"$name='"+name+"';")
    source=source.replace('.D9121.54.EXPLICIT.ISS.SUCCESSOR','.D9121.66.EXPLICIT.NO.MOVEMENT.VERSIONS')
    source+=body.replace('CASE',name)
    target=E/(name+'-capture-v2.php')
    if target.exists():
        assert target.read_text(encoding='utf-8')==source
    else:
        with target.open('x',encoding='utf-8') as f:
            f.write(source)
print('PASS:three own66 no-movement reconstruction and active01/03 drivers prepared')
