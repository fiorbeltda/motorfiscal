"""Primary-source intake for material ISS supply dates; no policy or runtime activation."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-iss-date105-primary-research-20261019')
E.mkdir(exist_ok=False)

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

sources = [
    ('ctn-consolidated.html', 'https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm'),
    ('lc116-consolidated.html', 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm'),
    ('sp-law13701-consolidated.html', 'https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado'),
    ('sp-decree53151-consolidated.html', 'https://legislacao.prefeitura.sp.gov.br/decreto-53151-de-17-de-maio-de-2012/consolidado'),
    ('sp-pn4-2016-consolidated.html', 'https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/consolidado')]
records = []
for name, url in sources:
    target = E / name
    started = datetime.now(timezone.utc).isoformat()
    command = ['powershell', '-NoProfile', '-File', str(ROOT / 'scripts/capture_primary_windows_tls.ps1'), '-Uri', url, '-OutputPath', str(target)]
    proc = subprocess.run(command, capture_output=True)
    for channel in ['stdout', 'stderr']:
        with (E / (name + '.' + channel)).open('xb') as stream:
            stream.write(getattr(proc, channel))
    record = {'file': name, 'url': url, 'started_at': started, 'observed_at': datetime.now(timezone.utc).isoformat(),
        'exit_code': proc.returncode, 'tls_validation': 'WINDOWS_DEFAULT_ENABLED', 'published_on': None,
        'effective_from': None, 'remote_deployment_proven': False, 'historical_applicability_review_complete': False}
    if proc.returncode == 0:
        record.update(sha256=hashlib.sha256(target.read_bytes()).hexdigest(), size_bytes=target.stat().st_size,
            transport=json.loads(proc.stdout.decode('utf-8-sig')))
    records.append(record)
    save(E / (name + '-capture.json'), record)
    print(json.dumps(record), flush=True)
save(E / 'primary-source-observation.json', {'created_at': datetime.now(timezone.utc).isoformat(), 'sources': records,
    'scope': 'INITIAL_PRIMARY_INTAKE_NOT_COMPLETE_RESEARCH_OR_MATERIAL_DATE_QUALIFICATION',
    'runtime_integrated': False, 'production_ready': False})
assert all(r['exit_code'] == 0 for r in records)
