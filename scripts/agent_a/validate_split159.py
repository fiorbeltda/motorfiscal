"""Reproduce A159 directed validation, native CLI replays and an independent Decimal oracle.

Usage: python scripts/agent_a/validate_split159.py --php C:/tools/php85/php.exe --output D:/.../new-run
All runtime storage and evidence are kept outside the checkout. No historical evidence is overwritten.
"""
import argparse
import hashlib
import json
import os
import subprocess
from datetime import datetime, timezone
from decimal import Decimal, ROUND_DOWN
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PHP_FILES = [
    'app/Modules/Fiscal/ConsumptionReform/SplitLedger158.php',
    'app/Modules/Fiscal/ConsumptionReform/SplitPlannedInstallment159.php',
    'app/Modules/Fiscal/ConsumptionReform/SplitPaymentIntegrationB34.php',
    'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Console/Commands/AssessSplitPayment158Command.php',
    'scripts/agent_a/split159_fixture.php',
    'tests/Fiscal/Unit/SplitSchedule159Test.php',
    'tests/Fiscal/Integration/SplitSchedule159IntegrationTest.php',
]
TESTS = [
    'Unit/SplitPayment158Test.php', 'Unit/SplitTransaction158Test.php', 'Unit/SplitLedger158Test.php',
    'Unit/SplitSchedule159Test.php', 'Integration/SplitPayment158IntegrationTest.php',
    'Integration/SplitPaymentIntegrationB34Test.php', 'Integration/HandoffActivationB34Test.php',
    'Integration/SplitSchedule159IntegrationTest.php', 'Unit/AcquirerCollection157Test.php',
    'Integration/AcquirerCollection157IntegrationTest.php', 'Integration/HealthPlanIntermediationB38Test.php',
    'Integration/TemporalMatrixB39Test.php',
]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP85', 'php'))
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    output = Path(args.output).resolve()
    if output.is_relative_to(ROOT):
        raise RuntimeError('Evidence directory must be outside the checkout')
    output.mkdir(parents=True, exist_ok=False)
    storage = output / 'runtime-storage'
    for rel in ['app', 'framework/views', 'framework/cache/data', 'framework/sessions', 'logs']:
        (storage / rel).mkdir(parents=True, exist_ok=True)
    env = dict(os.environ, LARAVEL_STORAGE_PATH=str(storage), TEMP=str(output), TMP=str(output))
    php = [args.php]
    if os.name == 'nt':
        extension_dir = Path(args.php).resolve().parent / 'ext'
        for name in ['mbstring', 'pdo_sqlite', 'sqlite3']:
            php += ['-d', f'extension={extension_dir / ("php_" + name + ".dll")}']
    php += ['-d', 'memory_limit=1G']
    runs = []

    def run(label, command, expected=0):
        result = subprocess.run(command, cwd=ROOT, env=env, capture_output=True)
        (output / (label + '.stdout.log')).write_bytes(result.stdout)
        (output / (label + '.stderr.log')).write_bytes(result.stderr)
        runs.append({'label': label, 'command': command, 'exit_code': result.returncode,
                     'expected_exit_code': expected, 'pass': result.returncode == expected})
        print(f'{label}: exit {result.returncode}', flush=True)
        if result.returncode != expected:
            raise RuntimeError(f'{label} failed; see {output}')
        return result.stdout

    run('directed-tests', php + ['vendor/pestphp/pest/bin/pest'] + ['tests/Fiscal/' + t for t in TESTS]
        + ['--compact', '--colors=never'])
    run('phpstan', php + ['vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress', '--error-format=raw'])
    run('pint', php + ['vendor/bin/pint', '--test'] + PHP_FILES)
    run('console-check', php + ['scripts/check_fiscal_console.php'])

    raw = run('fixture', php + ['-r', "require 'vendor/autoload.php'; require 'scripts/agent_a/split159_fixture.php'; echo json_encode(split159Ledger(), JSON_THROW_ON_ERROR);"])
    ledger = json.loads(raw)
    path = output / 'input.json'
    path.write_bytes(raw)
    command = php + ['artisan', 'fiscal:rtc:assess-split-payment', str(path), '--mode=schedule']
    first = run('cli-first', command)
    second = run('cli-repeat', command)
    if first != second:
        raise RuntimeError('CLI is not byte-deterministic')
    result = json.loads(first)
    if not result['replay_matches']:
        raise RuntimeError('CLI report did not replay')
    report = result['report']
    oracle = []
    for tax in ['IBS', 'CBS']:
        allocated = sum((Decimal(t['taxes'][tax]) for t in ledger['transactions']), Decimal('0.00'))
        segregated = Decimal('0.00')
        for tx in ledger['transactions']:
            if tx.get('settlement'):
                informed = Decimal(tx['taxes'][tax])
                ratio = Decimal(tx['settlement']['paid_amount']) / Decimal(tx['original_amount'])
                segregated += min(ratio * informed, informed).quantize(Decimal('.01'), rounding=ROUND_DOWN)
        for field, expected in [('allocated', allocated), ('segregated', segregated), ('remaining', Decimal(ledger['debits'][tax]))]:
            actual = Decimal(report['taxes'][tax][field])
            oracle.append({'tax': tax, 'field': field, 'expected': str(expected), 'actual': str(actual), 'pass': actual == expected})
    if not all(row['pass'] for row in oracle):
        raise RuntimeError('Independent Decimal oracle failed')
    ledger['operation_amount'] = '400.00'  # Keep the original envelope: tampering must fail.
    path.write_text(json.dumps(ledger), encoding='utf-8')
    negative = json.loads(run('cli-tampered', command, expected=1))
    if negative['report']['reason'] != 'A158_LEDGER_HASH_CONFLICT':
        raise RuntimeError('Unexpected tamper refusal')
    path.write_bytes(raw)
    receipt = {
        'task': 'A159', 'created_at': datetime.now(timezone.utc).isoformat(),
        'base_commit': 'f8f22d867403b17f2ccf77cfd94e3317216a7924',
        'scope': 'Split schedule with not-yet-initiated future installments',
        'environment': 'HOST_PHP85_NOT_CONTAINER', 'full_suite_complete': False,
        'production_ready': False, 'groups_closed': 0, 'runs': runs,
        'cli_byte_deterministic': True, 'cli_replay_matches': True, 'decimal_oracle': oracle,
        'runtime_hashes': {name: hashlib.sha256((ROOT / name).read_bytes()).hexdigest() for name in PHP_FILES},
        'limitations': ['No Docker daemon/container validation', 'No fiscal revision/reset lifecycle yet',
                        'No multiple payments per boleto yet', 'No new official deployment or simplified percentage'],
    }
    (output / 'validation-a159.json').write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'PASS: {output / "validation-a159.json"}', flush=True)


if __name__ == '__main__':
    main()
