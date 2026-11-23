"""Front 1: immutable B38 fork activating A159 supplied future installments."""
import copy
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.11.23-a159-draft1'


def read(path):
    return json.loads((ROOT / path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists() and read(path) != value:
        raise RuntimeError(f'Refusing to overwrite {target}')
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')


def main():
    previous = read('bundles/consumption-phase4b-2026.11.06-b38-draft1.json')
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
                  predecessor_bundle_id=previous['bundle_id'], knowledge_available_at='2026-11-23T12:00:00+00:00',
                  notice='A159: cronograma completo com parcelas futuras nao iniciadas; sem liquidacao presumida.')
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    policy = codes['split_payment_a158']
    policy.update(version='A159', integration='A159', ledger_contract='RTC-SPLIT-LEDGER-A159-V1',
                  planned_installment_contract='RTC-SPLIT-PLANNED-INSTALLMENT-A159-V1')
    questions = read(previous['resource_paths']['questions'])
    for question in questions:
        if question['fact_key'] == 'rtc.split_payment_ledger':
            question['prompt'] = ('Informe um livro RTC-SPLIT-LEDGER-A159-V1 com o cronograma completo: '
                                  'transacoes iniciadas A158 e parcelas futuras RTC-SPLIT-PLANNED-INSTALLMENT-A159-V1. '
                                  'Parcelas futuras exigem alocacao, evidencia e data futura; nao possuem liquidacao ou retornos.')
    controls = read(previous['production_controls']['path'])
    controls['version'] = VERSION
    for control in controls['controls']:
        if control['id'] == '4B.3.SUBJECTS_AND_COLLECTION':
            control['gap'] += ' A159: parcelas futuras alocadas sem iniciacao ficticia; revisoes/reset e pagamentos multiplos ainda pendentes.'
    for key, path, value in [
        ('rtc_code_catalog', f'catalogs/rtc-codes-{VERSION}.json', codes),
        ('questions', f'questions/consumption-integrated-{VERSION}.json', questions),
    ]:
        write(path, value)
        bundle['resource_paths'][key] = path
        bundle['content_hashes'][key] = digest(value)
    bundle['catalog_versions']['rtc_codes'] = VERSION
    path = f'production/phase4b-controls-{VERSION}.json'
    write(path, controls)
    bundle['production_controls'] = {'path': path, 'sha256': digest(controls)}
    write(f'bundles/consumption-phase4b-{VERSION}.json', bundle)
    print(bundle['bundle_id'])


if __name__ == '__main__':
    main()
