"""Plan and collect existing A44 tests without changing production or old evidence."""
import hashlib
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path
from xml.etree import ElementTree as ET

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / 'docs/fiscal/agent-a/20261103'
OUT = Path('D:/ProjetoSaasExports/agent-a-A154-acceptance44-review-20261103-v1')
DOCKER = 'D:/Docker/DockerDesktop/resources/bin/docker.exe'
CONTAINER = 'motorfiscal-agente-a-20261103'
JUNIT = 'storage/app/agent-a-A154-existing44-v1.junit.xml'
JUNIT_V2 = 'storage/app/agent-a-A154-existing44-v2.junit.xml'
SUPPLEMENTS = {
    '4B.1.5': ['Integration/AdvanceOrigin88Engine', 'Integration/RecordedAdvancePolicy91',
                 'Integration/RecordedAdvanceComponents91', 'Integration/RecordedAdvance91Engine',
                 'Integration/TaxPointLifecycle147Integration', 'Unit/TemporalComponents86',
                 'Unit/AdvanceOrigin88', 'Unit/AdvanceContext88'],
    '4B.2.4': ['Integration/Geographic116DocumentPrices'],
    '4B.2.11': ['Integration/EntityDomicile151', 'Integration/PartyDomicile153',
                 'Integration/CentralizedDomicile152'],
    '4B.2.2': ['Unit/DonationNominalCalculation'],
    '4B.4.2': ['Unit/LegacyExclusionWindow135'],
    '4B.4.8': ['Unit/AnnexServiceReduction'],
    '4B.4.10': ['Integration/FinancialScopeIntegration', 'Unit/FinancialOperationEngine',
                  'Unit/FinancialRegimeQualifier', 'Unit/FinancialTaxEngine'],
    '4B.3.9': ['Unit/SimplesTemporalCatalog78'],
}

# Reviewed behaviors and concrete remaining proof, not copied historical gaps.
# I = implemented mechanism, whole-scope evidence incomplete; P = partial;
# L = identified implementation gap. No category grants automatic group closure.
REVIEWS = {
    '4B.1.1': ('P', 'Seleção de fases e recusa de taxas não comprovadas; rota pós-ISS presente.', 'Completar matriz de ramos/eventos por período 2026–2033; separar parâmetros externos de implementação.'),
    '4B.1.2': ('I', 'Cálculo nominal de teste 2026 separado e dispensa condicionada; financeiro também calcula débitos nominais.', 'Aferir aplicabilidade e condições de todos os perfis 2026 exigidos; prova de SaaS/financeiro não cobre famílias restantes.'),
    '4B.1.3': ('P', 'Fatores ISS 2029–2032 e extinção 2033 implementados; teste injeta referência municipal.', 'Taxa municipal de 2028 no teste é sintética; tratamento especial e eventos cruzados permanecem pendentes. Metadado ICMS não é cálculo ICMS.'),
    '4B.1.4': ('P', 'Runner integrado preserva ISS/retenções e encaminha pós-ISS ao RTC.', 'Demonstrar coexistência por família/período; eventos de tributos legados cruzando 2033 recebem pergunta, não cálculo concluído.'),
    '4B.1.5': ('P', 'Contratos 86/88/91 distinguem datas relevantes e conhecimento; guardas próprias para reconciliação.', 'Fixtures históricas 86/88/91 ausentes impedem parte da reexecução; eventos públicos/cancelados/especiais e liquidação ainda precisam prova.'),
    '4B.1.6': ('P', 'A147 valida instantes estritos e fronteiras de publicação/revogação/supersessão; lifecycle114 mantém implantação qualificada.', 'Não há prova de lifecycle em todos os ramos/regras; recuperar fixtures históricas e concluir cenários faltantes.'),
    '4B.1.7': ('P', 'Recusa futura/vencida e conflitos nos catálogos e políticas inspecionados, incluindo ciclo A147.', 'Cobertura integral dos ramos/eventos não demonstrada; não confundir ativação de teste com implantação oficial.'),
    '4B.1.8': ('I', 'Seletores usam operação/competência/conhecimento e eventos, inclusive instantes dentro do mesmo ano.', 'Prova transversal de todas as decisões ainda incompleta; suítes temporais históricas dependem de fixtures ausentes.'),
    '4B.2.1': ('P', 'SaaS oneroso e composição A145/A146 com decisão por fornecimento.', 'Faltam famílias/condições exigidas além dos perfis domésticos BRL já comprovados.'),
    '4B.2.2': ('P', 'Doação/uso pessoal têm qualificadores e origem/valor material em contratos próprios.', 'Não basta fixture antiga recusada: comprovar rota corrente positiva de cada espécie não onerosa e seus efeitos de cálculo.'),
    '4B.2.3': ('P', 'A149 qualifica oito famílias locais; A151–153 ampliam domicílio e pessoal remoto.', 'Qualificador local não entrega incidência/cálculo de todas as famílias. Integração classificatória/documental depende de B.'),
    '4B.2.4': ('P', 'Localidade vinculada a operação e sujeitos; A150 trata imóvel e via; A151–153 domicílio material.', 'PF empresarial e demais contextos materiais precisam qualificação; compor resultado com runner/classificação sem escolha implícita.'),
    '4B.2.5': ('P', 'Destino distingue adquirente e destinatário, residência/onerosidade e centralização qualificada.', 'Faltam integração e casos restantes por família/evento; autenticação externa não é requisito implícito de todo ensaio Sandbox.'),
    '4B.2.6': ('P', 'Rótulo B2B/B2C complementado por identificação e domicílio de não cadastrado/PF A153.', 'Falta prova de tratamento completo por família e regime; não inferir consumidor ou estabelecimento pelo rótulo.'),
    '4B.2.7': ('P', 'A149 separa prestação material e A153 pessoal remoto; presença não equivale automaticamente a destino.', 'Integrar todas as famílias presenciais/remotas/digitais com incidência e cálculo aplicáveis.'),
    '4B.2.8': ('P', '129 separa papéis na importação digital; 141 reconcilia BRL; 143 monta candidato local delimitado.', 'Outros perfis/bases/eventos faltam. Rejeição XSD histórica é limite de B preservado, não novo defeito de cálculo presumido.'),
    '4B.2.9': ('P', 'Qualificação de consumo no exterior e exportação parcial; resultados ISS96 e par135 possuem testes.', 'Reexecutar e classificar cada resultado atual; concluir demais serviços, base/preço ISS e câmbio sem generalizar o perfil parcial.'),
    '4B.2.10': ('P', 'A151 seleciona estabelecimento efetivamente atendido; A152 exige matriz única e cobertura de centralização.', 'PF empresarial/estabelecimento e cenários restantes ainda abertos; integração real do predicado externo de crédito pertence a B.'),
    '4B.2.11': ('P', 'A153 comprova habitação/relevância, critérios escolhidos e alternativa com impossibilidade; A151–152 tratam entidades.', 'O índice A144 não vinculava estes novos testes neste ID; A154 corrige o vínculo sem mudar o histórico. Falta PF empresarial e integração.'),
    '4B.2.12': ('P', 'Plataforma distingue exclusão payment-only, gatilhos domésticos e fornecedor estrangeiro.', 'Concluir opções art22 e consequências de pagamento/documento; referência declarada não autentica notificação oficial.'),
    '4B.3.1': ('P', 'Papéis do fornecedor preservados; A148 recusa identidades/referências inexatas.', 'Qualificar condição de contribuinte/regime nas famílias restantes, sem tratar ID como situação cadastral.'),
    '4B.3.2': ('P', 'Adquirente separado do destinatário e pagador elegível; domicílio A151–153 e guardas A148.', 'Exercício da opção, vínculos operação/débito e liquidação faltam; qualificação local não comprova pagamento.'),
    '4B.3.3': ('P', '129 usa destinatário BR quando cabível; A153 exige critérios próprios quando distinto do adquirente.', 'Integrar domicílio/identidade material por família; endereço do arranjo do adquirente não substitui destinatário.'),
    '4B.3.4': ('P', 'Importador vinculado a adquirente/destinatário e bases art64§5 V/VI; inconsistências recusadas.', 'Demais condições materiais e perfis de importação faltam; crédito/documento externos ficam separados no handoff.'),
    '4B.3.5': ('P', 'Responsabilidade de plataforma qualificada separada do contribuinte na importação.', 'Opções art22, diferenças de referência e consequências da cobrança/documentos ainda incompletas.'),
    '4B.3.6': ('P', '129 distingue solidariedade estrangeira e plataforma com fundamento explícito.', 'Demais hipóteses de responsabilidade e ciclo de obrigação até pagamento/extinção precisam contratos/provas.'),
    '4B.3.7': ('P', 'Ciclo114 e A148 bloqueiam split sem implantação e referências qualificadas.', 'Lacuna funcional: segregação padrão/simplificada, parcelas e reconciliação débito/liquidação. Guardas não são implementação desse ciclo.'),
    '4B.3.8': ('P', 'Elegibilidade art36 e instrumento incompatível com split qualificados com referências estritas.', 'Lacuna funcional: exercício da opção e vínculo comprovável do pagamento com débito/extinção.'),
    '4B.3.9': ('P', 'Compra pública e opção regular Simples possuem componentes; A152 exige regime regular na centralização.', 'Consórcio público, perfis especiais exigidos e integração Simples/MEI continuam abertos; não delegar escopo mínimo à fase5.'),
    '4B.3.10': ('P', 'Contribuinte, responsável e pagador elegível são campos distintos em importação/plataforma/cobrança.', 'Comprovar consistência nos demais perfis e eventos; ciclo de extinção ainda não entregue.'),
    '4B.4.1': ('P', 'Base ordinária qualifica ajustes e importação141; composição A145/A146 preserva bases por fornecimento.', 'Demais regimes e eventos precisam bases próprias. Par143 delimitado não encerra requisito.'),
    '4B.4.2': ('P', 'Exclusões documentadas e janela135; tipos/linhas/identidades estritos119.', 'Qualificação material por regime/perfil/evento ainda incompleta; descontos/reembolsos não são exclusão irrestrita.'),
    '4B.4.3': ('P', 'Desconto incondicional exige prova; desconto condicionado não reduz automaticamente a base.', 'Completar espécies/perfis e compatibilidade com preço documental ISS por evento.'),
    '4B.4.4': ('P', 'Acréscimos com decimal exato, referência material e linhas únicas119.', 'Ramos restantes/eventos ainda não têm prova completa do vínculo e inclusão de acréscimos.'),
    '4B.4.5': ('P', 'Reembolso exige terceiro/documento/evidência; entradas vazias e monetários inexatos recusados.', 'Qualificação causal de cada espécie/perfil não é provada só pela presença dos identificadores.'),
    '4B.4.6': ('P', 'A145/A146 tratam independentes e principal/acessório sem dupla tributação ou preço inventado.', 'Perfis importação/exportação/eventos aplicáveis e projeção de B faltam; componente doméstico BRL não é alcance universal.'),
    '4B.4.7': ('P', 'Catálogo temporal seleciona taxas por tributo/local/data/conhecimento e recusa parâmetro não comprovado.', 'Demais períodos/jurisdições dependem de parâmetros oficiais; completar ramos sem inventar taxa futura.'),
    '4B.4.8': ('P', 'Profissional e anexo têm qualificadores independentes da classificação; teste integrado existe.', 'Benefícios/perfis restantes precisam materialidade e incidência temporal; não generalizar redução de uma profissão.'),
    '4B.4.9': ('L', 'FinancialBaseEngine deduz despesas elegíveis; isso não comprova benefício de redução de base.', 'Nenhum contrato específico de redução legal de base identificado no módulo RTC inspecionado. Qualificar hipótese oficial e implementar prova própria.'),
    '4B.4.10': ('P', 'Qualificador financeiro e bases192/193, débitos e testes de escopo integrado estão presentes.', 'Confirmar resultado corrente do runner antes de repetir lacuna histórica; famílias específicas restantes e integração final não completas.'),
    '4B.4.11': ('P', 'Decimais exatos119, frações A150 e arredondamento financeiro por componente, inclusive meio centavo.', 'Matriz por moeda/perfil/evento ainda incompleta; divisão territorial exata não prova arredondamento monetário.'),
    '4B.4.12': ('I', 'Campos IBS estadual/municipal separados; composições A145 e territorial A150 preservam separação.', 'Faltam casos materiais municipais não zero e períodos/jurisdições qualificados; não substituir por taxa futura sintética.'),
    '4B.4.13': ('I', 'CBS preservada separadamente em cálculo, composição e débitos financeiros.', 'Prova integral de benefícios/regimes/eventos e parâmetros posteriores não concluída; efeitos de crédito pertencem a B.'),
    '4B.4.14': ('P', 'Memórias, hashes, origem por linha e replay próprios; A153 tem oráculo independente e saídas legadas preservadas.', 'Cobertura auditável por família/evento/documento restante; hashes não suprem erro de conteúdo ou ausência de cenário.'),
}


def now():
    return datetime.now(timezone.utc).isoformat()


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def refs(value):
    if isinstance(value, dict):
        for key, item in value.items():
            if key == 'path' and isinstance(item, str) and item.startswith('tests/'):
                yield item
            else:
                yield from refs(item)
    elif isinstance(value, list):
        for item in value:
            yield from refs(item)


def code_refs(value):
    if isinstance(value, dict):
        for key, item in value.items():
            if key == 'path' and isinstance(item, str) and item.startswith('app/') and item.endswith('.php'):
                yield item
            else:
                yield from code_refs(item)
    elif isinstance(value, list):
        for item in value:
            yield from code_refs(item)


def plan():
    OUT.mkdir(parents=True, exist_ok=True)
    source = read(DOC / 'requirements44-reconciliation.json')
    rows = []
    for row in source['requirements']:
        paths = set(refs(row))
        paths.update('tests/Fiscal/' + name + 'Test.php' for name in SUPPLEMENTS.get(row['id'], []))
        rows.append({'id': row['id'], 'requirement': row['requirement'], 'tests': sorted(paths)})
    paths = sorted({path for row in rows for path in row['tests']})
    assert len(rows) == 44 and len({r['id'] for r in rows}) == 44
    assert all((ROOT / path).is_file() for path in paths)
    command = [DOCKER, 'exec', CONTAINER, 'php', 'vendor/bin/pest', *paths,
               '--compact', '--log-junit', JUNIT]
    result = {'task': 'A154', 'created_at': now(), 'base': subprocess.check_output(
        ['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
        'scope': 'UNIAO_DIRIGIDA_A44_NAO_REGRESSAO_INTEGRAL',
        'requirements': rows, 'unique_test_files': len(paths),
        'test_hashes': {path: digest(ROOT / path) for path in paths}, 'command': command,
        'acceptance_rule': 'Passing tests establish only their inspected behaviors, never automatic whole-item acceptance.',
        'global_matrix_edited': False, 'B_received': False}
    preserve_json(DOC / 'task-A154-test-plan.json', result)
    preserve_json(OUT / 'test-command.json', result)
    preserve_json(OUT / 'preflight.json', {'observed_at': now(), 'base': result['base'],
        'status': subprocess.check_output(['git', 'status', '--short'], cwd=ROOT, text=True),
        'container': subprocess.check_output([DOCKER, 'inspect', '--format',
            '{{.Id}} {{.State.Status}} {{.Config.Image}}', CONTAINER], text=True).strip(),
        'processes': subprocess.check_output([DOCKER, 'top', CONTAINER], text=True),
        'source_index_sha256': digest(DOC / 'requirements44-reconciliation.json')})
    print(json.dumps({'requirements': len(rows), 'unique_test_files': len(paths)}))


def run(version=1):
    current = read(OUT / 'test-command.json')
    assert all(digest(ROOT / p) == h for p, h in current['test_hashes'].items())
    junit_path = JUNIT if version == 1 else JUNIT_V2
    command = current['command']
    if version == 2:
        command = command[:4] + ['-d', 'memory_limit=1G'] + command[4:-1] + [JUNIT_V2]
        preserve_json(OUT / 'test-command-v2.json', {'command': command,
            'reason': 'v1 terminal OOM at 128MiB; no valid JUnit. Identical test selection and hashes.',
            'test_hashes': current['test_hashes'], 'created_at': now()})
    log = OUT / f'own44-v{version}.log'
    with log.open('xb') as output:
        started = now()
        proc = subprocess.run(command, cwd=ROOT, stdout=output, stderr=subprocess.STDOUT)
    preserve_json(OUT / f'own44-v{version}-exit.json', {'started_at': started, 'finished_at': now(),
        'exit_code': proc.returncode, 'log_sha256': digest(log)})
    junit = ROOT / junit_path
    if junit.is_file():
        preserve_bytes(OUT / Path(junit_path).name, junit.read_bytes())
    print(json.dumps({'exit_code': proc.returncode, 'junit_exists': junit.is_file()}))


def collect():
    tree = ET.parse(OUT / Path(JUNIT_V2).name)
    cases = []
    for case in tree.iter('testcase'):
        issues = [{'kind': child.tag, 'message': child.attrib.get('message', ''),
                   'details': child.text or ''} for child in case if child.tag in ('failure', 'error', 'skipped')]
        cases.append({**case.attrib, 'issues': issues})
    suites = [{**s.attrib} for s in tree.iter('testsuite') if s.get('file')]
    total = tree.getroot().find('testsuite')
    assert total is not None
    assert len(cases) == int(total.attrib['tests'])
    result = {'task': 'A154', 'observed_at': now(), 'testcases': len(cases),
        'passing': sum(not c['issues'] for c in cases),
        'nonpassing': sum(bool(c['issues']) for c in cases),
        'assertions': sum(int(c.get('assertions', 0)) for c in cases),
        'cases': cases, 'file_suites': suites, 'exit': read(OUT / 'own44-v2-exit.json'),
        'junit_sha256': digest(OUT / Path(JUNIT_V2).name),
        'previous_attempt': {'exit': read(OUT / 'own44-v1-exit.json'),
            'outcome': 'ABORTED_128M_OOM_NO_VALID_JUNIT_NO_TERMINAL_COUNTS'}}
    assert result['assertions'] == int(total.attrib['assertions'])
    preserve_json(OUT / 'existing-tests-observed.json', result)
    print(json.dumps({k: result[k] for k in ('testcases', 'passing', 'nonpassing', 'assertions')}))


def review():
    plan_data = read(OUT / 'test-command.json')
    observed = read(OUT / 'existing-tests-observed.json')
    initial = {r['id']: r for r in read(DOC / 'requirements44-reconciliation.json')['requirements']}
    assert set(REVIEWS) == set(initial)
    labels = {'P': 'PARCIAL', 'I': 'IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL', 'L': 'LACUNA_REAL'}
    rows = []
    for spec in plan_data['requirements']:
        ident = spec['id']
        category, behavior, remaining = REVIEWS[ident]
        cases = [c for c in observed['cases'] if c.get('file', '').split('::')[0] in spec['tests']]
        paths = set(code_refs(initial[ident]))
        # Supplementary hashes identify real code/test links, not fiscal approval.
        for part in ('EntityDomicile151', 'PartyDomicile153', 'ServiceLocality149'):
            if any(part in p for p in spec['tests']):
                paths.add('app/Modules/Fiscal/ConsumptionReform/' + part + '.php')
        rows.append({**spec, 'classification': labels[category], 'observed_behavior': behavior,
            'remaining_proof_or_implementation': remaining,
            'functional_scope_fully_proven': False, 'formal_group_closed': False,
            'current_code_sha256': {p: digest(ROOT / p) for p in sorted(paths)},
            'related_testcases_not_whole_item_acceptance': len(cases),
            'related_nonpassing_cases': [c for c in cases if c['issues']],
            'test_execution_missing': sorted(set(spec['tests']) - {c.get('file', '').split('::')[0] for c in cases}),
            'historical_gap_is_not_automatically_current_defect': True})
    result = {'task': 'A154', 'base': plan_data['base'], 'observed_at': now(),
        'scope': plan_data['scope'], 'requirements': rows,
        'suite': {k: v for k, v in observed.items() if k != 'cases'},
        'classification_counts': {label: sum(r['classification'] == label for r in rows) for label in labels.values()},
        'method': 'Explicit review per ID; classifications not derived from volume of tests or historical labels. Whole-item proof remains incomplete for the stated reasons.',
        'B_received': False, 'global_console_edited': False, 'global_matrix_edited': False,
        'production_ready': False, 'phase4b_complete': False,
        'priority': ['4B.4.9: qualify and implement actual base reduction',
                     '4B.3.7/4B.3.8: collection option and debt settlement lifecycle',
                     '4B.2.10/4B.2.11: business natural person establishment qualification'],
        'counts_overlap_across_requirements': True}
    preserve_json(DOC / 'acceptance44-A154.json', result)
    print(json.dumps(result['classification_counts']))


if __name__ == '__main__':
    {'plan': plan, 'run': run, 'run-v2': lambda: run(2), 'collect': collect, 'review': review}[sys.argv[1]]()
