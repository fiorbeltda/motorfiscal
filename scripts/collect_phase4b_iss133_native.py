"""Preserve component/CLI replays and compare monetary results with independent decimal oracles."""
import argparse
import hashlib
import json
import os
import subprocess
from collections import Counter
from datetime import datetime, timezone
from decimal import Decimal, ROUND_HALF_UP, localcontext
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, default=Path('D:/ProjetoSaasExports/phase4b-iss133-20261022-v1/native-v1'))
OUTPUT = parser.parse_args().output
OUTPUT.mkdir(exist_ok=False)
command = ['D:/Docker/DockerDesktop/resources/bin/docker.exe', 'compose', 'exec', '-T', 'app', 'php', 'scripts/capture_phase4b_iss133_native.php']
records = []
statuses = Counter()
money_checks = 0


def durable(path, raw):
    with path.open('xb') as stream:
        stream.write(raw)
        stream.flush()
        os.fsync(stream.fileno())
    return hashlib.sha256(raw).hexdigest()


with (OUTPUT / 'stderr.log').open('wb') as errors:
    process = subprocess.Popen(command, cwd=ROOT, stdout=subprocess.PIPE, stderr=errors)
    for line in process.stdout:
        item = json.loads(line)
        assert item['exact_component_cli_replay'] and item['input_unchanged']
        assert hashlib.sha256((ROOT / item['case']['seed_path']).read_bytes()).hexdigest() == item['case']['seed_sha256']
        result = item['result']
        statuses[result.get('reason', result['status'])] += 1
        if result['status'] == 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED':
            compositions = {r['role']: r for r in item['input']['input111']['records']}
            results = {r['role']: r for r in item['input']['input111']['input110']['result_records']}
            for document in result['documents']:
                role = document['role']
                material = results[role]['municipal_proof']['value']
                non_incidence = material['result_subject_scope'] == 'ABROAD' and material['br_person_link'] == 'NO_BR_LINK'
                if non_incidence:
                    assert document['iss_base_brl'] is None and document['iss_tax_brl'] is None and document['iss_rate_percent'] is None
                else:
                    c = compositions[role]
                    with localcontext() as context:
                        context.prec = 40
                        base = Decimal(c['principal']) + sum(Decimal(x['amount']) for x in c['service_additions']) - sum(Decimal(x['amount']) for x in c['unconditional_discounts'])
                        expected_tax = (base * Decimal('0.029')).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)
                    assert document['iss_base_brl'] == format(base, '.2f')
                    assert document['iss_tax_brl'] == format(expected_tax, '.2f') and document['iss_rate_percent'] == '2.90'
                money_checks += 1
            assert not result['rtc_allocation_used_as_iss_base'] and not result['production_ready']
        name = item['case']['name'] + '.json'
        digest = durable(OUTPUT / name, json.dumps(item, ensure_ascii=False, indent=2).encode())
        records.append({'file': name, 'sha256': digest, 'status': result['status']})
    exit_code = process.wait()
assert exit_code == 0 and len(records) == 52
assert statuses['LOCAL_ISS_DOCUMENT_BASES_CALCULATED'] == 42
assert statuses['ISS133_OWN_ISS_FX_CONTRACT_REQUIRED'] == 8
assert statuses['ISS133_FRESH_COMPOSITION_REQUIRED'] == 2
report = {'finished_at': datetime.now(timezone.utc).isoformat(), 'complete': True, 'exit_code': exit_code,
          'component_assessments': len(records), 'exact_component_cli_replays': len(records),
          'monetary_document_checks': money_checks, 'statuses': dict(statuses), 'records': records,
          'full_integrated_snapshots': 0, 'production_ready': False}
durable(OUTPUT / 'validation.json', json.dumps(report, indent=2).encode())
print(json.dumps({k: v for k, v in report.items() if k != 'records'}))
