"""A160 cumulative split regression and native CLI evidence, with storage outside the checkout.

Global PHPStan and Pint are recorded separately. This script never claims a full fiscal suite.
"""
import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
from datetime import datetime, timezone
from decimal import Decimal, ROUND_DOWN
from pathlib import Path

from validate_split159 import TESTS, PHP_FILES, ROOT


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP85', 'php'))
    parser.add_argument('--output', required=True)
    parser.add_argument('--increment', choices=['A160', 'A161', 'A162', 'A163', 'A164'], default='A160')
    args = parser.parse_args()
    output = Path(args.output).resolve()
    if output.is_relative_to(ROOT):
        raise RuntimeError('Use an evidence directory outside the checkout')
    output.mkdir(parents=True, exist_ok=False)
    storage = output / 'runtime-storage'
    for rel in ['app', 'framework/views', 'framework/cache/data', 'framework/sessions', 'logs']:
        (storage / rel).mkdir(parents=True, exist_ok=True)
    php_path = shutil.which(args.php) or args.php
    php = [php_path]
    modules = subprocess.check_output(php + ['-m'], cwd=ROOT).decode().lower().splitlines()
    if os.name == 'nt':
        for name in ['mbstring', 'pdo_sqlite', 'sqlite3', 'openssl']:
            if name not in modules:
                php += ['-d', f'extension={Path(php_path).resolve().parent / "ext" / ("php_" + name + ".dll")}']
    php += ['-d', 'memory_limit=1G']
    env = dict(os.environ, LARAVEL_STORAGE_PATH=str(storage), TEMP=str(output), TMP=str(output))
    runs = []

    def run(label, command, expected=0):
        completed = subprocess.run(command, cwd=ROOT, env=env, capture_output=True)
        (output / (label + '.stdout.log')).write_bytes(completed.stdout)
        (output / (label + '.stderr.log')).write_bytes(completed.stderr)
        runs.append({'label': label, 'command': command, 'exit_code': completed.returncode, 'expected_exit_code': expected})
        print(f'{label}: exit {completed.returncode}', flush=True)
        if completed.returncode != expected:
            raise RuntimeError(f'{label} failed; see {output}')
        return completed.stdout

    tests = TESTS + ['Unit/SplitFiscalHistory160Test.php', 'Integration/SplitFiscalHistory160IntegrationTest.php']
    if args.increment in ['A161', 'A162', 'A163', 'A164']:
        tests += ['Unit/SplitBoletoPayments161Test.php', 'Unit/SplitPaymentLedger161Test.php', 'Integration/SplitBoleto161IntegrationTest.php']
    if args.increment in ['A162', 'A163', 'A164']:
        tests += ['Unit/SimplifiedSplitCalculation162Test.php', 'Unit/SimplifiedSplitPeriod162Test.php', 'Integration/SimplifiedSplit162IntegrationTest.php']
    if args.increment in ['A163', 'A164']:
        tests += ['Unit/SplitDeadline163Test.php', 'Integration/SplitDeadline163IntegrationTest.php']
    if args.increment == 'A164':
        tests += ['Unit/RefundDeadline164Test.php', 'Integration/RefundDeadline164IntegrationTest.php']
    raw = run('directed-tests', php + ['vendor/pestphp/pest/bin/pest'] + ['tests/Fiscal/' + p for p in tests] + ['--compact', '--colors=never'])
    summary = re.search(rb'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', raw)
    if not summary:
        raise RuntimeError('Missing terminal test summary')
    files = list(dict.fromkeys(PHP_FILES + ['app/Modules/Fiscal/ConsumptionReform/SplitTransaction158.php',
        'app/Modules/Fiscal/ConsumptionReform/SplitFiscalHistory160.php', 'scripts/agent_a/split160_fixture.php',
        'scripts/agent_a/split160_engine_fixture.php', 'tests/Fiscal/Unit/SplitFiscalHistory160Test.php',
        'tests/Fiscal/Integration/SplitFiscalHistory160IntegrationTest.php']))
    if args.increment in ['A161', 'A162', 'A163', 'A164']:
        files += ['app/Modules/Fiscal/ConsumptionReform/SplitBoletoPayments161.php', 'scripts/agent_a/split161_fixture.php',
                  'tests/Fiscal/Unit/SplitBoletoPayments161Test.php', 'tests/Fiscal/Unit/SplitPaymentLedger161Test.php',
                  'tests/Fiscal/Integration/SplitBoleto161IntegrationTest.php']
    if args.increment in ['A162', 'A163', 'A164']:
        files += ['app/Modules/Fiscal/ConsumptionReform/SplitLegalEvidence162.php',
                  'app/Modules/Fiscal/ConsumptionReform/SimplifiedSplitCalculation162.php',
                  'app/Modules/Fiscal/ConsumptionReform/SimplifiedSplitPeriod162.php',
                  'app/Modules/Fiscal/ConsumptionReform/SimplifiedSplitIntegration162.php',
                  'app/Console/Commands/AssessSimplifiedSplit162Command.php', 'scripts/agent_a/split162_fixture.php',
                  'scripts/agent_a/split162_engine_fixture.php', 'tests/Fiscal/Unit/SimplifiedSplitCalculation162Test.php',
                  'tests/Fiscal/Unit/SimplifiedSplitPeriod162Test.php', 'tests/Fiscal/Integration/SimplifiedSplit162IntegrationTest.php']
    if args.increment in ['A163', 'A164']:
        files += ['app/Modules/Fiscal/ConsumptionReform/SplitCalendar163.php',
                  'app/Modules/Fiscal/ConsumptionReform/SplitDeadline163.php',
                  'app/Modules/Fiscal/ConsumptionReform/SplitDeadlineIntegration163.php',
                  'app/Console/Commands/AssessSplitDeadline163Command.php', 'scripts/agent_a/split163_fixture.php',
                  'scripts/agent_a/split163_engine_fixture.php', 'tests/Fiscal/Unit/SplitDeadline163Test.php',
                  'tests/Fiscal/Integration/SplitDeadline163IntegrationTest.php']
    if args.increment == 'A164':
        files += ['app/Modules/Fiscal/ConsumptionReform/RefundDeadline164.php',
                  'app/Modules/Fiscal/ConsumptionReform/RefundIntegration164.php',
                  'app/Console/Commands/AssessRefundDeadline164Command.php', 'scripts/agent_a/refund164_fixture.php',
                  'scripts/agent_a/refund164_engine_fixture.php', 'tests/Fiscal/Unit/RefundDeadline164Test.php',
                  'tests/Fiscal/Integration/RefundDeadline164IntegrationTest.php']
    run('pint', php + ['vendor/bin/pint', '--test'] + files)
    run('console-check', php + ['scripts/check_fiscal_console.php'])
    reports = {}
    inputs = {}
    snippets = {
        'schedule': 'split159Ledger()',
        'fiscal-history': 'split160Transaction()',
        'lifecycle': "(function () { $i=split159Ledger(); $i['contract']=\\App\\Modules\\Fiscal\\ConsumptionReform\\SplitLedger158::LIFECYCLE_CONTRACT; $i['transactions'][0]=history160($i['transactions'][0]); return sealLedger158($i); })()",
    }
    fixtures = ['scripts/agent_a/split160_fixture.php']
    if args.increment in ['A161', 'A162', 'A163', 'A164']:
        snippets.update(boleto='split161Boleto()', **{'payment-ledger': 'split161Ledger()'})
        fixtures = ['scripts/agent_a/split161_fixture.php']
    if args.increment in ['A162', 'A163', 'A164']:
        snippets.update(**{'simplified-calculation': 'split162Calculation()', 'simplified-period': 'split162Period()'})
        fixtures += ['scripts/agent_a/split162_fixture.php']
    if args.increment in ['A163', 'A164']:
        snippets.update(**{'deadline-remittance': "split163Input('PSP_REMITTANCE')",
                           'deadline-report': "split163Input('SEGREGATION_REPORT')",
                           'deadline-excess': "split163Input('SIMPLIFIED_EXCESS_TRANSFER')"})
        fixtures += ['scripts/agent_a/split163_fixture.php']
    if args.increment == 'A164':
        snippets.update(**{'refund-standard': "refund164Input('STANDARD_FALLBACK')",
                           'refund-acquirer': "refund164Input('ACQUIRER_EXCESS')"})
        fixtures += ['scripts/agent_a/refund164_fixture.php']
    for mode, snippet in snippets.items():
        setup = "require 'vendor/autoload.php'; " + ''.join("require_once '" + fixture + "'; " for fixture in fixtures)
        raw = run(mode + '-fixture', php + ['-r', setup + 'echo json_encode(' + snippet + ', JSON_THROW_ON_ERROR);'])
        inputs[mode] = json.loads(raw)
        path = output / (mode + '-input.json')
        path.write_bytes(raw)
        command = php + ['artisan', 'fiscal:rtc:assess-simplified-split' if mode.startswith('simplified-') else 'fiscal:rtc:assess-split-payment',
                         str(path), '--mode=' + mode.removeprefix('simplified-')]
        if mode.startswith('deadline-'):
            command = php + ['artisan', 'fiscal:rtc:assess-split-deadline', str(path)]
        if mode.startswith('refund-'):
            command = php + ['artisan', 'fiscal:rtc:assess-refund-deadlines', str(path)]
        first = run(mode + '-first', command)
        second = run(mode + '-repeat', command)
        result = json.loads(first)
        if first != second or not result['replay_matches']:
            raise RuntimeError(mode + ': nondeterministic output or failed replay')
        reports[mode] = result['report']
        malformed = json.loads(raw)
        malformed['source_hash'] = '0' * 64
        path.write_text(json.dumps(malformed), encoding='utf-8')
        run(mode + '-tampered', command, expected=1)
        path.write_bytes(raw)
    oracle = []
    expected = {
        'schedule': {'IBS': Decimal('10.00'), 'CBS': Decimal('20.00')},
        'fiscal-history': {tax: (Decimal('33.33') / Decimal('100.00') * Decimal(value)).quantize(Decimal('.01'), rounding=ROUND_DOWN)
                          for tax, value in [('IBS', '8.00'), ('CBS', '20.00')]},
        'lifecycle': {'IBS': Decimal('10.00'), 'CBS': Decimal('20.00')},
    }
    if args.increment in ['A161', 'A162', 'A163', 'A164']:
        amounts = {tax: sum(((Decimal(paid) / Decimal('100.00') * Decimal(nominal)).quantize(Decimal('.01'), rounding=ROUND_DOWN)
                            for paid in ['33.33', '66.67']), Decimal('0.00')) for tax, nominal in [('IBS', '10.00'), ('CBS', '20.00')]}
        expected.update(boleto=amounts, **{'payment-ledger': amounts})
    if args.increment in ['A162', 'A163', 'A164']:
        basis = Decimal(inputs['simplified-calculation']['operation_basis'])
        rates = inputs['simplified-calculation']['synthetic_parameter']['percentages']
        expected['simplified-calculation'] = {tax: (basis * Decimal(rates[tax]) / Decimal('100')).quantize(Decimal('.01'), rounding=ROUND_DOWN) for tax in ['IBS', 'CBS']}
    for mode, values in expected.items():
        for tax, amount in values.items():
            actual = reports[mode]['segregation']['taxes'][tax]['segregated'] if mode in ['fiscal-history', 'boleto'] else reports[mode]['taxes'][tax]['segregated']
            oracle.append({'mode': mode, 'tax': tax, 'expected': str(amount), 'actual': actual, 'pass': Decimal(actual) == amount})
    if args.increment in ['A161', 'A162', 'A163', 'A164']:
        ledger = inputs['payment-ledger']
        for tax in ['IBS', 'CBS']:
            remaining = Decimal(ledger['debits'][tax]) - Decimal(ledger['previously_extinguished'][tax]) - sum((Decimal(row['amount']) for row in ledger['applications'] if row['tax'] == tax), Decimal('0.00'))
            actual = reports['payment-ledger']['taxes'][tax]['remaining']
            oracle.append({'mode': 'payment-ledger-remaining', 'tax': tax, 'expected': str(remaining), 'actual': actual, 'pass': Decimal(actual) == remaining})
    if args.increment in ['A162', 'A163', 'A164']:
        period = inputs['simplified-period']
        rows = {row['document_id']: row for row in period['debts']}
        for tax in ['IBS', 'CBS']:
            received = sum((Decimal(row['amounts'][tax]) for row in period['receipts']), Decimal('0.00'))
            budget = received
            for priority in [1, 2]:
                for doc in period['document_order']:
                    row = rows[doc]
                    rank = 1 if row['acquirer_regime'] == 'NON_REGULAR' and period['period_start'] <= row['operation_on'] <= period['period_end'] else 2
                    if rank == priority and (priority == 1 or period['phase'] == 'CLOSED'):
                        budget -= min(budget, Decimal(row['debits'][tax]) - Decimal(row['previously_extinguished'][tax]))
            balances = reports['simplified-period']['balances'][tax]
            conserved = sum((Decimal(balances[k]) for k in ['applied_from_supplied_records', 'pending_transfer', 'transferred_to_supplier', 'unreserved_cash']), Decimal('0.00'))
            for field, amount in [('proposed_for_debts', received - budget), ('potential_excess_after_proposal', budget), ('conservation', received)]:
                actual = str(conserved) if field == 'conservation' else balances[field]
                oracle.append({'mode': 'simplified-period-' + field, 'tax': tax, 'expected': str(amount), 'actual': actual, 'pass': Decimal(actual) == amount})
    date_oracle = []
    if args.increment in ['A163', 'A164']:
        from datetime import date, timedelta
        for mode, trigger_key, count in [('deadline-remittance', 'financial_settlement', 1),
                                          ('deadline-excess', 'apuration_concluded', 3)]:
            record = inputs[mode]
            trigger = datetime.fromisoformat(record['events'][trigger_key]['at'].replace('Z', '+00:00'))
            from datetime import timezone as tz
            current = trigger.astimezone(tz(timedelta(hours=-3))).date()
            days = []
            classifications = record['synthetic_calendar']['days']
            while len(days) < count:
                current += timedelta(days=1)
                if classifications[current.isoformat()]:
                    days.append(current.isoformat())
            actual = reports[mode]['counted_business_dates']
            date_oracle.append({'mode': mode, 'expected': days, 'actual': actual, 'pass': actual == days})
        expected_report = '2026-10-09T19:10:00.000000-03:00'
        actual = reports['deadline-report']['deadline_at']
        date_oracle.append({'mode': 'deadline-report', 'expected': expected_report, 'actual': actual, 'pass': actual == expected_report})
        if not all(row['pass'] for row in date_oracle):
            raise RuntimeError('Independent business-date oracle failed')
    if args.increment == 'A164':
        from datetime import timedelta
        for mode in ['refund-standard', 'refund-acquirer']:
            record = inputs[mode]
            book = record['context_report']['input']
            for request, assessment in zip(record['requests'], reports[mode]['assessments'], strict=True):
                amount = Decimal(request['amount'])
                transferred = sum((Decimal(row['amount']) for row in request['transfer_allocations']), Decimal('0.00'))
                for field, value in [('refund_allocated', amount), ('transferred_allocated', transferred), ('pending_transfer', amount-transferred)]:
                    actual = assessment[field]
                    oracle.append({'mode': mode+'-'+request['id'], 'field': field, 'expected': str(value), 'actual': actual, 'pass': Decimal(actual)==value})
                notice = request['notice']
                if record['procedure']=='STANDARD_FALLBACK':
                    raw_payment = next(tx['settlement']['settled_at'] for tx in book['transactions'] if tx.get('settlement',{}).get('receipt_id')==request['payment_reference'])
                else:
                    raw_payment = next(row['settled_at'] for row in book['payments'] if row['id']==request['payment_reference'])
                parse = lambda value: datetime.fromisoformat(value.replace('Z','+00:00'))
                trigger = parse(notice['information_received_at'] if notice['delayed_information'] else raw_payment)
                if record['procedure']=='STANDARD_FALLBACK':
                    trigger=max(trigger,parse(notice['correct_linked_at']))
                current=trigger.astimezone(timezone(timedelta(hours=-3))).date()
                classifications=request['synthetic_calendar']['days']
                counted=[]
                while len(counted)<3:
                    current += timedelta(days=1)
                    if classifications[current.isoformat()]:counted.append(current.isoformat())
                actual=assessment['counted_business_dates']
                date_oracle.append({'mode':mode+'-'+request['id'],'expected':counted,'actual':actual,'pass':actual==counted})
        if not all(row['pass'] for row in date_oracle):
            raise RuntimeError('Independent refund date oracle failed')
    if not all(row['pass'] for row in oracle):
        raise RuntimeError('Independent Decimal comparison failed')
    receipt = {'task': args.increment, 'created_at': datetime.now(timezone.utc).isoformat(),
               'environment': 'HOST_PHP85_NOT_CONTAINER', 'full_suite_complete': False, 'groups_closed': 0,
               'production_ready': False, 'directed_tests': {'passed': int(summary[1]), 'assertions': int(summary[2])},
               'runs': runs, 'decimal_oracle': oracle, 'business_date_oracle': date_oracle, 'native_modes_byte_deterministic_and_replay': list(snippets),
               'runtime_hashes': {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in files},
               'evidence_hashes': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in output.glob('*.log')},
               'phpstan': 'SEPARATE_GLOBAL_SERIAL_RESULT_REQUIRED'}
    receipt_path = output / ('validation-' + args.increment.lower() + '.json')
    receipt_path.write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'PASS: {receipt_path}', flush=True)


if __name__ == '__main__':
    main()
