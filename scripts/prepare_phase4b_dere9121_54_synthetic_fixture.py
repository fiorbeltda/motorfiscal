"""Build explicit synthetic D9121 from an immutable candidate27 D2101 aggregate."""
from pathlib import Path
from decimal import Decimal
import hashlib
import base64
import json
import subprocess
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
P = ROOT / 'storage/app/phase4b-variable-yield-20261006-27'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

for report_path in sorted(P.glob('*-monthly-report-final.json')):
    report = json.loads(report_path.read_text(encoding='utf-8'))
    if 'D2101' in report.get('event_outputs', {}):
        break
else:
    raise AssertionError('No preserved candidate27 D2101 report')
source = ROOT / report['event_outputs']['D2101']
data = source.read_bytes()
assert sha(source) == report['event_validations']['D2101']['xml_sha256']
original = ET.fromstring(data)
ns = {'d': original.tag.split('}')[0][1:]}
period = original.find('.//d:perApur', ns).text
registration = original.find('.//d:nrInsc', ns).text[:8]
titles = original.findall('.//d:detTitulo', ns)
total = sum((Decimal(t.find('d:vApur', ns).text) for t in titles), Decimal('0.00'))
assert format(total, '.2f') == report['projection']['debt_yield_projection']['total_vApur']
namespace = 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2'
ET.register_namespace('', namespace)
root = ET.Element('{'+namespace+'}DeRE')
event = ET.SubElement(root, '{'+namespace+'}evtRetornoTitPub')

def group(parent, tag, values):
    node = ET.SubElement(parent, '{'+namespace+'}'+tag)
    for name, value in values.items():
        ET.SubElement(node, '{'+namespace+'}'+name).text = value
    return node

group(event, 'ideContrib', {'nrInsc': registration})
group(event, 'ideStatus', {'cdRetorno': '1', 'descRetorno': 'Sucesso'})
event_hash = base64.b64encode(hashlib.sha256(data).digest()).decode('ascii')
group(event, 'infoRecEv', {'nrRecibo': '2101-'+period.replace('-', '')+'-'+'S'*19, 'seqEvento': '00',
    'dhRecepcao': '2026-10-13T10:00:00.0000000+00:00', 'dhProcess': '2026-10-13T10:00:01.0000000+00:00',
    'tpEv': 'D-2101', 'hash': event_hash})
info = ET.SubElement(event, '{'+namespace+'}infoEvento')
group(info, 'idePeriodo', {'perApur': period})
pgcc_receipt = '1011-'+period.replace('-', '')+'-'+'P'*19
group(info, 'infoAdic', {'nrReciboPGCC': pgcc_receipt})
group(info, 'infoTotTitOfPub', {'vApurTot': format(total, '.2f')})
fixture = E / 'synthetic-d9121.xml'
with fixture.open('xb') as stream:
    stream.write(ET.tostring(root, encoding='utf-8', xml_declaration=True))
context = {'synthetic': True, 'evidence_scope': 'SYNTHETIC_D9121_AGGREGATE_FROM_PRESERVED27_NOT_GOVERNMENT_RETURN',
    'source_report_path': report_path.relative_to(ROOT).as_posix(), 'source_report_sha256': sha(report_path),
    'source_d2101_path': source.relative_to(ROOT).as_posix(), 'source_d2101_sha256': sha(source),
    'synthetic_hash_encoding': 'BASE64_SHA256_RAW_CANDIDATE_BYTES_NOT_AUTHENTICATED_GOVERNMENT_DIGEST',
    'event_hash': event_hash, 'period': period, 'registration_root': registration,
    'synthetic_pgcc_receipt': pgcc_receipt, 'title_count': len(titles), 'total_vApur': format(total, '.2f'),
    'fixture_sha256': sha(fixture), 'production_ready': False}
with (E / 'synthetic-d9121-context.json').open('x', encoding='utf-8') as stream:
    json.dump(context, stream, indent=2)
driver = E / 'validate-synthetic-d9121.php'
php = '''<?php
declare(strict_types=1);
require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$result = (new App\\Modules\\Fiscal\\Infrastructure\\Schema\\DereXmlSchemaValidator)->validate(
    file_get_contents(__DIR__.'/synthetic-d9121.xml'),
    resource_path('fiscal/sources/official/dere-observed-20261006-cgibs-xsd-v1-3-0.zip'),
    '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd',
    'evtRetornoTitPub-v0_0_2.xsd', resource_path('fiscal/sources/official/w3c-xmldsig-core-schema-observed-20261002.xsd'),
    'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
echo json_encode($result, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
exit($result['reason'] === 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' ? 0 : 1);
'''
with driver.open('x', encoding='utf-8') as stream:
    stream.write(php)
command = ['docker', 'compose', 'exec', '-T', 'app', 'php', driver.relative_to(ROOT).as_posix()]
result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
with (E / 'synthetic-schema-check.log').open('x', encoding='utf-8') as stream:
    stream.write(result.stdout + result.stderr)
with (E / 'synthetic-schema-check.json').open('x', encoding='utf-8') as stream:
    json.dump({'command': command, 'exit_code': result.returncode,
        'log_sha256': sha(E / 'synthetic-schema-check.log'), 'source_d2101_preserved': sha(source) == context['source_d2101_sha256'],
        'production_ready': False}, stream, indent=2)
print(result.stdout + result.stderr)
raise SystemExit(result.returncode)
