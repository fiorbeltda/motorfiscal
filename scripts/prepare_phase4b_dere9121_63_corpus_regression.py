"""Create a fresh63 replay destination without changing the accepted61 regression evidence."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'scripts/verify_phase4b_dere9121_61_old_corpus.py').read_text(encoding='utf-8')
source=source.replace('phase4b-dere9121-61-version-integration-20261013','phase4b-dere9121-63-period-integration-20261013')
source=source.replace('current61','current63').replace('AFTER_PUBLIC_VERSION_ACTIVATION','AFTER_PUBLIC_PERIOD_HISTORY_ACTIVATION')
target=ROOT/'scripts/verify_phase4b_dere9121_63_old_corpus.py'
with target.open('x',encoding='utf-8') as f:
    f.write(source)
print('PASS:fresh63 public corpus replay prepared; accepted61 evidence unchanged')
