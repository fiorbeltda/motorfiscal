"""Compress only own54 evidence in place, recording byte-identical SHA256 preservation."""
from pathlib import Path
import hashlib
import json
import shutil
import subprocess
ROOT = Path(__file__).resolve().parents[1]
E = (ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013').resolve()
records = []
before_free = shutil.disk_usage(E).free
for path in sorted(E.iterdir()):
    if not path.is_file() or path.stat().st_size < 65536:
        continue
    assert path.resolve().parent == E
    original = hashlib.sha256(path.read_bytes()).hexdigest()
    result = subprocess.run(['compact.exe', '/C', '/F', '/I', '/Q', '/EXE:LZX', str(path)], capture_output=True)
    assert result.returncode == 0
    assert hashlib.sha256(path.read_bytes()).hexdigest() == original
    records.append({'path': path.relative_to(ROOT).as_posix(), 'sha256': original, 'bytes_preserved': True})
with (E / 'ntfs-evidence-compression.json').open('x', encoding='utf-8') as stream:
    json.dump({'files': records, 'free_bytes_before': before_free, 'free_bytes_after': shutil.disk_usage(E).free,
        'historical_files_deleted': False}, stream, indent=2)
print(json.dumps({'files_compressed': len(records), 'bytes_preserved': True, 'free_bytes': shutil.disk_usage(E).free}))
