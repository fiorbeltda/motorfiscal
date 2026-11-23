"""Front 1 A162: explicit legal simplified split policy, facts and questions; no invented official parameters."""
import argparse
import copy
import json
import os
import subprocess
from datetime import datetime, timezone
from fork_phase4b_a159_schedule_bundle import ROOT, read, write, digest

VERSION = '2026.11.23-a162-draft1'
FACTS = [
    ('rtc.split_procedure', 'ENUM', ['STANDARD', 'SIMPLIFIED'], 'Qual procedimento de split se aplica: STANDARD ou SIMPLIFIED?'),
    ('rtc.split_nominal_values_identified', 'BOOLEAN', None, 'Na originacao foram identificados os valores nominais dos dois tributos IBS e CBS?'),
    ('rtc.simplified_sector', 'STRING', None, 'Qual setor documentado seleciona o parametro do simplificado?'),
    ('rtc.simplified_split_option', 'ENUM', ['EXPLICIT', 'OMITTED_TAX_IDENTIFICATION', 'MANDATORY_JOINT_ACT'], 'Informe a opcao explicita, a falta de identificacao de ambos os tributos ou o ato conjunto obrigatorio do simplificado.'),
    ('rtc.simplified_split_record', 'ARRAY', None, 'Informe um registro com calculation A162 e period A162 opcional; separe percentuais, segregacao, proposta, aplicacoes e excedentes documentados.'),
]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP85', 'php'))
    args = parser.parse_args()
    previous = read('bundles/consumption-phase4b-2026.11.23-a161-draft1.json')
    target = f'bundles/consumption-phase4b-{VERSION}.json'
    available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    php = "require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\SimplifiedSplitIntegration162::POLICY, JSON_THROW_ON_ERROR);"
    codes['simplified_split_a162'] = json.loads(subprocess.check_output([args.php, '-r', php], cwd=ROOT.parent.parent))
    for key in ['simplified_split_percentages_a162', 'simplified_split_mandatory_acts_a162', 'simplified_split_deployments_a162']:
        codes[key] = []
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
            control['gap'] = ('A157 e A158-A161 preservados. A162:modelo legal simplificado separado da fase1 tecnica, '
                              'com parametro temporal/setorial qualificado dos dois tributos,opcao e livro por prioridade. '
                              'Catalogos oficiais de percentual/mandato/implantacao seguem vazios;testes usam candidatos '
                              'sinteticos identificados. Restam calendarios/prazos,demais perfis publicos/consorcios/Simples/MEI '
                              'e prova integral dos requisitos.Nada e autenticado,pago,lancado ou convertido em credito.')
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, predecessor_bundle_id=previous['bundle_id'],
                  knowledge_available_at=available, notice='A162:modelo legal simplificado local com percentuais qualificados e livro de apuracao por prioridade,sem presumir parametros/implantacao oficiais.')
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
