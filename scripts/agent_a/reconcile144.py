"""Index current evidence for A's 44 requirements; never edits the global matrix."""
import hashlib
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs/fiscal/agent-a/20261103"
CHECKPOINT = "docs/fiscal/checkpoints/20261027-import143/"
MODULE = "app/Modules/Fiscal/ConsumptionReform/"
ENGINE = MODULE + "ConsumptionReformEngine.php"
UNIT = "tests/Fiscal/Unit/"
INTEGRATION = "tests/Fiscal/Integration/"

# Narrow components supplement the central runner, which is read-only to A144.
MAP = {
    "4B.1.1": (["TransitionCatalog", "TemporalRateCatalog"], ["PostIssIntegration", "IssTransitionIntegration"]),
    "4B.1.2": (["TemporalRateCatalog"], ["ConsumptionReformEngine"]),
    "4B.1.3": (["TransitionCatalog"], ["IssTransitionRateEngine", "IssTransitionIntegration"]),
    "4B.1.4": (["TransitionCatalog"], ["PostIssIntegration"]),
    "4B.1.5": (["TaxPointContext86", "TaxPointEventPlanner85", "SupplyDateEvidenceResolver83"], ["TaxPointContext86", "TemporalEvents86"]),
    "4B.1.6": (["TemporalRateCatalog", "TransitionCatalog", "TaxPointContext86"], ["TransitionDeploymentLifecycle114", "TaxPointContext86"]),
    "4B.1.7": (["TemporalRateCatalog", "TransitionCatalog"], ["ConsumptionReformEngine", "TransitionDeploymentLifecycle114"]),
    "4B.1.8": (["TaxPointContext86", "TemporalRateCatalog"], ["TaxTemporalResolver", "TaxPointContext86"]),
    "4B.2.1": ([], ["ConsumptionReformEngine"]),
    "4B.2.2": (["PersonalUseRecipientQualifier", "DonationQualifier"], ["PersonalUseSupplyOrigin", "AcquiredPersonalUse", "DonationQualification"]),
    "4B.2.3": ([], ["ConsumptionReformEngine", "AnnexServiceIntegrated"]),
    "4B.2.4": (["PresenceDestinationQualifier", "ObservedGeographicReference115"], ["MaterialInput119"]),
    "4B.2.5": (["PresenceDestinationQualifier", "ObservedGeographicReference115"], ["ConsumptionReformEngine", "MaterialInput119"]),
    "4B.2.6": ([], ["ConsumptionReformEngine"]),
    "4B.2.7": (["PresenceDestinationQualifier"], ["ConsumptionReformEngine"]),
    "4B.2.8": (["ImportResponsibility129", "ImportFxTransactionQualifier"], ["ImportResponsibility129Integration", "ImportDocumentMoney141", "ImportDpsModel143"]),
    "4B.2.9": (["CurrentPartialExportIss96", "PartialExportDocumentPrices112"], ["ExportIssResults96", "PartialExportDpsPair135"]),
    "4B.2.10": (["PresenceDestinationQualifier"], ["ConsumptionReformEngine"]),
    "4B.2.11": ([], ["ConsumptionReformEngine"]),
    "4B.2.12": (["PlatformResponsibilityEngine"], ["PlatformResponsibilityEngine"]),
    "4B.3.1": ([], ["ConsumptionReformEngine", "ImportResponsibility129Integration"]),
    "4B.3.2": (["CollectionDecisionEngine", "CollectionBasisReference119"], ["ConsumptionReformEngine", "MaterialInput119"]),
    "4B.3.3": (["ImportResponsibility129"], ["ConsumptionReformEngine", "ImportResponsibility129Integration"]),
    "4B.3.4": (["ImportResponsibility129"], ["ImportResponsibility129", "ImportResponsibility129Integration"]),
    "4B.3.5": (["PlatformResponsibilityEngine"], ["PlatformResponsibilityEngine"]),
    "4B.3.6": (["ImportResponsibility129", "PlatformResponsibilityEngine"], ["ImportResponsibility129Integration", "PlatformResponsibilityEngine"]),
    "4B.3.7": (["CollectionDecisionEngine", "TransitionCatalog"], ["ConsumptionReformEngine", "TransitionDeploymentLifecycle114"]),
    "4B.3.8": (["CollectionDecisionEngine", "CollectionBasisReference119"], ["ConsumptionReformEngine", "MaterialInput119"]),
    "4B.3.9": (["GovernmentPurchaseQualifier", "SimplesRegularElectionQualifier78"], ["GovernmentPurchaseQualifier", "SimplesRegularElectionEngine78"]),
    "4B.3.10": (["ImportResponsibility129", "CollectionDecisionEngine"], ["ImportResponsibility129Integration", "ConsumptionReformEngine"]),
    "4B.4.1": (["OrdinaryAdjustmentInput119"], ["MaterialInput119", "ImportDocumentMoney141"]),
    "4B.4.2": (["OrdinaryAdjustmentInput119", "LegacyExclusionWindow135"], ["MaterialInput119", "ImportDocumentMoney141", "PublicPayment139"]),
    "4B.4.3": (["OrdinaryAdjustmentInput119"], ["MaterialInput119", "ConsumptionReformEngine"]),
    "4B.4.4": (["OrdinaryAdjustmentInput119"], ["MaterialInput119"]),
    "4B.4.5": (["OrdinaryAdjustmentInput119"], ["MaterialInput119", "ConsumptionReformEngine"]),
    "4B.4.6": ([], ["ConsumptionReformEngine"]),
    "4B.4.7": (["TemporalRateCatalog"], ["ConsumptionReformEngine", "PostIssIntegration"]),
    "4B.4.8": (["ProfessionalReductionQualifier", "AnnexServiceReductionQualifier"], ["ProfessionalReduction", "AnnexServiceIntegrated"]),
    "4B.4.9": (["FinancialBaseEngine"], ["FinancialBaseEngine"]),
    "4B.4.10": (["FinancialBaseEngine", "FinancialRegimeQualifier"], ["FinancialBaseEngine"]),
    "4B.4.11": (["AdvanceTaxReconciler84"], ["MaterialInput119Contract", "FinancialBaseEngine"]),
    "4B.4.12": (["TemporalRateCatalog"], ["ConsumptionReformEngine", "PostIssIntegration"]),
    "4B.4.13": (["TemporalRateCatalog"], ["ConsumptionReformEngine", "PostIssIntegration"]),
    "4B.4.14": (["OrdinaryAdjustmentInput119", "AdvanceTaxReconciler84"], ["MaterialInput119", "ImportDocumentMoney141"]),
}


def evidence(path):
    raw = (ROOT / path).read_bytes()
    text = raw.decode("utf-8-sig")
    anchors = [{"line": i, "text": line.strip()} for i, line in enumerate(text.splitlines(), 1)
               if re.match(r"\s*(?:public function |private function |it\(|test\(|final )", line)]
    return {"path": path, "sha256": hashlib.sha256(raw).hexdigest(), "anchors": anchors}


def test_path(name):
    matches = [prefix + name + "Test.php" for prefix in (UNIT, INTEGRATION)
               if (ROOT / (prefix + name + "Test.php")).is_file()]
    assert len(matches) == 1, (name, matches)
    return matches[0]


def main():
    matrix = json.loads((ROOT / (CHECKPOINT + "requirements80-current-matrix.json")).read_text(encoding="utf-8"))
    rows = []
    for item in matrix["requirements"]:
        if item["id"] not in MAP:
            continue
        components, tests = MAP[item["id"]]
        gap = item["current_remaining_gap"]
        correction = None
        if item["id"] == "4B.2.8":
            correction = "143 já monta localmente a DPS completa do perfil141/142; não é ausência de implementação documental desse perfil."
            gap = "Demais serviços, eventos, moedas e perfis; compatibilidade com fontes oficiais e integração pelo B permanecem sem aceite integral. A recusa histórica XSD/RN não foi revalidada como situação oficial atual."
        if item["id"] == "4B.4.6":
            correction = "Ajustes monetários múltiplos não comprovam operações econômicas combinadas; nenhum teste positivo específico de múltiplos fornecimentos foi identificado nesta triagem."
        if item["id"] == "4B.4.9":
            correction = "Regime financeiro e redução de alíquota não comprovam benefício legal de redução de base; aplicabilidade e contrato específicos ainda exigem qualificação."
        rows.append({"id": item["id"], "requirement": item["requirement"],
                     "classification": "PARCIAL", "verification_scope": "VINCULOS_E_HASHES_ATUAIS; ABRANGENCIA_FUNCIONAL_INTEGRAL_NAO_COMPROVADA",
                     "historical_acceptance_state": item["current_acceptance_state"],
                     "historical_observation": item.get("observed_coverage_at_own_review"),
                     "later_remediation_retained": item.get("later_scoped_remediation"),
                     "code": [evidence(path) for path in dict.fromkeys([ENGINE] + [MODULE + n + ".php" for n in components])],
                     "tests_present_not_freshly_executed_by_A144": [evidence(test_path(n)) for n in tests],
                     "historical_receipt_reference": item.get("review_evidence"),
                     "checkpoint_source": CHECKPOINT + "requirements80-current-matrix.json",
                     "current_gap": gap, "reconciliation_note": correction,
                     "formal_group_closed": False})
    assert len(rows) == len(MAP) == 44
    assert len({row["id"] for row in rows}) == 44
    assert [sum(r["id"].startswith(f"4B.{g}.") for r in rows) for g in range(1, 5)] == [8, 12, 10, 14]
    checkpoint = json.loads((ROOT / (CHECKPOINT + "checkpoint.json")).read_text(encoding="utf-8"))
    receipts = []
    for entry in checkpoint["published_compact_evidence"]:
        record = evidence(entry["published_path"])
        assert record["sha256"] == entry["sha256"], entry["published_path"]
        receipts.append(record)
    result = {"task": "A144", "observed_at": datetime.now(timezone.utc).isoformat(),
              "base": subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip(),
              "global_matrix_edited": False, "console_edited": False, "B_received": False,
              "A144_created_fiscal_implementation": False, "formal_groups_closed": 0,
              "method_limit": "Hashes e âncoras provam referências atuais, não aceite funcional. Lacunas históricas não são automaticamente defeitos atuais.",
              "verified_checkpoint_receipts": receipts, "requirements": rows}
    delivery_path = OUT / "validation-A145.json"
    if delivery_path.is_file():
        delivery = json.loads(delivery_path.read_text(encoding="utf-8"))
        for row in rows:
            if row["id"] in ("4B.2.1", "4B.2.3", "4B.4.1", "4B.4.6", "4B.4.12", "4B.4.13", "4B.4.14"):
                row["later_scoped_delivery_A145"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A145.json"),
                    "implementation": evidence(MODULE + "CombinedSupplyAssessment145.php"),
                    "test": evidence(INTEGRATION + "CombinedSupplyAssessment145Test.php"),
                    "tests": delivery["suites_separately_reported_not_summed"][1],
                    "scope": "Fornecimentos independentes especificados, domésticos/BRL/saída regular; projeção combinada pendente de B.",
                    "whole_requirement_accepted": False,
                }
                if row["id"] == "4B.4.6":
                    row["initial_reconciliation_gap_retained"] = row["current_gap"]
                    row["current_gap"] = "A145 compõe e comprova dois fornecimentos independentes de tratamentos/locais distintos. Restam principal/acessório, demais contextos e integração documental aplicáveis; não é aceite integral."
    accessory_path = OUT / "validation-A146.json"
    if accessory_path.is_file():
        for row in rows:
            if row["id"] == "4B.4.6":
                row["later_scoped_delivery_A146"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A146.json"),
                    "test": evidence(INTEGRATION + "PrincipalAccessoryAssessment146Test.php"),
                    "scope": "Fornecimento principal com acessórios qualificados como condição/meio; preço total tributado uma vez, sem alocação presumida.",
                    "whole_requirement_accepted": False,
                }
                row["current_gap"] = "A145/A146 comprovam os perfis domésticosBRL de fornecimentos independentes e principal/acessórios. Restam outros contextos aplicáveis e integração documental pelo B; não é aceite integral."
    lifecycle_path = OUT / "validation-A147.json"
    if lifecycle_path.is_file():
        for row in rows:
            if row["id"] in ("4B.1.6", "4B.1.7", "4B.1.8"):
                row["later_scoped_delivery_A147"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A147.json"),
                    "implementation": evidence(MODULE + "TaxPointContext86.php"),
                    "test": evidence(UNIT + "TaxPointLifecycle147Test.php"),
                    "scope": "Microssegundos, UTC e offsets no ciclo temporal86; integração nos dois runners e guardas88/91; sem ato oficial sintético promovido.",
                    "whole_requirement_accepted": False,
                }
    collection_path = OUT / "validation-A148.json"
    if collection_path.is_file():
        for row in rows:
            if row["id"] in ("4B.3.1", "4B.3.2", "4B.3.7", "4B.3.8", "4B.3.10"):
                row["later_scoped_delivery_A148"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A148.json"),
                    "implementation": evidence(MODULE + "CollectionDecisionEngine.php"),
                    "test": evidence(INTEGRATION + "CollectionEvidence148Test.php"),
                    "scope": "Referências materiais/identidades utilizáveis; perguntas exatas nos dois runners; elegibilidade preservada sem presumir extinção.",
                    "whole_requirement_accepted": False,
                }
    locality_path = OUT / "validation-A149.json"
    if locality_path.is_file():
        for row in rows:
            if row["id"] in ("4B.2.3", "4B.2.4", "4B.2.5", "4B.2.7"):
                row["later_scoped_delivery_A149"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A149.json"),
                    "implementation": evidence(MODULE + "ServiceLocality149.php"),
                    "test": evidence(INTEGRATION + "ServiceLocality149Test.php"),
                    "scope": "Oito famílias de local material com registros vinculados; não seleciona códigos/taxas e requer integraçãoB.",
                    "whole_requirement_accepted": False,
                }
    measured_path = OUT / "validation-A150.json"
    if measured_path.is_file():
        for row in rows:
            if row["id"] in ("4B.2.4", "4B.2.5", "4B.4.11", "4B.4.12", "4B.4.14"):
                row["later_scoped_delivery_A150"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A150.json"),
                    "implementation": evidence(MODULE + "ServiceLocality149.php"),
                    "test": evidence(INTEGRATION + "TerritorialLocality150Test.php"),
                    "scope": "Imóvel por maior área municipal agregada e via com frações exatas por município/UF; sem taxas ou classificação global.",
                    "whole_requirement_accepted": False,
                }
    domicile_path = OUT / "validation-A151.json"
    if domicile_path.is_file():
        for row in rows:
            if row["id"] in ("4B.2.4", "4B.2.5", "4B.2.7", "4B.2.10", "4B.3.2"):
                row["later_scoped_delivery_A151"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A151.json"),
                    "implementation": evidence(MODULE + "EntityDomicile151.php"),
                    "test": evidence(INTEGRATION + "EntityDomicile151Test.php"),
                    "scope": "Domicílio da entidade cadastrada pelo estabelecimento atendido; parte aplicável por onerosidade/residência. Não presume sede, crédito ou incidência.",
                    "remaining": "Pessoas físicas, não cadastrados, centralização, múltiplos estabelecimentos atendidos e integração pelos contratos de B.",
                    "whole_requirement_accepted": False,
                }
    centralized_path = OUT / "validation-A152.json"
    if centralized_path.is_file():
        for row in rows:
            if row["id"] in ("4B.2.4", "4B.2.5", "4B.2.7", "4B.2.10", "4B.3.2", "4B.3.9"):
                row["later_scoped_delivery_A152"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A152.json"),
                    "implementation": evidence(MODULE + "EntityDomicile151.php"),
                    "service_linkage": evidence(MODULE + "ServiceLocality149.php"),
                    "test": evidence(INTEGRATION + "CentralizedDomicile152Test.php"),
                    "scope": "Centralização: matriz única, múltiplos estabelecimentos ativos, regime regular IBS/CBS e predicado externo de não vedação. Não determina crédito.",
                    "remaining": "Integração da qualificação real de B; pessoas físicas/não cadastrados e demais contextos; integração documental e regressão integral final.",
                    "whole_requirement_accepted": False,
                }
    party_path = OUT / "validation-A153.json"
    if party_path.is_file():
        for row in rows:
            if row["id"] in ("4B.2.4", "4B.2.5", "4B.2.6", "4B.2.7", "4B.2.10", "4B.3.2"):
                row["later_scoped_delivery_A153"] = {
                    "receipt": evidence("docs/fiscal/agent-a/20261103/validation-A153.json"),
                    "implementation": evidence(MODULE + "PartyDomicile153.php"),
                    "dispatcher": evidence(MODULE + "EntityDomicile151.php"),
                    "test": evidence(INTEGRATION + "PartyDomicile153Test.php"),
                    "scope": "PF consumidora cadastrada; critérios distintos escolhidos para não cadastrados; alternativa declarada com prova de impossibilidade. Não presume domicílio pelo IP.",
                    "remaining": "PF em atividade econômica/estabelecimento; integração com B, dados reais e regressão integral final.",
                    "whole_requirement_accepted": False,
                }
    OUT.mkdir(parents=True, exist_ok=True)
    destination = OUT / "requirements44-reconciliation.json"
    destination.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"requirements": len(rows), "groups": [8, 12, 10, 14],
                      "checkpoint_hashes_verified": len(receipts), "path": str(destination)}, ensure_ascii=False))


if __name__ == "__main__":
    main()
