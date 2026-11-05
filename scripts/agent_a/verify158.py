"""A158 immutable command receipts; previous tasks' evidence remains unchanged."""
import sys
import json
import hashlib
import subprocess
from decimal import Decimal, localcontext, ROUND_DOWN
from datetime import datetime
from pathlib import Path

import handoffs156
from evidence_io import preserve_bytes, preserve_json

handoffs156.OUT = Path('D:/ProjetoSaasExports/agent-a-A158-split-payment-20261105-v1')

def native(version='v1'):
    root, out = handoffs156.ROOT, handoffs156.OUT
    php = [handoffs156.DOCKER, 'exec', handoffs156.CONTAINER, 'php']
    raw = subprocess.check_output(php + ['-r', "require 'vendor/autoload.php'; require 'scripts/agent_a/split158_fixture.php'; echo json_encode(split158Calculation(), JSON_THROW_ON_ERROR);"], cwd=root)
    run_dir = 'arithmetic-native-' + version
    preserve_bytes(out / run_dir / 'seed.json', raw)
    records = []
    for arrangement in ['BOLETO', 'PIX_DYNAMIC', 'PIX_AUTOMATIC', 'PIX_STATIC', 'TED', 'TEF']:
        receiver = arrangement in ['BOLETO', 'PIX_DYNAMIC', 'PIX_AUTOMATIC']
        for original, paid in [('100.00', '33.33'), ('3.00', '1.00'), ('100.00', '120.00'), ('100.00', '0.00'), ('999999999999999999.99', '999999999999999999.98')]:
            for returned in ([False, True] if receiver else [False]):
                data = json.loads(raw)
                data.update(arrangement=arrangement, originator='RECEIVER' if receiver else 'PAYER', original_amount=original, paid_amount=paid)
                if original == '3.00':
                    data['taxes'] = {'IBS': {'informed': '0.03'}, 'CBS': {'informed': '0.06'}}
                if returned:
                    data['taxes']['IBS'].update(corrected='0.03' if original == '3.00' else '12.00', outstanding='0.02' if original == '3.00' else '2.00')
                    data['taxes']['CBS'].update(corrected='0.00', outstanding='0.00')
                del data['input_hash']
                data['input_hash'] = hashlib.sha256(json.dumps(data, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
                name = f'{len(records):03d}-{arrangement}'
                relative = 'storage/app/agent-a-A158-' + run_dir + '/' + name + '.json'
                preserve_json(root / relative, data)
                command = php + ['artisan', 'fiscal:rtc:assess-split-payment', relative]
                first = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                repeat = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                preserve_bytes(out / run_dir / name / 'first.json', first.stdout)
                preserve_bytes(out / run_dir / name / 'repeat.json', repeat.stdout)
                preserve_bytes(out / run_dir / name / 'stderr.log', first.stderr + repeat.stderr)
                assert first.returncode == repeat.returncode == 0 and first.stdout == repeat.stdout, name
                result = json.loads(first.stdout)
                report = result['report']
                assert result['replay_matches'] is True
                for tax in ['IBS', 'CBS']:
                    values = data['taxes'][tax]
                    with localcontext() as context:
                        context.prec = 100
                        reference = Decimal(values.get('corrected', values['informed']))
                        expected = min(Decimal(paid) * reference / Decimal(original), reference) if receiver else Decimal(values['informed'])
                        if 'outstanding' in values:
                            expected = min(expected, Decimal(values['outstanding']))
                        expected = expected.quantize(Decimal('0.01'), rounding=ROUND_DOWN)
                    assert report['taxes'][tax]['segregated'] == str(expected), (name, tax, expected)
                for flag in ['official', 'posted', 'payment_performed', 'settlement_proven', 'extinction_proven', 'deployment_proven']:
                    assert report[flag] is False
                records.append({'case': name, 'input_hash': data['input_hash'], 'output_sha256': hashlib.sha256(first.stdout).hexdigest()})
    preserve_json(out / run_dir / 'result.json', {'cases': len(records), 'decimal_checks': len(records) * 2,
        'byte_identical_repeats': len(records), 'records': records,
        'source_hashes': {str(p.relative_to(root)).replace('\\', '/'): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in [root / 'app/Modules/Fiscal/ConsumptionReform/SplitPayment158.php',
                      root / 'app/Console/Commands/AssessSplitPayment158Command.php', root / 'scripts/agent_a/verify158.py']}})


def transactions(version):
    root, out = handoffs156.ROOT, handoffs156.OUT
    php = [handoffs156.DOCKER, 'exec', handoffs156.CONTAINER, 'php']
    raw = subprocess.check_output(php + ['-r', "require 'vendor/autoload.php'; require 'scripts/agent_a/split158_fixture.php'; echo json_encode(split158Transaction(), JSON_THROW_ON_ERROR);"], cwd=root)
    run_dir = 'transaction-native-' + version
    preserve_bytes(out / run_dir / 'seed.json', raw)
    cases = {}
    milestones = {'BOLETO': 'BOLETO_PAYMENT_DISCHARGE_COMPLETE', 'PIX_DYNAMIC': 'PIX_SPI_OR_INTRABANK_SETTLED',
        'PIX_AUTOMATIC': 'PIX_SPI_OR_INTRABANK_SETTLED', 'PIX_STATIC': 'PIX_SPI_OR_INTRABANK_SETTLED',
        'TED': 'TED_STR_OR_SITRAF_SETTLED', 'TEF': 'TEF_PAYER_DEBITED'}
    for arrangement, milestone in milestones.items():
        for paid in ['33.33', '100.00', '120.00', None]:
            data = json.loads(raw)
            data['arrangement'] = arrangement
            data['settlement']['milestone'] = milestone
            if arrangement in ['PIX_STATIC', 'TED', 'TEF']:
                data['originator'] = 'PAYER'
                data['platform_returns'] = []
            if paid is None:
                data['settlement'] = None
            else:
                data['settlement']['paid_amount'] = paid
            cases[f'{arrangement}-{paid}'] = data
    for processed in [None, '2026-10-02T09:02:00-03:00', '2026-10-02T12:02:00.000001Z']:
        data = json.loads(raw)
        data['platform_returns'][2]['processed_at'] = processed
        cases[f'processed-{len(cases)}'] = data
    data = json.loads(raw)
    data['receivable_advance'] = None
    cases['without-advance'] = data
    checks = 0
    records = []
    for name, data in cases.items():
        data.pop('input_hash', None)
        data['input_hash'] = hashlib.sha256(json.dumps(data, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
        relative = 'storage/app/agent-a-A158-' + run_dir + '/' + name + '.json'
        preserve_json(root / relative, data)
        command = php + ['artisan', 'fiscal:rtc:assess-split-payment', relative, '--mode=transaction']
        first = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        repeat = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        preserve_bytes(out / run_dir / name / 'first.json', first.stdout)
        preserve_bytes(out / run_dir / name / 'repeat.json', repeat.stdout)
        preserve_bytes(out / run_dir / name / 'stderr.log', first.stderr + repeat.stderr)
        assert first.returncode == repeat.returncode == 0 and first.stdout == repeat.stdout, name
        result = json.loads(first.stdout)
        report = result['report']
        assert result['replay_matches'] is True
        assert report['posted'] is report['official'] is report['payment_performed'] is report['extinction_proven'] is False
        if data['settlement'] is None:
            assert report['financial_state'] == 'WAITING_FINANCIAL_SETTLEMENT' and report['segregation'] is None
            assert report['settled_at'] is None and report['selected_platform_values'] == []
        else:
            settlement = data['settlement']
            freeze = datetime.fromisoformat(settlement['calculation_frozen_at'])
            selected = {}
            for message in sorted(data['platform_returns'], key=lambda m:m['sequence']):
                if message['processed_at'] is not None and datetime.fromisoformat(message['processed_at']) <= freeze:
                    selected.setdefault(message['tax'], {})[message['kind']] = {'amount': message['amount'], 'message_id': message['id']}
            assert report['selected_platform_values'] == (selected if selected else []), name
            expected_settled = max(datetime.fromisoformat(settlement['milestone_at']), datetime.fromisoformat(settlement['noncontractual_clear_at']))
            assert datetime.fromisoformat(report['settled_at']) == expected_settled
            total = Decimal('0.00')
            receiver = data['originator'] == 'RECEIVER'
            for tax in ['IBS', 'CBS']:
                returned = selected.get(tax, {})
                reference = Decimal(returned.get('CORRECTED', {'amount':data['taxes'][tax]})['amount'])
                with localcontext() as context:
                    context.prec = 100
                    amount = min(Decimal(settlement['paid_amount']) * reference / Decimal(data['original_amount']), reference) if receiver else reference
                    if 'OUTSTANDING' in returned:
                        amount = min(amount, Decimal(returned['OUTSTANDING']['amount']))
                    amount = amount.quantize(Decimal('0.01'), rounding=ROUND_DOWN)
                assert report['segregation']['taxes'][tax]['segregated'] == str(amount), (name, tax)
                total += amount
                checks += 1
            assert report['net_after_segregation'] == str(Decimal(settlement['paid_amount']) - total), name
            checks += 1
        records.append({'case': name, 'input_hash':data['input_hash'], 'output_sha256':hashlib.sha256(first.stdout).hexdigest()})
    preserve_json(out / run_dir / 'result.json', {'cases':len(records), 'decimal_checks':checks, 'byte_identical_repeats':len(records),
        'records':records, 'source_hashes':{p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in [
            'app/Modules/Fiscal/ConsumptionReform/SplitPayment158.php', 'app/Modules/Fiscal/ConsumptionReform/SplitTransaction158.php',
            'app/Console/Commands/AssessSplitPayment158Command.php', 'scripts/agent_a/split158_fixture.php', 'scripts/agent_a/verify158.py']}})


def ledger_native(version):
    import copy
    import re
    from fractions import Fraction
    root, out = handoffs156.ROOT, handoffs156.OUT
    php = [handoffs156.DOCKER, 'exec', handoffs156.CONTAINER, 'php']
    raw = subprocess.check_output(php + ['-r', "require 'vendor/autoload.php'; require 'scripts/agent_a/split158_fixture.php'; echo json_encode(split158Ledger(), JSON_THROW_ON_ERROR);"], cwd=root)
    seed = json.loads(raw)
    run_dir = 'ledger-native-' + version
    preserve_bytes(out / run_dir / 'seed.json', raw)
    cases = {'base': seed}
    for name, keys in [('without-applications', ['applications','excess_determinations','transfers']),
                       ('without-remittances', ['remittances','applications','excess_determinations','transfers']),
                       ('without-reports', ['reports','remittances','applications','excess_determinations','transfers']),
                       ('without-transfers', ['transfers'])]:
        data = copy.deepcopy(seed)
        for key in keys:
            data[key] = []
        cases[name] = data
    data = copy.deepcopy(cases['without-reports'])
    data['transactions'][2]['settlement'] = None
    cases['one-unsettled-installment'] = data
    data = copy.deepcopy(cases['without-applications'])
    data['remittances'][0]['amount'] = '22.00'
    cases['batch-shortfall'] = data
    data = copy.deepcopy(seed)
    data['remittances'][0]['amount'] = '120.00'
    cases['batch-overpayment'] = data
    data = copy.deepcopy(seed)
    data['transfers'][0]['amount'] = '5.00'
    cases['full-excess-transfer'] = data
    data = copy.deepcopy(seed)
    event = copy.deepcopy(data['applications'][0])
    event.update(id='OTHER.COMPENSATION', application_receipt_id='SYNTHETIC.COMPENSATION', modality='COMPENSATION',
        amount='10.00', applied_at='2026-10-05T12:30:00Z')
    del event['remittance_id']
    data['applications'][0]['amount'] = '15.00'
    data['applications'].append(event)
    cases['documented-compensation'] = data
    def scaled(value, factor):
        if isinstance(value, dict):
            return {k:scaled(v, factor) for k,v in value.items()}
        if isinstance(value, list):
            return [scaled(v, factor) for v in value]
        if isinstance(value, str) and re.fullmatch(r'(0|[1-9][0-9]*)\.[0-9]{2}', value):
            with localcontext() as context:
                context.prec = 100
                return format(Decimal(value) * factor, '.2f')
        return value
    cases['small-exact'] = scaled(seed, Decimal('0.01'))
    cases['large-exact'] = scaled(seed, Decimal('1000000000000000000'))
    data = copy.deepcopy(cases['without-applications'])
    data['debits']['IBS'] = '31.00'
    for transaction, amount in zip(data['transactions'], ['10.33','10.33','10.34']):
        transaction['taxes']['IBS'] = transaction['installment']['taxes']['IBS'] = amount
    data['reports'][0]['operation_taxes']['IBS'] = '20.66'
    data['reports'][0]['batch_taxes']['IBS'] = '60.66'
    data['reports'][1]['operation_taxes']['IBS'] = '10.34'
    data['reports'][1]['batch_taxes']['IBS'] = '40.34'
    data['remittances'][0]['amount'] = '101.00'
    cases['supplied-residual-cents'] = data
    def seal(data):
        data.pop('input_hash', None)
        data['input_hash'] = hashlib.sha256(json.dumps(data, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    checks = 0
    records = []
    for name, data in cases.items():
        for transaction in data['transactions']:
            seal(transaction)
        seal(data)
        relative = 'storage/app/agent-a-A158-' + run_dir + '/' + name + '.json'
        preserve_json(root / relative, data)
        command = php + ['artisan', 'fiscal:rtc:assess-split-payment', relative, '--mode=ledger']
        first = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        repeat = subprocess.run(command, cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        preserve_bytes(out / run_dir / name / 'first.json', first.stdout)
        preserve_bytes(out / run_dir / name / 'repeat.json', repeat.stdout)
        preserve_bytes(out / run_dir / name / 'stderr.log', first.stderr + repeat.stderr)
        assert first.returncode == repeat.returncode == 0 and first.stdout == repeat.stdout, name
        result = json.loads(first.stdout)
        report = result['report']
        assert result['replay_matches'] is True
        assert report['official'] is report['posted'] is report['payment_performed'] is report['credit_appropriated'] is False
        with localcontext() as context:
            context.prec = 100
            segregated = {}
            allocation_findings = []
            for transaction in data['transactions']:
                taxes = {}
                assert not transaction['platform_returns'], 'This oracle cut deliberately covers supplied schedules without platform revisions.'
                for tax in ['IBS','CBS']:
                    allocation = Decimal(transaction['taxes'][tax])
                    exact_allocation = Fraction(Decimal(data['debits'][tax])) * Fraction(Decimal(transaction['original_amount'])) / Fraction(Decimal(data['operation_amount']))
                    difference = Fraction(allocation) - exact_allocation
                    if difference:
                        allocation_findings.append((transaction['transaction_id'], tax, exact_allocation, difference))
                    if transaction['settlement'] is None:
                        taxes[tax] = Decimal('0.00')
                    else:
                        paid = Decimal(transaction['settlement']['paid_amount'])
                        amount = min(paid * allocation / Decimal(transaction['original_amount']), allocation) if transaction['originator'] == 'RECEIVER' else allocation
                        taxes[tax] = amount.quantize(Decimal('.01'), rounding=ROUND_DOWN)
                segregated[transaction['transaction_id']] = taxes
            assert report['allocation_proportionality_proven'] is (not allocation_findings), name
            assert len(report['allocation_findings']) == len(allocation_findings), name
            for actual, (transaction_id, tax, exact, difference) in zip(report['allocation_findings'], allocation_findings):
                assert actual['transaction_id'] == transaction_id and actual['tax'] == tax
                assert Fraction(actual['exact_proportional_amount']) == exact
                assert Fraction(actual['supplied_minus_exact']) == difference
            for tax in ['IBS','CBS']:
                events = [e for e in data['applications'] if e['tax'] == tax]
                applied = sum((Decimal(e['amount']) for e in events), Decimal('0.00'))
                own_reports = sum((sum((segregated[t][tax] for t in r['transaction_ids']), Decimal('0.00')) for r in data['reports']), Decimal('0.00'))
                excess = sum((Decimal(e['amount']) for e in data['excess_determinations'] if e['tax'] == tax), Decimal('0.00'))
                transferred = sum((Decimal(e['amount']) for e in data['transfers'] if e['tax'] == tax), Decimal('0.00'))
                total_segregated = sum((t[tax] for t in segregated.values()), Decimal('0.00'))
                expected = {'debit':Decimal(data['debits'][tax]), 'prior':Decimal(data['previously_extinguished'][tax]),
                    'allocated':sum((Decimal(t['taxes'][tax]) for t in data['transactions']), Decimal('0.00')),
                    'segregated':total_segregated, 'reported':own_reports, 'applied':applied,
                    'split_applied':sum((Decimal(e['amount']) for e in events if e['modality'] == 'SPLIT_PAYMENT'), Decimal('0.00')),
                    'other_applied':sum((Decimal(e['amount']) for e in events if e['modality'] != 'SPLIT_PAYMENT'), Decimal('0.00')),
                    'remaining':Decimal(data['debits'][tax])-Decimal(data['previously_extinguished'][tax])-applied,
                    'excess':excess, 'transferred':transferred, 'pending_transfer_to_supplier':excess-transferred,
                    'unreported':total_segregated-own_reports}
                for key, amount in expected.items():
                    assert report['taxes'][tax][key] == format(amount, '.2f'), (name,tax,key,amount)
                    checks += 1
            for index, remittance in enumerate(data['remittances']):
                tax = remittance['tax']
                related = [r for r in data['reports'] if r['id'] in remittance['report_ids']]
                expected_batch = sum((Decimal(r['batch_taxes'][tax]) for r in related), Decimal('0.00'))
                own = sum((sum((segregated[t][tax] for t in r['transaction_ids']), Decimal('0.00')) for r in related), Decimal('0.00'))
                own_applied = sum((Decimal(e['amount']) for e in data['applications'] if e.get('remittance_id') == remittance['id']), Decimal('0.00'))
                own_excess = sum((Decimal(e['amount']) for e in data['excess_determinations'] if e['remittance_id'] == remittance['id']), Decimal('0.00'))
                expected = {'batch_expected':expected_batch, 'batch_received':Decimal(remittance['amount']),
                    'batch_difference':Decimal(remittance['amount'])-expected_batch, 'operation_reported':own,
                    'operation_applied':own_applied, 'operation_excess_determined':own_excess,
                    'operation_reported_not_accounted':own-own_applied-own_excess}
                for key, amount in expected.items():
                    assert report['remittances'][index][key] == format(amount, '.2f'), (name, key)
                    checks += 1
        records.append({'case':name,'input_hash':data['input_hash'],'output_sha256':hashlib.sha256(first.stdout).hexdigest()})
    preserve_json(out / run_dir / 'result.json', {'cases':len(records),'decimal_checks':checks,'byte_identical_repeats':len(records),'records':records,
        'source_hashes':{p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in [
            'app/Modules/Fiscal/ConsumptionReform/SplitPayment158.php','app/Modules/Fiscal/ConsumptionReform/SplitTransaction158.php',
            'app/Modules/Fiscal/ConsumptionReform/SplitLedger158.php','app/Console/Commands/AssessSplitPayment158Command.php',
            'scripts/agent_a/split158_fixture.php','scripts/agent_a/verify158.py']}})


if __name__ == '__main__':
    if sys.argv[1] == 'run':
        handoffs156.run(sys.argv[2], sys.argv[3:])
    elif sys.argv[1] == 'collect':
        handoffs156.collect(sys.argv[2], sys.argv[3])
    elif sys.argv[1] == 'native':
        native(sys.argv[2] if len(sys.argv) > 2 else 'v1')
    elif sys.argv[1] == 'transaction-native':
        transactions(sys.argv[2])
    elif sys.argv[1] == 'ledger-native':
        ledger_native(sys.argv[2])
    else:
        raise SystemExit('Expected run NAME ARGS... or collect NAME JUNIT')
