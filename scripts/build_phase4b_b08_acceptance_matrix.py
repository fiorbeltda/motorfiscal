"""B08: matriz de aceite individual dos 36 requisitos da frente B (4B.5-4B.8).

Somente leitura da matriz consolidada B06. Para cada item: criterio de aceite verificavel,
evidencias historicas e dos cortes B01-B07, classificacao, lacuna, dependencia e proxima acao.
Nao aprova item nem altera codigo fiscal. Uso:
python -I -X utf8 scripts/build_phase4b_b08_acceptance_matrix.py <out.json> <out.md>
"""
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MATRIX = ROOT / "storage/app/phase4b-b06-development-20261103/requirements80-current-matrix.json"
MATRIX_SHA = "01b8f515dfe237f5910e2f17ed9db44612e5084d697601845b55cc2f06c6b8c1"
if hashlib.sha256(MATRIX.read_bytes()).hexdigest() != MATRIX_SHA:
    raise SystemExit("Matriz B06 divergente.")

# Criterio de aceite (escopo funcional Sandbox) e dependencia principal por item.
CRITERIA = {
    "4B.5.1": ("Direito a credito decidido por perfil (regular, Simples, uso pessoal, importacao, presumidos/especificos) com documento vinculado e fundamento legal por perfil; positivos e negativos por familia.", "FONTE_E_IMPLEMENTACAO_B"),
    "4B.5.2": ("Apropriacao ponta a ponta: elegibilidade -> registro -> consumo do credito no periodo, com proveniencia e condicoes do art.47/48 dependentes de implantacao separadas.", "FONTE_OFICIAL_IMPLANTACAO"),
    "4B.5.3": ("Condicionantes (documento valido, extincao do debito anterior, tratamento do insumo) com causa explicita por resultado e perguntas de correcao.", "INTEGRACAO_A_B02"),
    "4B.5.4": ("Estorno com evento causal/documental, limite ao disponivel e ciclo (registro, reversao) por contexto exigido.", "IMPLEMENTACAO_B"),
    "4B.5.5": ("Glosa com evidencia, ciclo material e integracao a decisao/memoria; distinta de estorno.", "IMPLEMENTACAO_B"),
    "4B.5.6": ("Credito em importacao com documento definitivo, prova do pagamento/extincao e vinculo DI/DUIMP-documento fiscal.", "DADO_E_FONTE_EXTERNA"),
    "4B.5.7": ("Operacoes sem credito: todas as exclusoes legais com fonte e testes; NOT_ASSESSED distinto de DENIED.", "FONTE_E_IMPLEMENTACAO_B"),
    "4B.5.8": ("Beneficios que preservam ou anulam credito (imunidade, isencao, aliquota zero, diferimento, suspensao, reducoes) com fonte e livros.", "FONTE_E_IMPLEMENTACAO_B"),
    "4B.5.9": ("Vinculo documento fiscal <-> credito em todos os perfis exigidos, com recusa de documento em branco/divergente.", "IMPLEMENTACAO_B"),
    "4B.5.10": ("Todo resultado zero tem causa e semantica distintas em todas as rotas e periodos ativos.", "INTEGRACAO_A_B02"),
    "4B.6.1": ("Catalogos materializados e versionados com fonte/hash e vigencia por ambiente oficial qualificada.", "FONTE_OFICIAL_VIGENCIA"),
    "4B.6.2": ("cIndOp com edicoes versionadas, selecao por ambiente e conciliacao com Anexo C.", "FONTE_OFICIAL_VIGENCIA"),
    "4B.6.3": ("cClassTrib oficial versionado, com selecao aplicavel por perfil sem autoridade automatica.", "IMPLEMENTACAO_B"),
    "4B.6.4": ("NBS oficial materializada com aplicabilidade temporal e classificacao de negocio por familia.", "IMPLEMENTACAO_B"),
    "4B.6.5": ("Correlacao oficial preservada por edicao (1.01.00, 1.02.00) sem autoridade decisoria.", "NENHUMA_ALEM_DE_VIGENCIA"),
    "4B.6.6": ("Toda ENGINE_DECISION_RULE com fundamento verificavel; controversias decididas e implementadas.", "INTEGRACAO_A_B07"),
    "4B.6.7": ("Separacao estrutural OFFICIAL_CORRELATION x ENGINE_DECISION_RULE mantida em todas as versoes e mutacoes.", "NENHUMA"),
    "4B.6.8": ("Toda correlacao com kind OFFICIAL_CORRELATION e decision_authority=false, em todas as edicoes.", "NENHUMA"),
    "4B.6.9": ("Toda regra do motor com kind ENGINE_DECISION_RULE e validacao por perfil.", "INTEGRACAO_A_B07"),
    "4B.6.10": ("Artefato oficial atual do cClassTrib preservado com hash, versao e vigencia; revalidacao periodica.", "REVALIDACAO_PERIODICA"),
    "4B.7.1": ("Projecao DPS/NFS-e com campos IBS/CBS para todos os perfis exigidos, valida no XSD oficial vigente, sem assinar/transmitir.", "XSD_OFICIAL_NT009_NAO_PUBLICADO"),
    "4B.7.2": ("Uso da documentacao tecnica oficial vigente, com revalidacao de bytes das fontes.", "REVALIDACAO_PERIODICA"),
    "4B.7.3": ("Manual municipal consultado e preservado (SHA-256).", "NENHUMA"),
    "4B.7.4": ("Toda mudanca de schema versionada com hash e fonte; schema oficial nunca adulterado.", "XSD_OFICIAL_NT009_NAO_PUBLICADO"),
    "4B.8.1": ("Todos os requisitos funcionais da Fase 4B com aceite individual comprovado.", "INTEGRACAO_A_B_E_REGRESSAO"),
    "4B.8.2": ("Bundle proprio versionado carregado nativamente.", "NENHUMA"),
    "4B.8.3": ("Facts e perguntas proprios cobrindo os perfis exigidos, incluindo os fatos B07.", "INTEGRACAO_A_B07"),
    "4B.8.4": ("Catalogos versionados com hash de conteudo vinculado ao manifesto.", "NENHUMA"),
    "4B.8.5": ("Regras temporais versionadas; parametros futuros indisponiveis recusados explicitamente.", "PARAMETROS_FUTUROS_OFICIAIS"),
    "4B.8.6": ("Calculo separado IBS estadual, IBS municipal e CBS em todos os perfis.", "EVIDENCIA_A"),
    "4B.8.7": ("Convivencia com tributos antigos por familia e periodo de transicao.", "EVIDENCIA_A"),
    "4B.8.8": ("Trace nao vazio e replay deterministico por familia.", "REGRESSAO_INTEGRAL"),
    "4B.8.9": ("Decisao deterministica com hash e replay; recusas futuras explicitas.", "REGRESSAO_INTEGRAL"),
    "4B.8.10": ("Projecao documental exigida para todos os perfis.", "XSD_OFICIAL_NT009_NAO_PUBLICADO"),
    "4B.8.11": ("Invariantes por familia/periodo com mutacoes positivas e negativas.", "IMPLEMENTACAO_B"),
    "4B.8.12": ("Cenarios 2026, 2027, transicao e regime final por familia, com dados ausentes, conflitos e versoes fora de vigencia.", "PARAMETROS_FUTUROS_OFICIAIS"),
}
NEXT = {
    "INTEGRACAO_A_B02": "Aguardar patch do A (B02) e validar.",
    "INTEGRACAO_A_B07": "Aguardar implementacao do A (Q-B06-1) e executar os 320 casos B07.",
    "XSD_OFICIAL_NT009_NAO_PUBLICADO": "Monitorar publicacao do XSD oficial com campos NT009; nao adaptar schema.",
    "FONTE_OFICIAL_VIGENCIA": "Qualificar vigencia/ambiente por fonte oficial antes de ativar.",
    "FONTE_OFICIAL_IMPLANTACAO": "Separar condicoes dependentes de implantacao oficial; nao presumir.",
    "REGRESSAO_INTEGRAL": "Recuperar as 34 pastas da maquina antiga e rodar a regressao integral apos integracao A+B.",
    "INTEGRACAO_A_B_E_REGRESSAO": "Integracao A+B autorizada + regressao integral final.",
    "EVIDENCIA_A": "Receber relatorio de integracao do A.",
    "PARAMETROS_FUTUROS_OFICIAIS": "Aguardar parametros oficiais futuros; manter recusas explicitas.",
    "REVALIDACAO_PERIODICA": "Revalidar bytes no portal a cada corte.",
    "DADO_E_FONTE_EXTERNA": "Definir com o usuario as provas de importacao aceitas (DI/DUIMP, pagamento).",
    "IMPLEMENTACAO_B": "Candidato a proximo corte B independente.",
    "FONTE_E_IMPLEMENTACAO_B": "Pesquisar fonte oficial por perfil e implementar em cortes pequenos.",
    "NENHUMA": "Manter invariante nos proximos cortes.",
    "NENHUMA_ALEM_DE_VIGENCIA": "Manter edicoes; vigencia oficial do Anexo VIII nao declarada pelo portal.",
}
m = json.loads(MATRIX.read_text(encoding="utf-8"))
cons = {c["id"]: c for c in m["consolidation_20261103"]["items"]}
items = []
for it in m["requirements"]:
    if it["group"] not in ("4B.5", "4B.6", "4B.7", "4B.8"):
        continue
    crit, dep = CRITERIA[it["id"]]
    later = [e["package"] for e in (it.get("subsequent_scoped_evidence") or []) if isinstance(e, dict)]
    c = cons[it["id"]]
    items.append({
        "id": it["id"], "requirement": it["requirement"], "acceptance_criterion": crit,
        "historical_coverage": it.get("observed_coverage_at_own_review"),
        "evidence_packages": later, "b_cuts_20261103": c.get("new_evidence_20261103", []),
        "classification": c["classification"], "remaining_gap": it["current_remaining_gap"],
        "primary_dependency": dep, "external_dependency_note": c.get("external_dependency"),
        "next_action": NEXT[dep], "formal_completion_approved": False,
    })
summary = {}
for i in items:
    summary.setdefault(i["primary_dependency"], 0)
    summary[i["primary_dependency"]] += 1
out = {"version": "acceptance-matrix-b-b08", "source_matrix": str(MATRIX.relative_to(ROOT)).replace("\\", "/"),
       "source_matrix_sha256": MATRIX_SHA, "items": items, "by_primary_dependency": dict(sorted(summary.items())),
       "approved_items": 0, "b_complete": False}
Path(sys.argv[1]).write_bytes((json.dumps(out, ensure_ascii=False, indent=2) + "\n").encode("utf-8"))
lines = ["| ID | Critério de aceite | Classificação | Cortes B | Dependência | Próxima ação |", "|---|---|---|---|---|---|"]
for i in items:
    lines.append(f"| {i['id']} | {i['acceptance_criterion']} | {i['classification']} | {', '.join(i['b_cuts_20261103']) or '—'} | {i['primary_dependency']} | {i['next_action']} |")
Path(sys.argv[2]).write_bytes(("\n".join(lines) + "\n").encode("utf-8"))
print(json.dumps({"items": len(items), "by_primary_dependency": out["by_primary_dependency"]}, ensure_ascii=False))
