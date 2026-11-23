"""Front 1 A160: immutable A159 fork enabling fiscal revisions and explicit return resets."""
import copy
from fork_phase4b_a159_schedule_bundle import read, write, digest

VERSION = '2026.11.23-a160-draft1'


def main():
    previous = read('bundles/consumption-phase4b-2026.11.23-a159-draft1.json')
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
                  predecessor_bundle_id=previous['bundle_id'], knowledge_available_at='2026-11-23T13:00:00+00:00',
                  notice='A160: historico fiscal e reset explicito de retornos por revisao, com cronograma futuro A159.')
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    codes['split_payment_a158'].update(version='A160', integration='A160',
                                     ledger_contract='RTC-SPLIT-LEDGER-A160-V1',
                                     transaction_contract='RTC-SPLIT-TRANSACTION-A160-V1')
    questions = read(previous['resource_paths']['questions'])
    for question in questions:
        if question['fact_key'] == 'rtc.split_payment_ledger':
            question['prompt'] = ('Informe um livro RTC-SPLIT-LEDGER-A160-V1 com transacoes A158 estaveis ou '
                                  'RTC-SPLIT-TRANSACTION-A160-V1 com historico fiscal completo e prova de reset, '
                                  'e parcelas futuras A159. Identifique revisoes e retornos processados ate o congelamento.')
    controls = read(previous['production_controls']['path'])
    controls['version'] = VERSION
    for control in controls['controls']:
        if control['id'] == '4B.3.SUBJECTS_AND_COLLECTION':
            control['gap'] += ' A160:revisoes/reset explicitos qualificados;multiplos pagamentos e calendario ainda pendentes.'
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
