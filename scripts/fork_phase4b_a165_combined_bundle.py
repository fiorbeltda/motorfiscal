"""Front 1 A165: combined per-supply import/export/events,with native document allocation."""
import argparse
import copy
import json
import os
import subprocess
from datetime import datetime, timezone
from fork_phase4b_a159_schedule_bundle import ROOT, read, write, digest

VERSION = '2026.11.23-a165-draft1'
FACTS = [
    ('rtc.combined_supply_binding', 'ARRAY', None, 'Informe o vinculo unico do fornecimento/linha ao contrato combinado,preco BRL alocado e prova da alocacao. Preserve o contexto fiscal e os eventos de cada fornecimento.'),
]


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default=os.environ.get('PHP85', 'php'))
    args = parser.parse_args()
    previous = read('bundles/consumption-phase4b-2026.11.23-a164-draft1.json')
    target = f'bundles/consumption-phase4b-{VERSION}.json'
    available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
    codes = read(previous['resource_paths']['rtc_code_catalog'])
    codes['version'] = VERSION
    php = "require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\CombinedOperation165::POLICY, JSON_THROW_ON_ERROR);"
    codes['combined_operation_a165'] = json.loads(subprocess.check_output([args.php, '-r', php], cwd=ROOT.parent.parent))
    php = php.replace('CombinedOperation165::POLICY','CrossBorderTaxPoint165::POLICY')
    codes['cross_border_tax_point_a165'] = json.loads(subprocess.check_output([args.php, '-r', php], cwd=ROOT.parent.parent))
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
            control['gap'] = control['gap'] + ' A165:composicao por fornecimento preserva recolhimentos e pendencias documentais individuais.'
        if control['id'] == '4B.4.TAX_BASE_AND_CALCULATION':
            control['gap'] = ('A165:importacao/exportacao/eventos e alocacoes de documentos em moeda original/BRL '
                              'reconciliados por fornecimento,sem aliquota media ou soma de antecipacao com base final. '
                              'Pendencias documentais e demais perfis do escopo original permanecem explicitas.')
    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION, predecessor_bundle_id=previous['bundle_id'],
                  knowledge_available_at=available, notice='A165:operacoes combinadas com decisao por fornecimento,documentos/cambio/eventos separados e memorias nominais sem promover pendencias documentais a aprovacao fiscal.')
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
