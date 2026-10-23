"""Preserve STF primary movement source; do not infer finality/modulation from news."""
import argparse
import hashlib
import json
import os
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
args.output.mkdir(parents=True, exist_ok=False)
url = 'https://portal.stf.jus.br/jurisprudenciaRepercussao/verAndamentoProcesso.asp?incidente=2637509&numeroTema=118'
with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=45) as response:
    raw = response.read()
    record = {'url': url, 'final_url': response.url, 'http_status': response.status,
              'observed_at': datetime.now(timezone.utc).isoformat(), 'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw)}
with (args.output / 'stf-tema118.html').open('xb') as handle:
    handle.write(raw)
    handle.flush()
    os.fsync(handle.fileno())
assert b'21/10/2026' in raw and b'23/10/2026' in raw and b'592616' in raw
record.update(merit_decided_on='2026-10-21', minutes_published_on='2026-10-23',
              final_judgment_verified=False, modulation_verified=False)
(args.output / 'capture.json').write_text(json.dumps(record, indent=2) + '\n', encoding='utf-8')
print(json.dumps(record))
