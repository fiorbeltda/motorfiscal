"""Front 1 A161: new bundle for one boleto with multiple identified payment events."""
import copy
from datetime import datetime, timezone
from fork_phase4b_a159_schedule_bundle import read, write, digest, ROOT

VERSION = '2026.11.23-a161-draft1'


def main():
    previous = read('bundles/consumption-phase4b-2026.11.23-a160-draft1.json')
    target = f'bundles/consumption-phase4b-{VERSION}.json'
    available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
                  predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available,
                  notice='A161: multiplos pagamentos de boleto, cronograma sem duplicar parcelas e informes por pagamento.')
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    codes['split_payment_a158'].update(version='A161', integration='A161', ledger_contract='RTC-SPLIT-LEDGER-A161-V1',
                                     boleto_payments_contract='RTC-SPLIT-BOLETO-PAYMENTS-A161-V1')
    questions = read(previous['resource_paths']['questions'])
    for question in questions:
        if question['fact_key'] == 'rtc.split_payment_ledger':
            question['prompt'] = ('Informe um livro RTC-SPLIT-LEDGER-A161-V1, com parcelas futuras A159, transacoes '
                                  'A158/A160 ou boleto RTC-SPLIT-BOLETO-PAYMENTS-A161-V1. Em boleto com varios '
                                  'pagamentos, informe cada payment_id, recibo, snapshot congelado e indicador integral; '
                                  'os informes devem usar payment_references, sem duplicar parcelas.')
    controls = read(previous['production_controls']['path'])
    controls['version'] = VERSION
    for control in controls['controls']:
        if control['id'] == '4B.3.SUBJECTS_AND_COLLECTION':
            control['gap'] = ('A157 reconciliacao pelo adquirente e A158-A161 split padrao local integrados, com '
                              'parcelas futuras, revisoes/reset e multiplos pagamentos de boleto identificados. '
                              'Restam modelo legal simplificado, calendarios/prazos qualificados, demais perfis '
                              'publicos/consorcios/Simples/MEI e responsabilidades aplicaveis. Implantacao oficial '
                              'do split e parametros de referencia/redutores nao sao presumidos; nada e autenticado, '
                              'pago ou lancado. B25 foi retirado; contratos anteriores preservados em seus bundles.')
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
    write(target, bundle)
    print(bundle['bundle_id'])


if __name__ == '__main__':
    main()
