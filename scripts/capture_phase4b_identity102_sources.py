"""Exclusive primary captures for local DPS identity preparation; preserve accepted101."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-identity102-primary-preparation-20261016')
E.mkdir(exist_ok=False)
def save(p, value):
    with p.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
sources = [
    ('manual-api.pdf', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/manual-contribuintes-emissor-publico-api-sistema-nacional-nfs-e-v1-2-out2025.pdf'),
    ('production-portal.html', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual'),
    ('restricted-portal.html', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita'),
    ('restricted-xsd.zip', 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/esquemas-nfse-rtc-v1-01-20260727.zip')]
records = []
for name, url in sources:
    target = E / name
    started = datetime.now(timezone.utc).isoformat()
    command = ['powershell', '-NoProfile', '-File', str(ROOT / 'scripts/capture_primary_windows_tls.ps1'), '-Uri', url, '-OutputPath', str(target)]
    proc = subprocess.run(command, capture_output=True)
    with (E / (name + '.stdout')).open('xb') as stream: stream.write(proc.stdout)
    with (E / (name + '.stderr')).open('xb') as stream: stream.write(proc.stderr)
    record = {'file': name, 'url': url, 'started_at': started, 'observed_at': datetime.now(timezone.utc).isoformat(),
        'exit_code': proc.returncode, 'tls_validation': 'WINDOWS_DEFAULT_ENABLED', 'published_on': None,
        'effective_from': None, 'remote_deployment_proven': False}
    if proc.returncode == 0:
        record.update(sha256=hashlib.sha256(target.read_bytes()).hexdigest(), size_bytes=target.stat().st_size,
            transport=json.loads(proc.stdout.decode('utf-8-sig')))
    records.append(record)
    save(E / (name + '-capture.json'), record)
    print(json.dumps(record), flush=True)
save(E / 'primary-source-observation.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'sources': records,
    'runtime_integrated': False, 'production_ready': False})
assert all(r['exit_code'] == 0 for r in records)
