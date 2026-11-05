"""Independent Decimal verification of captured native arithmetic (not PHP DecimalValue)."""
import json
import sys
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path

from evidence_io import preserve_json
from handoffs156 import OUT, sha


def verify(version):
    capture = OUT/('capture-'+version+'.log')
    data = json.loads(capture.read_text(encoding='utf-8'))
    checks = []
    for row in data['rows']:
        d = row['candidate']['candidate_decision']
        trace = {x['stage']: x for x in row['result']['trace']}
        if d:
            base = Decimal(d['base']['taxable'])
            for tax, amount, rate in [('ibs','state_amount','state_rate_percent'),
                                      ('ibs','municipal_amount','municipal_rate_percent'),
                                      ('cbs','nominal_amount','federal_rate_percent')]:
                expected = (base*Decimal(d[tax][rate])/100).quantize(Decimal('.01'), rounding=ROUND_HALF_UP)
                assert Decimal(d[tax][amount]) == expected
                checks.append({'profile': row['profile'], 'year': row['year'], 'component': tax+'.'+amount,
                    'base': str(base), 'percent': d[tax][rate], 'expected': str(expected), 'actual': d[tax][amount]})
            assert Decimal(d['ibs']['nominal_amount']) == Decimal(d['ibs']['state_amount'])+Decimal(d['ibs']['municipal_amount'])
        else:
            base = Decimal(trace['BASE']['taxable_base'])
            values = trace['IMPORT_NOMINAL_CALCULATION']
            for key, percent in [('ibs_state','1.25'),('ibs_municipal','0.75'),('cbs','3.50')]:
                expected = (base*Decimal(percent)/100).quantize(Decimal('.01'), rounding=ROUND_HALF_UP)
                assert Decimal(values[key]) == expected
                checks.append({'profile': row['profile'], 'year': row['year'], 'component': key,
                    'base': str(base), 'percent': percent, 'expected': str(expected), 'actual': values[key]})
        assert row['result']['decision'] == [] and row['result']['official'] is False
    preserve_json(OUT/('decimal-oracles-'+version+'.json'), {'rows': len(data['rows']),
        'component_oracles': len(checks), 'capture_sha256': sha(capture), 'checks': checks, 'official_decisions': 0})
    print(json.dumps({'scenarios': len(data['rows']), 'component_oracles': len(checks)}))


if __name__ == '__main__':
    verify(sys.argv[1])
