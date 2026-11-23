"""Front 1 A164: source-bound fallback/acquirer refunds and explicit allocations."""
import argparse
import copy
import json
import os
import subprocess
from datetime import datetime, timezone
from fork_phase4b_a159_schedule_bundle import ROOT, read, write, digest

VERSION = '2026.11.23-a164-draft1'
FACTS = [
    ('rtc.refund_deadline_record', 'ARRAY', None, 'Informe um livro A164 de devolucoes padrao/fallback ou pelo adquirente com contexto validado,alocacoes e aviso documental da autoridade. Declare a cobertura selecionada ou integral dos excedentes.'),
]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP85', 'php'))
    args = parser.parse_args()
    previous = read('bundles/consumption-phase4b-2026.11.23-a163-draft1.json')
    target = f'bundles/consumption-phase4b-{VERSION}.json'
    available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    php = "require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\RefundIntegration164::POLICY, JSON_THROW_ON_ERROR);"
    codes['refund_deadlines_a164'] = json.loads(subprocess.check_output([args.php, '-r', php], cwd=ROOT.parent.parent))
    facts = read(previous['resource_paths']['fact_definitions'])
    questions = read(previous['resource_paths']['questions'])
    anchor = next(i for i, row in enumerate(facts) if row['key'] == 'rtc.split_payment_ledger')
    q_anchor = next(i for i, row in enumerate(questions) if row['fact_key'] == 'rtc.split_payment_ledger')
    for i, (key, kind, values, prompt) in enumerate(FACTS):
        assert key not in {row['key'] for row in facts}
        fact = {'key': key, 'type': kind, 'material': True}
        if values:
            fact['allowed_values'] = values
        facts.insert(anchor + 1 + i, fact)
        questions.insert(q_anchor + 1 + i, {'fact_key': key, 'answer_type': kind, 'rank': questions[q_anchor]['rank'], 'prompt': prompt})
    controls = read(previous['production_controls']['path'])
    controls['version'] = VERSION
    for control in controls['controls']:
        if control['id'] == '4B.3.SUBJECTS_AND_COLLECTION':
            control['gap'] = ('A157-A163 preservados.A164:devolucoes padrao/fallback e adquirente com marcos '
                              'art29/36,alocacao por pagamento/excedente e conservacao dos repasses consolidados. '
                              'Calendarios de expediente oficial ainda precisam ser qualificados;nao substituir por calendario bancario. '
                              'Restam exclusoesANS,demais perfis e auditoria integral dos44 requisitos.')
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, predecessor_bundle_id=previous['bundle_id'],
                  knowledge_available_at=available, notice='A164:devolucoes com marcos regulamentares e alocacoes monetarias documentais,sem presumir calendario ou autenticacao oficial.')
    bundle['catalog_versions']['rtc_codes'] = VERSION
    for key, path, value in [
        ('rtc_code_catalog', f'catalogs/rtc-codes-{VERSION}.json', codes),
        ('fact_definitions', f'facts/consumption-phase4b-{VERSION}.json', facts),
        ('questions', f'questions/consumption-integrated-{VERSION}.json', questions),
    ]:
        write(path, value)
        bundle['resource_paths'][key] = path
        bundle['content_hashes'][key] = digest(value)
    path = f'production/phase4b-controls-{VERSION}.json'
    write(path, controls)
    bundle['production_controls'] = {'path': path, 'sha256': digest(controls)}
    write(target, bundle)
    print(bundle['bundle_id'])


if __name__ == '__main__':
    main()
