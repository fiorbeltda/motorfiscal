"""A155 evidence capture and reproducible H1-H5 delivery. Never edits B resources."""
import hashlib
import json
import os
import subprocess
import sys
import urllib.request
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path
from xml.etree import ElementTree
import zipfile

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
DOC = ROOT / 'docs/fiscal/agent-a/20261104'
OUT = Path('D:/ProjetoSaasExports/agent-a-A155-H1-H5-20261104-v1')
DOCKER = 'D:/Docker/DockerDesktop/resources/bin/docker.exe'
CONTAINER = 'motorfiscal-agente-a-20261103'
MODULE = 'app/Modules/Fiscal/ConsumptionReform/'
FINAL_REGRESSION = 'regression-v3'
FINAL_COMPATIBILITY = 'A-compatibility-v2'
PRODUCTION = [MODULE + n + '.php' for n in ('ConsumptionReformEngine', 'TemporalRateCatalog',
              'CreditIntegration155', 'PeriodCreditAssessment155')] + ['app/Console/Commands/AssessPeriodCredit155Command.php']
B_COMPONENTS = [MODULE + n + '.php' for n in ('CreditDecisionEngine', 'CreditZeroSemanticsPolicyB02',
    'AnnexStandardQualificationPolicyB07', 'CreditAdjustmentCycleB11', 'AcquirerCreditProhibitionCatalogB13',
    'CreditUtilizationPlannerB14', 'PresumedCreditCatalogB17')]
REGRESSION = [
    'Unit/CreditZeroSemanticsB02', 'Unit/CreditZeroSemanticsPolicyB02',
    'Unit/ConsumptionReformEngine', 'Unit/InputCreditTreatment', 'Unit/ImportCreditQualification',
    'Unit/CreditInput122', 'Unit/CreditInputPolicy122', 'Integration/CreditInput122Integration',
    'Integration/InputCreditIntegrated', 'Integration/Credit122DocumentPrices',
    'Integration/MaterialInput119', 'Unit/AcquiredPersonalUse',
    'Unit/CreditAdjustmentCycleB11', 'Unit/AcquirerCreditProhibitionCatalogB13',
    'Unit/CreditUtilizationPlannerB14', 'Unit/PresumedCreditCatalogB17', 'Unit/CreditLifecycleB18',
    'Unit/CreditInvariantsB09', 'Unit/AnnexStandardQualificationPolicyB07',
    'Unit/SyntheticRateAcceptanceB19', 'Integration/AnnexServiceIntegrated',
    'Integration/ImportResponsibility129Integration', 'Integration/ImportDocumentMoney141',
    'Integration/ImportDpsModel143', 'Integration/PartialExportDpsPair135',
    'Integration/IssTransitionIntegration', 'Integration/PostIssIntegration',
    'Unit/FinancialTaxEngine', 'Integration/PublicPayment139',
    'Integration/HandoffIntegration155', 'Unit/PeriodCreditAssessment155',
]


def now():
    return datetime.now(timezone.utc).isoformat()


def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)


def read(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))


def sources():
    url = 'https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html'
    with urllib.request.urlopen(url, timeout=45) as response:
        raw = response.read()
        headers = dict(response.headers)
    preserve_bytes(OUT / 'sources/lc214-camara-20261104.html', raw)
    digest = hashlib.sha256(raw).hexdigest()
    assert digest == 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde', 'Official text changed; review before proceeding.'
    preserve_json(OUT / 'sources/sources.json', {'url': url, 'observed_at': now(),
        'sha256': digest, 'bytes': len(raw), 'headers': headers,
        'planalto_web_observation': 'Access denied via web tool; unchanged preserved B snapshot retained, not replaced.',
        'B_planalto_sha256': sha(ROOT / 'resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm'),
        'scope': 'Revalidate primary LC214 wording for existing B contracts; no new tax rate or benefit invented.'})
    print(json.dumps({'source_sha256': digest, 'bytes': len(raw)}))


def regression(version=1):
    paths = ['tests/Fiscal/' + n + 'Test.php' for n in REGRESSION]
    assert len(set(paths)) == len(paths) and all((ROOT / p).is_file() for p in paths)
    command = [DOCKER, 'exec', CONTAINER, 'php', '-d', 'memory_limit=1G', 'vendor/bin/pest',
        *paths, '--compact', '--log-junit', f'storage/app/agent-a-A155-regression-v{version}.junit.xml']
    preserve_json(OUT / f'regression-v{version}-command.json', {'command': command,
        'started_at': now(), 'base': git('rev-parse', 'HEAD').decode().strip(),
        'source_hashes': {p: sha(ROOT / p) for p in PRODUCTION + paths + B_COMPONENTS},
        'scope': 'DIRECTED_H1_H5_INTEGRATION_NOT_FINAL_FULL_REGRESSION'})
    with (OUT / f'regression-v{version}.log').open('xb') as output:
        process = subprocess.run(command, cwd=ROOT, stdout=output, stderr=subprocess.STDOUT)
    preserve_json(OUT / f'regression-v{version}-exit.json', {'exit_code': process.returncode,
        'finished_at': now(), 'log_sha256': sha(OUT / f'regression-v{version}.log')})
    print(json.dumps({'exit_code': process.returncode}))


def compatibility(version=1):
    names = ['Unit/ConsumptionReformEngine', 'Unit/TaxTemporalResolver', 'Unit/PlatformResponsibilityEngine',
        'Unit/GovernmentPurchaseQualifier', 'Unit/MaterialInput119Contract', 'Unit/FinancialBaseEngine',
        'Unit/IssTransitionRateEngine', 'Unit/TransitionDeploymentLifecycle114', 'Unit/TaxPointLifecycle147',
        'Integration/PostIssIntegration', 'Integration/CombinedSupplyAssessment145',
        'Integration/PrincipalAccessoryAssessment146', 'Integration/TaxPointLifecycle147Integration',
        'Integration/CollectionEvidence148', 'Integration/ServiceLocality149', 'Integration/TerritorialLocality150',
        'Integration/EntityDomicile151', 'Integration/CentralizedDomicile152', 'Integration/PartyDomicile153']
    paths = ['tests/Fiscal/' + n + 'Test.php' for n in names]
    command = [DOCKER, 'exec', CONTAINER, 'php', '-d', 'memory_limit=1G', 'vendor/bin/pest', *paths,
        '--compact', '--do-not-cache-result', '--log-junit', f'storage/app/agent-a-A155-A-compatibility-v{version}.junit.xml']
    preserve_json(OUT / f'A-compatibility-v{version}-command.json', {'command': command, 'started_at': now(),
        'source_hashes': {p: sha(ROOT / p) for p in PRODUCTION + paths},
        'scope': 'A153 accumulated directed selection on CURRENT A155 source; not historical receipt replacement or full regression.'})
    with (OUT / f'A-compatibility-v{version}.log').open('xb') as stream:
        result = subprocess.run(command, cwd=ROOT, stdout=stream, stderr=subprocess.STDOUT)
    preserve_json(OUT / f'A-compatibility-v{version}-exit.json', {'exit_code': result.returncode, 'finished_at': now(),
        'log_sha256': sha(OUT / f'A-compatibility-v{version}.log')})
    print(json.dumps({'exit_code': result.returncode}))


def junit(name):
    path = ROOT / ('storage/app/agent-a-A155-' + name + '.junit.xml')
    tree = ElementTree.parse(path)
    total = tree.getroot().find('testsuite')
    assert total is not None
    preserve_bytes(OUT / path.name, path.read_bytes())
    return {'path': path.relative_to(ROOT).as_posix(), 'sha256': sha(path), **total.attrib}


def summarize():
    result = {name: junit(name) for name in ('smoke-v1', 'directed-v1', 'regression-v1')}
    preserve_json(OUT / 'suites-observed.json', result)
    print(json.dumps(result, ensure_ascii=False))


def native():
    code = "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); require 'scripts/agent_a/handoff155_fixture.php'; echo json_encode(handoff155Period(), JSON_THROW_ON_ERROR);"
    captured = subprocess.run([DOCKER, 'exec', CONTAINER, 'php', '-r', code], cwd=ROOT,
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    base = json.loads(captured.stdout)
    records = []
    for name, previous in [('unused', '0.00'), ('part-used', '10.00'), ('fully-used', '80.00')]:
        data = json.loads(json.dumps(base))
        data['credits'][0]['previously_utilized_amount'] = previous
        logical = f'storage/app/agent-a-A155/{name}.json'
        preserve_json(ROOT / logical, data)
        command = [DOCKER, 'exec', CONTAINER, 'php', 'artisan', 'fiscal:rtc:assess-period-credit', logical]
        runs = [subprocess.run(command, cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE) for _ in range(2)]
        assert all(p.returncode == 0 for p in runs), [p.stderr for p in runs]
        assert runs[0].stdout == runs[1].stdout
        preserve_bytes(OUT / f'cli/{name}-input.json', (ROOT / logical).read_bytes())
        preserve_bytes(OUT / f'cli/{name}-output.json', runs[0].stdout)
        output = json.loads(runs[0].stdout)
        assert output['replay_matches'] is True
        report = output['report']
        assert report['debts_extinguished'] is False and report['ledger_posted'] is False
        for tax in ('IBS', 'CBS'):
            credit = next(c for c in data['credits'] if c['tax'] == tax)
            balance = next(c for c in report['adjustment']['balances'] if c['tax'] == tax)
            used = sum((Decimal(c['amount']) for c in report['utilization']['compensations'] if c['tax'] == tax), Decimal(0))
            remaining = sum((Decimal(c['remaining']) for c in report['utilization']['remaining_credits'] if c['tax'] == tax), Decimal(0))
            expected_available = Decimal(credit['amount']) - Decimal(balance['adjusted']) - Decimal(credit['previously_utilized_amount'])
            assert used + remaining == expected_available
            debt = next(d for d in data['debts'] if d['tax'] == tax)
            assert used == min(expected_available, Decimal(debt['amount']))
        records.append({'case': name, 'command': command, 'exit_code': 0, 'byte_identical_repeated_cli': True,
            'output_sha256': hashlib.sha256(runs[0].stdout).hexdigest(), 'independent_decimal_conservation_per_tax': True})
    invalid = dict(base, ledger_complete=False)
    logical = 'storage/app/agent-a-A155/incomplete.json'
    preserve_json(ROOT / logical, invalid)
    command = [DOCKER, 'exec', CONTAINER, 'php', 'artisan', 'fiscal:rtc:assess-period-credit', logical]
    result = subprocess.run(command, cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    output = json.loads(result.stdout)
    assert result.returncode == 1 and output['report']['reason'] == 'RTC_PERIOD_CREDIT_COMPLETE_LEDGER_REQUIRED'
    preserve_bytes(OUT / 'cli/incomplete-output.json', result.stdout)
    preserve_json(OUT / 'native-oracles.json', {'cases': records, 'refusal': {'command': command, 'exit_code': result.returncode,
        'reason': output['report']['reason']}, 'synthetic_input_not_external_authentication': True})
    print(json.dumps({'independent_decimal_cases': len(records), 'expected_refusal_exit': result.returncode}))


def paths_in(value, prefix):
    if isinstance(value, dict):
        for key, child in value.items():
            if key == 'path' and isinstance(child, str) and child.startswith(prefix):
                yield child
            else:
                yield from paths_in(child, prefix)
    elif isinstance(value, list):
        for child in value:
            yield from paths_in(child, prefix)


def deliver():
    # Read the paused review's explicit notes, without executing or resuming A154.
    from audit154 import REVIEWS
    assert git('rev-parse', 'HEAD').decode().strip() == 'e3a632bb186eec18682af5c8898c10e8610f3808'
    regression_exit = read(OUT / f'{FINAL_REGRESSION}-exit.json')
    assert regression_exit['exit_code'] == 0
    regression_plan = read(OUT / f'{FINAL_REGRESSION}-command.json')
    assert all(sha(ROOT / p) == digest for p, digest in regression_plan['source_hashes'].items())
    for path in B_COMPONENTS:
        assert (ROOT / path).read_bytes() == git('show', 'HEAD:' + path), path
    for name in ('phpstan-v3', 'pint-v4', 'console-v1'):
        assert read(OUT / (name + '-exit.json'))['exit_code'] == 0
    suites = {name: junit(name) for name in ('smoke-v1', 'directed-v1', 'regression-v1', 'regression-v2', FINAL_REGRESSION,
        'B-contract-status-v1', 'A-compatibility-v1', FINAL_COMPATIBILITY)}
    assert read(OUT / f'{FINAL_COMPATIBILITY}-exit.json')['exit_code'] == 0
    assert all(sha(ROOT / p) == digest for p, digest in read(OUT / f'{FINAL_COMPATIBILITY}-command.json')['source_hashes'].items())
    assert all(int(suites[FINAL_REGRESSION].get(k, 0)) == 0 for k in ('errors', 'failures', 'skipped'))
    b_tree = ElementTree.parse(ROOT / 'storage/app/agent-a-A155-B-contract-status-v1.junit.xml')
    b_failures = [{'name': c.attrib['name'], 'details': ''.join(x.text or '' for x in c if x.tag in ('failure', 'error'))}
        for c in b_tree.iter('testcase') if any(x.tag in ('failure', 'error') for x in c)]
    assert len(b_failures) == 1 and 'characterizes the synthetic-parameter gap' in b_failures[0]['name']
    preserve_json(OUT / 'B-characterization-change.json', {'failures': b_failures,
        'interpretation': 'Expected obsolete B16 characterization: synthetic=true now NOT_FOUND. B owns updating that test. B19 acceptance is green in the regression.',
        'B07_pending': int(suites['B-contract-status-v1'].get('skipped', 0)),
        'A_executes_320_cases_in_each_runner': True})
    initial = read(ROOT / 'docs/fiscal/agent-a/20261103/requirements44-reconciliation.json')
    labels = {'P': 'PARCIAL', 'I': 'IMPLEMENTADO_SEM_EVIDENCIA_INTEGRAL', 'L': 'LACUNA_REAL'}
    fresh_paths = set()
    for name in (FINAL_REGRESSION, FINAL_COMPATIBILITY):
        tree = ElementTree.parse(ROOT / f'storage/app/agent-a-A155-{name}.junit.xml')
        fresh_paths.update(c.get('file', '').split('::')[0] for c in tree.iter('testcase') if c.get('file'))
    rows = []
    for old in initial['requirements']:
        category, behavior, remaining = REVIEWS[old['id']]
        touched = old['id'] in ['4B.1.2', '4B.1.6', '4B.1.7', '4B.1.8', '4B.4.7', '4B.4.8']
        code = sorted(set(paths_in(old, 'app/')))
        tests = sorted(set(paths_in(old, 'tests/')))
        receipts = sorted(set(p for p in paths_in(old, 'docs/') if '/validation-A' in p))
        if old['id'] == '4B.2.11':
            code = sorted(set(code + [MODULE + 'EntityDomicile151.php', MODULE + 'PartyDomicile153.php']))
            tests = sorted(set(tests + ['tests/Fiscal/Integration/' + n + 'Test.php' for n in
                ('EntityDomicile151', 'CentralizedDomicile152', 'PartyDomicile153')]))
            receipts = sorted(set(receipts + [f'docs/fiscal/agent-a/20261103/validation-A{n}.json' for n in (151, 152, 153)]))
            remaining = 'H5 inclui os vínculos A151–A153 ausentes neste ID do índice A144. Restam PF empresarial e integração; A154 continua pausada.'
        rows.append({'id': old['id'], 'requirement': old['requirement'], 'classification': labels[category],
            'observed_scope': behavior, 'remaining': remaining,
            'code_current_sha256': {p: sha(ROOT / p) for p in code},
            'existing_test_paths': tests,
            'freshly_executed_current_test_paths_not_whole_item_acceptance': sorted(set(tests) & fresh_paths),
            'not_executed_in_A155': sorted(set(tests) - fresh_paths),
            'historical_scoped_receipts': {p: sha(ROOT / p) for p in receipts},
            'A155_touches_this_A_requirement': touched,
            'A155_evidence': ['tests/Fiscal/Integration/HandoffIntegration155Test.php', FINAL_REGRESSION] if touched else [],
            'whole_item_accepted': False, 'formal_group_closed': False})
    assert len(rows) == 44 and len({r['id'] for r in rows}) == 44
    preserve_json(DOC / 'requirements44-handoff-H5.json', {'task': 'A155/H5', 'observed_at': now(),
        'basis': 'A144-A153 published scoped evidence plus explicit requirement review; A154 remains paused and has no accepted suite.',
        'requirements': rows, 'groups': {'4B.1': 8, '4B.2': 12, '4B.3': 10, '4B.4': 14},
        'B_may_consolidate_observed_partial_evidence': True, 'phase4b_closed': False})
    baseline_hashes = {p: hashlib.sha256(git('show', 'HEAD:' + p)).hexdigest() for p in PRODUCTION[:2]}
    receipt = {'task': 'A155', 'observed_at': now(), 'request': 'Complete H1-H5 while main development stays paused',
        'remote': git('remote', 'get-url', 'origin').decode().strip(), 'branch': git('branch', '--show-current').decode().strip(),
        'base': git('rev-parse', 'HEAD').decode().strip(), 'clone': str(ROOT),
        'published_A144_A153': '6c0f81bffb9923f36d00dcfb9eec5d0fd138bd9f',
        'central_files_before_sha256': baseline_hashes, 'current_production_sha256': {p: sha(ROOT / p) for p in PRODUCTION},
        'B_components_unchanged': {p: sha(ROOT / p) for p in B_COMPONENTS},
        'handoffs': {'H1': 'IMPLEMENTED_AND_LOCALLY_VERIFIED_B02_GATED_BOTH_CREDIT_BRANCHES',
            'H2': 'IMPLEMENTED_AND_LOCALLY_VERIFIED_320_CASES_EACH_RUNNER',
            'H3': 'IMPLEMENTED_B13_B17_OPERATION_FLOW_AND_B11_B14_PERIOD_ENTRYPOINT',
            'H4': 'IMPLEMENTED_SYNTHETIC_MARKERS_REJECTED_B19_GREEN', 'H5': 'LOCAL_REPORT_PATCH_AND_SOURCE_PACKAGE_DELIVERED'},
        'activation': 'B must create/validate NEW catalog and bundle versions with gates/facts/questions; no installed accepted resource edited by A.',
        'suites_separately_reported_not_summed': suites,
        'suite_note': 'smoke-v1 CLI test was misplaced in Unit; moved to Laravel Integration. directed-v1 preceded the prior-utilization guard. regression-v1 had four ordering assertions corrected to select by credit_id; v2 is green; v3 additionally preserves the B13 signed result and accepts meaningful B07 reference spaces. Final source and selection: regression-v3.',
        'quality': {'phpstan': 'GLOBAL_LEVEL8_NO_ERRORS', 'pint': '8_PHP_FILES_PASS', 'console': '28_CHECKS_PASS'},
        'native_decimal_oracles': read(OUT / 'native-oracles.json'),
        'official_source': read(OUT / 'sources/sources.json'),
        'B_characterization_change': 'B-characterization-change.json',
        'A44_report': 'docs/fiscal/agent-a/20261104/requirements44-handoff-H5.json',
        'console_proposal_for_B': {'applied': False, 'verified_after_application': False,
            'summary': 'A155 implements H1-H4 and delivers H5; regular A154 development paused.',
            'deliveries': ['B02 and Q-B06-1 caller integration', 'B13/B17 operation flow, B11/B14 period entrypoint and synthetic-rate rejection',
                '44-item evidence report, source patch and package'],
            'pending': ['B creates and validates new gated catalog/bundle versions',
                'B updates B16 characterization, scopes B02/B07 no-gate assertions to historical catalogs and consolidates matrix/console',
                'Remaining A44 scope and final full integrated regression remain open'],
            'A_group_states': {f'4B.{n}': 'PARTIAL' for n in range(1, 5)},
            'B_group_states': 'CONSOLIDATE_BY_B_FROM_ACTUAL_INTEGRATION_EVIDENCE', 'production_ready': False},
        'A154_state': 'PAUSED_USER_REQUEST_EXIT130_NO_ACCEPTANCE',
        'git_publication': 'PRECOMMIT_CUT_LOCAL_COMMIT_AUTHORIZED_AFTER_VALIDATION_NO_PUSH_AUTHORIZATION', 'B_received': False,
        'global_matrix_edited': False, 'global_console_edited': False, 'phase4b_complete': False, 'production_ready': False,
        'known_limits': ['A44 groups remain partial; this is not final full integrated regression.',
            'B07 installed-bundle cases remain pending until B activation; 640 own in-memory test cases exercise the implementation.',
            'B16 old synthetic acceptance characterization must be updated by B; its failure is preserved.',
            'B17 qualification remains distinct from appropriation; synthetic parameter stays nonofficial.',
            'Period assessment consumes a complete supplied ledger; it never posts, refunds, extinguishes or authenticates government records.']}
    preserve_json(DOC / 'handoffs-H1-H5-receipt.json', receipt)
    report = f'''# A155 — H1–H5 para o Agente B (04/11/2026)

Base `{receipt['base']}`, branch `{receipt['branch']}`, clone `{ROOT}`.
A144–A153 já publicados em `6c0f81b`; B74a4195 e pacotee3a632b recebidos por fast-forward local.
Desenvolvimento A154 pausado; ponto de retomada em `paused-A154.md`. Usuário autorizou commit local ao final;
o SHA será registrado no recibo externo `commit-receipt.json`, sem SHA circular dentro deste conteúdo. Sem push autorizado.

| Handoff | Entrega local e prova |
|---|---|
| H1 | B02 chamado nos ramos ordinário/importação pelo adaptador A155, gate exato; status/valores sem gate preservados. |
| H2 | Tabela Q-B06-1 implementada, {640} casos (320 × 2 runners) executados em captura de teste em memória. Sem editar catálogos aceitos. |
| H3 | B13 antes da decisão de crédito; B17 na decisão/trace, sem somar nem apropriar; CLI de apuração encadeia B11→B14 e desconta utilizações anteriores. |
| H4 | `source_kind` não oficial, `synthetic=true` e marcadores malformados recusados; B19 aprovado. Saída legada sem marcadores mantida. |
| H5 | Este relatório, recibo JSON, 44 itens individuais, patch completo e fontes novas empacotadas. |

Regressão dirigida final: **{suites[FINAL_REGRESSION]['tests']} testes / {suites[FINAL_REGRESSION]['assertions']} asserções**, sem falhas/erros/skips.
Inclui os testes próprios e as suítes H1 especificadas, componentes B11/B13/B14/B17/B18, B19, anexos, importação129/141/143,
par135, pagamento139, financeiro e ISS. O comando e hashes estão em `{FINAL_REGRESSION}-command.json` no pacote externo.
Não somar às suítes sobrepostas: dirigido anterior701/1447; smoke anterior46 aprovados e1 falha de contexto de teste corrigida.
A regressão v1 preserva quatro falhas de asserção por posição da lista corrigidas para buscar credit_id; v2 passou e v3 é o corte final.
V3 conserva o objeto B13 assinado sem acrescentar campos e preserva referências B07 STRING com espaços internos.
Compatibilidade A145–A153 no código atual: {suites[FINAL_COMPATIBILITY]['tests']} testes / {suites[FINAL_COMPATIBILITY]['assertions']} asserções, sem substituir recibos históricos.
PHPStan global nível8 sem erros; Pint8 aprovado. Três oráculos Python Decimal, CLI repetida byte-idêntica e recusaexit1.
Console:28 verificações aprovadas; sua consolidação continua de B, não foi atualizada por A.

**B precisa continuar:** criar novas versões de catálogo/bundle/fatos/perguntas e ativar os blocos exatos B02/B07/A155
(constantes no código; contrato em `task-A155-H1-H5-manifest.md`). Os 320 casos B07 originais permanecem pendentes
sem bundle instalado; a implementação foi exercitada pelos640 casos próprios. O teste de caracterização B16
que esperava aceitar `synthetic=true` agora falha intencionalmente e deve ser atualizado por B. Evidência preservada
em `B-characterization-change.json`; nenhum teste B foi alterado por A.
Ao criar as versões novas, B também deve restringir ao acervo histórico as asserções B02/B07 de ausência de gates;
elas passam no estado atual, mas não devem proibir a nova versão explicitamente habilitada.

Os44 requisitos de A estão detalhados em `requirements44-handoff-H5.json`, com classificação, código/hash,
testes, recibos históricos e lacunas. Isso permite consolidar avanços parciais; não equivale a fechar os44 itens.
H1–H4 removem as dependências de implementação do A descritas nos handoffs, com ativação/integração de B ainda pendente.

Interfaces: H1 adiciona `zero_semantics_version`, `zero_causes`, `amount_semantics` por componente quando habilitado.
H2 usa os3 fatos novos B07 e registra `RTC_ANNEX_NONCORRESPONDENCE_QUALIFIED`. H3 usa os fatos do manifesto,
`credit_integration155` na decisão/trace e `RTC-PERIOD-CREDIT-ASSESSMENT-2026.11.04-A155` na apuração separada.
Valores IBS/CBS ordinários e presumidos permanecem separados. Ledger completo inclui ajustes e utilizações anteriores;
preservam-se datas de apropriação, restrições de ressarcimento e segregação de tributos. Não há posting/extinção/autenticação.

Fontes oficiais revalidadas: Câmara LC214, SHA`{receipt['official_source']['sha256']}`; bytes iguais ao snapshot anterior.
Planalto pela ferramenta web recusou acesso; snapshot bruto publicado por B foi preservado e seus testes de trechos executados.
Não houve taxa futura, parâmetro oficial, cadastro ou benefício inventado.

Pacote externo: `{OUT}`. Patch: `A155-H1-H5.patch`, basee3a632b; arquivos novos incluídos.
Antes de aplicar, B confere seu HEAD/diff e executa `git apply --check A155-H1-H5.patch`; não sobrepor alterações concorrentes.
Arquivos B, factory, definições globais, XML, banco e infraestrutura não foram editados por A.
O recibo contém hashes centrais antes/depois. `source-package-final/manifest.json` inventaria as cópias.
Recebimento por Claude/B e aplicação na matriz/console ainda não comprovados; usuário é a ponte.
'''
    preserve_bytes(DOC / 'handoffs-H1-H5-results.md', report.encode('utf-8'))
    handoff = ROOT / 'docs/fiscal/agent-a/20261103/handoff-B.md'
    addition = '\n\n## A155 — resposta aos H1–H5 (04/11/2026)\n\nImplementação e validação local concluídas; ver `../20261104/handoffs-H1-H5-results.md`, recibo e relatório44 na mesma pasta. Pacote/patch no D: conforme relatório. A154 permanece pausada. B precisa criar novas versões de catálogos/bundles e atualizar a caracterização B16. Commit local autorizado ao final; SHA no recibo externo, sem push implícito. Nenhum arquivo de B editado por A e recebimento por B não presumido.\n'
    assert '## A155 — resposta aos H1–H5' not in handoff.read_text(encoding='utf-8-sig')
    with handoff.open('ab') as stream:
        stream.write(addition.encode('utf-8'))
    files = PRODUCTION + ['scripts/agent_a/handoff155_fixture.php', 'scripts/agent_a/integrate155.py',
        'tests/Fiscal/Integration/HandoffIntegration155Test.php', 'tests/Fiscal/Unit/PeriodCreditAssessment155Test.php',
        'docs/fiscal/agent-a/20261103/handoff-B.md'] + ['docs/fiscal/agent-a/20261104/' + name for name in (
            'task-A155-H1-H5-manifest.md', 'paused-A154.md', 'handoffs-H1-H5-results.md',
            'handoffs-H1-H5-receipt.json', 'requirements44-handoff-H5.json')]
    assert len(files) == len(set(files))
    before_index = git('diff', '--cached', '--binary')
    env = dict(os.environ, GIT_INDEX_FILE=str(OUT / 'patch-index'))
    subprocess.run(['git', 'read-tree', 'HEAD'], cwd=ROOT, env=env, check=True, stdout=subprocess.PIPE)
    subprocess.run(['git', 'add', '--', *files], cwd=ROOT, env=env, check=True, stdout=subprocess.PIPE)
    patch = subprocess.check_output(['git', 'diff', '--cached', '--binary', 'HEAD', '--', *files], cwd=ROOT, env=env)
    preserve_bytes(OUT / 'A155-H1-H5.patch', patch)
    preserve_bytes(OUT / 'diff-stat.txt', subprocess.check_output(['git', 'diff', '--cached', '--stat', 'HEAD', '--', *files], cwd=ROOT, env=env))
    assert git('diff', '--cached', '--binary') == before_index
    skeleton = OUT / 'patch-check-base'
    skeleton.mkdir(exist_ok=False)
    for path in files:
        previous = subprocess.run(['git', 'show', 'HEAD:' + path], cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if previous.returncode == 0:
            preserve_bytes(skeleton / path, previous.stdout)
    check = subprocess.run(['git', 'apply', '--check', str(OUT / 'A155-H1-H5.patch')],
        cwd=skeleton, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    preserve_json(OUT / 'patch-check.json', {'base': receipt['base'], 'exit_code': check.returncode,
        'stdout': check.stdout.decode('utf-8', errors='replace'), 'stderr': check.stderr.decode('utf-8', errors='replace'),
        'source': 'Exact HEAD bytes for every preexisting path; new paths absent; check only, no application.'})
    assert check.returncode == 0, check.stderr
    manifest = []
    for path in files:
        raw = (ROOT / path).read_bytes()
        preserve_bytes(OUT / 'source-package-final' / path, raw)
        manifest.append({'path': path, 'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw)})
    preserve_json(OUT / 'source-package-final/manifest.json', {'base': receipt['base'], 'files': manifest,
        'patch_sha256': hashlib.sha256(patch).hexdigest(), 'main_git_index_unchanged': True})
    preserve_bytes(OUT / 'README.md', report.encode('utf-8'))
    preserve_json(OUT / 'delivery-manifest.json', {'files': manifest, 'patch_sha256': hashlib.sha256(patch).hexdigest(),
        'status': git('status', '--short', '--untracked-files=all').decode(),
        'original_C_checkout_status': subprocess.check_output(['git', 'status', '--short'], cwd='C:/xampp/htdocs/projeto-saas-motor', text=True),
        'B_received': False, 'new_publication': False})
    archive = OUT / 'A155-motorfiscal-H1-H5.zip'
    members = [p for p in OUT.rglob('*') if p.is_file() and p.name not in ('patch-index', archive.name)]
    with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED) as package:
        for path in sorted(members):
            package.write(path, path.relative_to(OUT).as_posix())
    with zipfile.ZipFile(archive) as package:
        assert package.testzip() is None
    preserve_json(OUT / 'zip-receipt.json', {'path': str(archive), 'sha256': sha(archive), 'members': len(members), 'crc_verified': True})
    print(json.dumps({'source_files': len(files), 'zip': str(archive), 'zip_sha256': sha(archive),
        'regression_tests': suites[FINAL_REGRESSION]['tests'], 'regression_assertions': suites[FINAL_REGRESSION]['assertions']}))


if __name__ == '__main__':
    {'sources': sources, 'regression': regression, 'regression-v2': lambda: regression(2), 'regression-v3': lambda: regression(3),
        'compatibility': compatibility, 'compatibility-v2': lambda: compatibility(2),
        'summarize': summarize, 'native': native, 'deliver': deliver}[sys.argv[1]]()
