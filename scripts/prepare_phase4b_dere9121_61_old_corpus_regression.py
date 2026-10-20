"""Prepare a fresh current-runtime regression for immutable56 source reports without overwriting58 result."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'scripts/verify_phase4b_dere9121_58_public_corpus_regression.py').read_text(encoding='utf-8')
old="E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'"
assert source.count(old)==1
source=source.replace(old,"E=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'")
source=source.replace('current58 runtime','current61 runtime').replace('AFTER_NO_MOVEMENT_ACTIVATION','AFTER_PUBLIC_VERSION_ACTIVATION')
target=ROOT/'scripts/verify_phase4b_dere9121_61_old_corpus.py'
with target.open('x',encoding='utf-8') as stream:
    stream.write(source)
print('PASS:fresh61 runtime regression prepared for old54 corpus; old58 proof untouched')
