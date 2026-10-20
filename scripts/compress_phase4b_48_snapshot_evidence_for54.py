"""Preserve historical snapshot bytes while recovering workspace capacity via NTFS compression."""
from pathlib import Path
import hashlib
import json
import shutil
import subprocess
ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / 'storage/app/phase4b-private-consolidation48-draft-20261009-28').resolve()
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
paths = sorted(SOURCE.glob('*-snapshot.json'), key=lambda p: p.stat().st_size, reverse=True)[:16]
assert len(paths) == 16
records = []
before = shutil.disk_usage(SOURCE).free
for path in paths:
    assert path.resolve().parent == SOURCE
    original = hashlib.sha256(path.read_bytes()).hexdigest()
    result = subprocess.run(['compact.exe', '/C', '/F', '/I', '/Q', '/EXE:LZX', str(path)], capture_output=True)
    assert result.returncode == 0
    assert hashlib.sha256(path.read_bytes()).hexdigest() == original
    records.append({'path': path.relative_to(ROOT).as_posix(), 'sha256': original, 'logical_bytes': path.stat().st_size})
report = {'status': 'HISTORICAL_SNAPSHOT_BYTES_PRESERVED_NTFS_COMPRESSION', 'files': records,
    'free_bytes_before': before, 'free_bytes_after': shutil.disk_usage(SOURCE).free, 'files_deleted': False}
with (E / 'historical48-snapshot-compression.json').open('x', encoding='utf-8') as stream:
    json.dump(report, stream, indent=2)
print(json.dumps({'files': len(records), 'all_sha256_preserved': True, 'free_bytes': report['free_bytes_after']}))
