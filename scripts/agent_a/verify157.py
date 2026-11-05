"""A157 command capture using the existing immutable receipt utility in a new directory."""
import sys
import json
import hashlib
import subprocess
from decimal import Decimal
from datetime import datetime
from pathlib import Path

import handoffs156
from evidence_io import preserve_bytes, preserve_json

handoffs156.OUT = Path('D:/ProjetoSaasExports/agent-a-A157-acquirer-collection-20261105-v1')


def seal(input_data):
    input_data = dict(input_data)
    input_data.pop('input_hash', None)
    raw = json.dumps(input_data, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()
    input_data['input_hash'] = hashlib.sha256(raw).hexdigest()
    return input_data


def native():
    root, out = handoffs156.ROOT, handoffs156.OUT
    php = [handoffs156.DOCKER, 'exec', handoffs156.CONTAINER, 'php', '-d', 'memory_limit=1G']
    seed_raw = subprocess.check_output(php+['-r', "require 'vendor/autoload.php'; require 'scripts/agent_a/collection157_fixture.php'; echo json_encode(collection157Ledger(), JSON_THROW_ON_ERROR);"], cwd=root)
    preserve_bytes(out/'native-seed.json', seed_raw)
    seed = json.loads(seed_raw)
    assert seal(seed)['input_hash'] == seed['input_hash']
    cases = {'base': seed, 'no-payments': seal(seed | {'payments': [], 'transfers': []})}
    for year in [2026, 2027, 2030, 2033]:
        for received in ['0.00', '40.00', '80.00', '100.00']:
            data = json.loads(seed_raw)
            for key in ['known_at', 'operation_date', 'operation_at', 'history_as_of']:
                data[key] = data[key].replace('2026', str(year))
            data['payments'] = [data['payments'][0], data['payments'][2]]
            data['payments'][0]['amount'] = received
            for payment in data['payments']:
                payment['settled_at'] = payment['settled_at'].replace('2026', str(year))
            data['transfers'] = []
            cases[f'{year}-{received}'] = seal(data)
    event = {key:seed[key] for key in ['document_id','supplier_id','acquirer_id','payment_transaction_id','currency']}
    event.update({'id':'OTHER1','tax':'IBS','modality':'COMPENSATION','applied':True,'amount':'10.00',
        'applied_at':'2026-10-02T12:00:00Z','authority_receipt_id':'SYNTHETIC.OTHER1','evidence_id':'SYNTHETIC.OTHER1.PROOF'})
    cases['intervening'] = seal(seed | {'other_extinctions':[event]})
    records = []
    for name, data in cases.items():
        relative = 'storage/app/agent-a-A157-native-v1/'+name+'.json'
        preserve_json(root/relative, data)
        command = php+['artisan','fiscal:rtc:reconcile-acquirer-collection',relative]
        first = subprocess.run(command,cwd=root,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        second = subprocess.run(command,cwd=root,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        preserve_bytes(out/'native'/name/'first.json',first.stdout)
        preserve_bytes(out/'native'/name/'repeat.json',second.stdout)
        preserve_bytes(out/'native'/name/'stderr.log',first.stderr+second.stderr)
        assert first.returncode == second.returncode == 0 and first.stdout == second.stdout, name
        body = json.loads(first.stdout)
        assert body['replay_matches'] is True
        report = body['report']
        remaining = {t:Decimal(data['debits'][t])-Decimal(data['previously_extinguished'][t]) for t in ['IBS','CBS']}
        applied = {t:Decimal('0') for t in remaining}
        events = [(datetime.fromisoformat(p['settled_at']), 'payment', p) for p in data['payments']]
        events += [(datetime.fromisoformat(p['applied_at']), 'other', p) for p in data['other_extinctions']]
        for _, kind, payment in sorted(events, key=lambda x:x[0]):
            tax = payment['tax']
            amount = Decimal(payment['amount'])
            value = min(amount, remaining[tax]) if kind == 'payment' else amount
            remaining[tax] -= value
            if kind == 'payment': applied[tax] += value
        for tax, values in report['taxes'].items():
            v = {k:Decimal(x) for k,x in values.items()}
            assert v['debit'] == v['prior']+v['other_applied']+v['applied']+v['remaining']
            assert v['received'] == v['applied']+v['excess']
            assert v['excess'] == v['transferred']+v['pending_transfer_to_supplier']
            assert v['remaining'] == remaining[tax] and v['applied'] == applied[tax]
        assert report['posted'] is report['payment_performed'] is report['official'] is False
        records.append({'case':name,'exit_code':0,'byte_identical':True,'sha256':hashlib.sha256(first.stdout).hexdigest(),
            'taxes':report['taxes'],'decimal_checks':10})
    rejected = seal(seed | {'history_complete':False})
    relative = 'storage/app/agent-a-A157-native-v1/incomplete.json'
    preserve_json(root/relative,rejected)
    result = subprocess.run(php+['artisan','fiscal:rtc:reconcile-acquirer-collection',relative],cwd=root,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    preserve_bytes(out/'native/incomplete.json',result.stdout)
    assert result.returncode == 1 and json.loads(result.stdout)['report']['reason'] == 'A157_COMPLETE_LEDGER_REQUIRED'
    preserve_json(out/'native-decimal-v1.json',{'positive_cases':len(records),'repetitions':2,'independent_decimal_checks':10*len(records),
        'refusal_exit_code':result.returncode,'cases':records,'source_hashes':{p:handoffs156.sha(root/p) for p in [
            'app/Modules/Fiscal/ConsumptionReform/AcquirerCollection157.php',
            'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
            'app/Console/Commands/ReconcileAcquirerCollection157Command.php','scripts/agent_a/collection157_fixture.php','scripts/agent_a/verify157.py']}})
    print(json.dumps({'positive_cases':len(records),'independent_decimal_checks':10*len(records),'expected_refusal_exit':1}))

if __name__ == '__main__':
    if sys.argv[1] == 'run':
        handoffs156.run(sys.argv[2], sys.argv[3:])
    elif sys.argv[1] == 'collect':
        handoffs156.collect(sys.argv[2], sys.argv[3])
    elif sys.argv[1] == 'native':
        native()
