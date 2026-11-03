"""B06: analise documental individualizada das regras ENGINE divergentes/sem correlacao (B04).

Somente leitura. Usa apenas fontes oficiais ja preservadas e vinculadas por hash no catalogo ativo:
cClassTrib IT2025.002 v1.70, cIndOp 1.03.00, NBS v1.01, Anexos II/III da LC 214 consolidada e as
correlacoes do Anexo VIII 1.01.00 (ativa) e 1.02.00 (B01). Nao altera regras, nao aprova regras e nao
preenche correlacoes. Classifica cada situacao em resolvida documentalmente ou controversa.
Uso: python -I -X utf8 scripts/analyze_phase4b_b06_rule_documentation.py <out.json>
"""
import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ACTIVE = ROOT / "resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json"
B01 = ROOT / "resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json"
B04 = ROOT / "storage/app/phase4b-b04-development-20261103/b04-reconciliation-report.json"
EXPECTED = {ACTIVE: None, B01: "3835aa14055984229f7f07ef84c2dc1963a4827e9a6a9b2cde0bef5501cca3d3",
            B04: "6e7bc4b6194e4f183a462198ddc73d924a81d93bf8804f095bbb4c3c9a59c2ad"}
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
for p, h in EXPECTED.items():
    if h and sha(p) != h:
        raise SystemExit(f"Entrada divergente: {p}")

cat = json.loads(ACTIVE.read_text(encoding="utf-8"))
b01 = json.loads(B01.read_text(encoding="utf-8"))
b04 = json.loads(B04.read_text(encoding="utf-8"))
rules = cat["engine_decision_rules"]
cclass, cindop, nbs = cat["cclasstrib"], cat["cindop"], cat["nbs"]
annexes = cat["annex_service_reduction"]["annexes"]
editions = {"1.01.00": cat["correlations"], "1.02.00": b01["correlations"]}
SPECIAL_SUBJECT = {"410006": "IMUNIDADE_SUBJETIVA_ART9_II", "410007": "IMUNIDADE_SUBJETIVA_ART9_III",
                   "200020": "REGIME_ESPECIFICO_COOPERATIVA_ART271", "200052": "PROFISSAO_INTELECTUAL_ART127"}
ANNEX_CCLASS = {"200028": "EDUCATION", "200029": "HEALTH"}


def article_numbers(text):
    return set(re.findall(r"(?:art\.?|arts\.?)\s*([0-9]+)", text, re.I)) | set(re.findall(r"-([0-9]+)", text))


def destination_ok(rule, codes):
    basis = rule["destination_basis"]
    texts = {c: cindop[c]["destination_basis"].lower() for c in codes if c in cindop}
    if basis == "ACQUIRER_DOMICILE":
        return all("adquirente" in t for t in texts.values()), "Art. 11, X, a, 1 (domicilio do adquirente)"
    if basis == "RECIPIENT_DOMICILE":
        return all("destinat" in t for t in texts.values()), "Art. 11, X, a, 2 (domicilio do destinatario)"
    if basis == "PERSON_PRESENCE":
        by = rule.get("cindop_by_presence", {})
        ok = ("estabelecimento do fornecedor" in cindop.get(by.get("SUPPLIER", ""), {}).get("destination_basis", "").lower()
              and "diverso do estabelecimento" in cindop.get(by.get("OTHER", ""), {}).get("destination_basis", "").lower())
        return ok, "Art. 11, III (prestacao fisica/fruicao presencial)"
    return False, "BASE_DE_DESTINO_NAO_MAPEADA"


def item_rows(rule, rows):
    code = rule["ctribnac_code"]
    item = f"{code[:2]}.{code[2:4]}"
    prefixes = rule.get("nbs_prefixes", [])
    same_item = [r for r in rows if r["lc116_code"] == item and any(r["nbs"].startswith(p) for p in prefixes)]
    other_item = [r for r in rows if r["lc116_code"] not in ("", item) and any(r["nbs"].startswith(p) for p in prefixes)]
    unattributed = [r for r in rows if r["lc116_code"] == "" and any(r["nbs"].startswith(p) for p in prefixes)]
    return item, same_item, other_item, unattributed


b04_status = {i["rule"]: i["editions"] for i in b04["report"]["items"]}
targets = sorted(r for r, e in b04_status.items()
                 if e["1.02.00"]["status"] in ("DIVERGENT_NO_LITERAL_MATCH", "NO_OFFICIAL_ROW_IN_PREFIX"))
results = []
for rid in targets:
    rule = rules[rid]
    codes = sorted({rule["cindop"]} if "cindop" in rule else set() | set(rule.get("cindop_by_presence", {}).values()))
    if "cindop" in rule and rule.get("cindop_by_presence"):
        codes = sorted({rule["cindop"], *rule["cindop_by_presence"].values()})
    checks, flags = {}, []
    cc = cclass.get(rule["cclasstrib"])
    checks["C1_cclasstrib_oficial"] = bool(cc and cc["nfse_supported"] and cc["valid_from"] <= rule["valid_from"]
                                          and (cc["valid_to"] is None or cc["valid_to"] >= rule["valid_from"]))
    checks["C2_cindop_oficial"] = bool(codes) and all(c in cindop and cindop[c]["nfse_supported"] for c in codes)
    dest_ok, dest_basis = destination_ok(rule, codes)
    checks["C3_base_de_destino_coerente"] = dest_ok
    prefixes = rule.get("nbs_prefixes", [])
    checks["C4_nbs_existente"] = bool(prefixes) and all(any(k.startswith(p) for k in nbs) for p in prefixes)
    ref = (cc or {}).get("legal_reference", "")
    basis = " ".join([rule.get("source_article", ""), rule.get("legal_basis", "")])
    basis_articles = article_numbers(basis) | set(re.findall(r"art\s?([0-9]+)", basis, re.I))
    if ref:
        checks["C5_fundamento_coerente_com_cclasstrib"] = bool(article_numbers(ref) & basis_articles)
    else:
        checks["C5_fundamento_coerente_com_cclasstrib"] = "11" in basis_articles
    annex_cat = ANNEX_CCLASS.get(rule["cclasstrib"])
    annex_note = None
    if annex_cat:
        items = {v["nbs"] for v in annexes[annex_cat]["items"].values() if v["nbs"]}
        exact = all(any(p.startswith(i) for i in items) for p in prefixes)
        broader = all(any(p.startswith(i) or i.startswith(p) for i in items) for p in prefixes)
        checks["C6_nbs_no_anexo_lc214"] = broader and rule.get("annex_service_category") == annex_cat
        annex_note = ("PREFIXO_DENTRO_DO_ITEM_DO_ANEXO" if exact else
                      "PREFIXO_MAIS_AMPLO_QUE_O_ITEM;QUALIFICADOR_EXIGE_NBS_DO_ITEM") if broader else "NBS_FORA_DO_ANEXO"
    elif rule.get("annex_service_category") and rule["cclasstrib"] == "000001":
        flags.append("PADRAO_DECLARADO_PARA_NBS_DE_ANEXO")
    if rule["cclasstrib"] in SPECIAL_SUBJECT:
        flags.append("CONDICIONADA_A_FATO_SUBJETIVO:" + SPECIAL_SUBJECT[rule["cclasstrib"]])
    corr = {}
    for label, rows in editions.items():
        item, same, other, unattr = item_rows(rule, rows)
        if same:
            match = [r for r in same if r["cclasstrib"] == rule["cclasstrib"] and r["cindop"] in codes]
            if match:
                st = "CONVERGENTE_NO_ITEM"
            else:
                variant = all((r["cclasstrib"] == rule["cclasstrib"] or r["cclasstrib"] in ("000001", *ANNEX_CCLASS) or rule["cclasstrib"] in ("000001", *ANNEX_CCLASS, *SPECIAL_SUBJECT))
                              and (r["cindop"] in codes or {r["cindop"], *codes} <= {"100301", "100302", "030101", "030102"}) for r in same)
                st = "VARIANTE_DOCUMENTADA_DO_ITEM" if variant else "DIVERGENCIA_NAO_EXPLICADA_NO_ITEM"
        else:
            st = "SEM_LINHA_OFICIAL_PARA_O_ITEM"
        corr[label] = {"status": st, "lc116_item": item, "rows_same_item": [[r["nbs"], r["cindop"], r["cclasstrib"]] for r in same],
                       "rows_other_items": len(other), "rows_without_item_literal": len(unattr)}
    failed = [k for k, v in checks.items() if v is False]
    if failed or any(c["status"] == "DIVERGENCIA_NAO_EXPLICADA_NO_ITEM" for c in corr.values()):
        classification = "CONTROVERSA_EXIGE_ANALISE"
    elif "PADRAO_DECLARADO_PARA_NBS_DE_ANEXO" in flags:
        classification = "CONTROVERSA_INTERPRETACAO_JURIDICA"
    elif any(f.startswith("CONDICIONADA_A_FATO_SUBJETIVO") for f in flags):
        classification = "RESOLVIDA_DOCUMENTALMENTE_CONDICIONADA_A_FATOS"
    else:
        classification = "RESOLVIDA_DOCUMENTALMENTE"
    results.append({"rule": rid, "b04_status_1_02_00": b04_status[rid]["1.02.00"]["status"], "ctribnac": rule["ctribnac_code"],
                    "cclasstrib": rule["cclasstrib"], "cclasstrib_name": (cc or {}).get("name"), "cclasstrib_legal_reference": ref,
                    "cindops": codes, "destination_basis": rule["destination_basis"], "destination_legal_basis": dest_basis,
                    "nbs_prefixes": prefixes, "source_article": rule.get("source_article"), "legal_basis": rule.get("legal_basis"), "annex": annex_note,
                    "checks": checks, "flags": flags, "correlation_by_item": corr, "classification": classification,
                    "proposed_treatment": {
                        "RESOLVIDA_DOCUMENTALMENTE": "Manter como ENGINE_DECISION_RULE SANDBOX com fundamentos registrados; sem ativacao oficial presumida.",
                        "RESOLVIDA_DOCUMENTALMENTE_CONDICIONADA_A_FATOS": "Manter; exigir prova do fato subjetivo antes do uso (ja exigida pelo qualificador correspondente).",
                        "CONTROVERSA_INTERPRETACAO_JURIDICA": "Manter pendente de validacao juridica; nao ativar automaticamente.",
                        "CONTROVERSA_EXIGE_ANALISE": "Bloquear uso automatico ate correcao ou parecer.",
                    }[classification]})
summary = {}
for r in results:
    summary[r["classification"]] = summary.get(r["classification"], 0) + 1
out = {"version": "rule-documentation-analysis-b06", "targets": len(results), "summary": dict(sorted(summary.items())),
       "sources": {"active_catalog": str(ACTIVE.relative_to(ROOT)).replace("\\", "/"), "active_catalog_sha256": sha(ACTIVE),
                   "cclasstrib": cat["sources"]["cclasstrib"]["version"], "cindop": cat["sources"]["cindop"]["version"],
                   "nbs": cat["sources"]["nbs"]["version"], "annex_source_id": cat["annex_service_reduction"]["source_id"],
                   "annex_source_hash": cat["annex_service_reduction"]["source_hash"], "b01_sha256": EXPECTED[B01], "b04_sha256": EXPECTED[B04]},
       "decision_authority": False, "rules_changed": False, "results": results}
Path(sys.argv[1]).write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")
print(json.dumps({"targets": len(results), "summary": out["summary"]}, ensure_ascii=False))
for r in results:
    print(r["classification"][:34].ljust(34), r["rule"], [k for k, v in r["checks"].items() if v is False], r["flags"], r["correlation_by_item"]["1.02.00"]["status"])
