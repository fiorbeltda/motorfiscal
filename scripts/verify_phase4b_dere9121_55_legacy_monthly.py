"""Reuse immutable legacy comparison logic with new report destination and explicit53 archive check."""
from pathlib import Path
import hashlib
import json
import sys
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'
accepted = json.loads((ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009/command-development-validation.json').read_text(encoding='utf-8'))
item = next(i for i in accepted['implementation'] if i['path'] == 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php')
assert hashlib.sha256((E / 'before-public-selection-monthly-command.php').read_bytes()).hexdigest() == item['sha256']
original = ROOT / 'scripts/verify_phase4b_negative_base53_legacy_monthly_compatibility.py'
source = original.read_text(encoding='utf-8')
anchor = "path = E / ('compatibility-' + mode + '-source-change.json')"
assert source.count(anchor) == 1
source = source.replace(anchor, "path = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013/legacy-monthly-compatibility.json'")
sys.argv = [str(original), 'after']
exec(compile(source, str(original), 'exec'), {'__file__': str(original), '__name__': '__main__'})
