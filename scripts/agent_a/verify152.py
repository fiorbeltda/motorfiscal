"""Independent active-matrix, legal predicates, dates and canonical hashes for A152 native evidence."""
import hashlib
import json
from datetime import date, datetime
from pathlib import Path
from xml.etree import ElementTree

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A152-centralized-domicile-20261103-v1")
EVIDENCE_FIELDS = ("scope_evidence", "registry_evidence", "centralized_purchase_evidence",
                   "regular_regime_evidence", "credit_restriction_evidence")


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical_hash(value):
    return sha(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode("utf-8"))


def load(name):
    return json.loads((FOLDER / name).read_text(encoding="utf-8-sig"))


def covers(record, on):
    return date.fromisoformat(record["valid_from"]) <= on and (
        record["valid_until"] is None or date.fromisoformat(record["valid_until"]) >= on)


def check_hash(report):
    assert report["input_hash"] == canonical_hash(report["operation_input"])
    payload = dict(report)
    claimed = payload.pop("report_hash")
    assert claimed == canonical_hash(payload)


def matrix_oracle(report, case, expected_id="HQ151", expected_uf="SP", expected_municipality="3550308"):
    operation = report["operation_input"]
    assert report["contract"] == "RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152"
    assert report["status"] == "LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED"
    assert operation["onerous"] is True and operation["acquirer_domiciled_in_brazil"] is True
    on = date.fromisoformat(operation["operation_date"])
    registry = operation["registry_evidence"]["record"]
    scope = operation["scope_evidence"]["record"]
    category = operation["supply_category"]
    articles = {"FIXED_COMMUNICATION": "LC214 art.11 IX", "MOVABLE_RENTAL": "LC214 art.11 §4 I",
                "RESIDUAL_SERVICE": "LC214 art.11 X", "PERSONAL_REMOTE": "LC214 art.11 X e §5"}
    assert scope["supply_category"] == category and scope["source_article"] == articles[category]
    assert scope["scope_confirmed"] is True and scope["centralized_purchase"] is True
    if category == "MOVABLE_RENTAL":
        assert scope["physical_movable_rental"] is True
    if category == "RESIDUAL_SERVICE":
        assert scope["outside_inciso_I_to_IX"] is True
    if category == "PERSONAL_REMOTE":
        assert scope["normally_personal_physical"] is True and scope["remotely_performed_at_least_partly"] is True
    assert report["source_article"] == ("LC214 art.11 §4 I e II" if category in ("FIXED_COMMUNICATION", "MOVABLE_RENTAL")
                                         else articles[category] + "; §4 II")
    purchase = operation["centralized_purchase_evidence"]["record"]
    regime = operation["regular_regime_evidence"]["record"]
    restriction = operation["credit_restriction_evidence"]["record"]
    active = [entry for entry in registry["establishments"] if covers(entry, on)]
    matrices = [entry for entry in active if entry["headquarters"] is True]
    assert len(active) > 1 and len(matrices) == 1
    assert len({entry["id"] for entry in active}) == len(active)
    matrix = matrices[0]
    assert all(entry["entity_id"] == operation["acquirer_id"] for entry in active)
    assert matrix["id"] == purchase["headquarters_establishment_id"] == report["selected_establishment_id"] == expected_id
    assert report["selected_party_id"] == operation["acquirer_id"] and report["destination_basis"] == "ACQUIRER_DOMICILE"
    assert report["locality"] == {"uf": matrix["uf"], "municipality_ibge": matrix["municipality_ibge"]}
    assert report["locality"] == {"uf": expected_uf, "municipality_ibge": expected_municipality}
    assert set(purchase["covered_establishment_ids"]) <= {entry["id"] for entry in active}
    assert len(purchase["covered_establishment_ids"]) == len(set(purchase["covered_establishment_ids"])) > 0
    assert report["covered_establishment_ids"] == sorted(purchase["covered_establishment_ids"])
    assert report["active_establishment_ids"] == sorted(entry["id"] for entry in active)
    assert registry["regularly_registered"] is True and registry["unique_registration"] is True
    assert registry["all_establishments_included"] is True and purchase["centralized"] is True
    assert regime["ibs_regime"] == regime["cbs_regime"] == "REGULAR" and covers(regime, on)
    assert restriction["complete"] is True and restriction["qualification_reference"]
    assert restriction["ibs_credit_prohibited"] is False and restriction["cbs_credit_prohibited"] is False
    for field in EVIDENCE_FIELDS:
        envelope = operation[field]
        record = envelope["record"]
        assert envelope["record_hash"] == canonical_hash(record)
        assert record["applicable_on"] == operation["operation_date"]
        assert datetime.fromisoformat(record["observed_at"]) <= datetime.fromisoformat(operation["knowledge_at"])
        for key in ("operation_id", "supply_id", "supplier_id", "acquirer_id", "recipient_id", "onerous",
                    "acquirer_domiciled_in_brazil", "recipient_domiciled_in_brazil"):
            assert record[key] == operation[key]
    assert report["external_credit_restriction_qualification_consumed"] is True
    for flag in ("credit_right_determined", "credit_restriction_assessment_authenticated", "official_registry_queried",
                 "tax_rate_selected", "tax_incidence_determined", "production_ready"):
        assert report[flag] is False
    check_hash(report)
    return {"case": case, "party": operation["acquirer_id"], "matrix": matrix["id"],
            "active_establishments": len(active), "destination": report["locality"],
            "two_regular_regimes_and_external_nonprohibition_predicates_checked": True,
            "independent_date_and_canonical_hash_checks": True}


def suite(filename, expected):
    path = ROOT / "storage/app" / filename
    xml = ElementTree.parse(path)
    assert not xml.findall(".//failure") and not xml.findall(".//error") and not xml.findall(".//skipped")
    cases = xml.findall(".//testcase")
    actual = (len(cases), sum(int(case.get("assertions", "0")) for case in cases))
    assert actual == expected, actual
    preserve_bytes(FOLDER / filename, path.read_bytes())
    return {"file": filename, "tests": actual[0], "assertions": actual[1], "sha256": sha(path.read_bytes())}


def main():
    native = load("native-cases-v1.json")
    assert native["synthetic_external_predicates"] is True and native["B_delivery_received"] is False
    cases = native["cases"]
    assert len(cases) == 4 and {case["category"] for case in cases} == {
        "FIXED_COMMUNICATION", "MOVABLE_RENTAL", "RESIDUAL_SERVICE", "PERSONAL_REMOTE"}
    oracles = []
    for case in cases:
        assert case["replay"] is True
        assert case["category"] == case["report"]["operation_input"]["supply_category"]
        oracles.append(matrix_oracle(case["report"], case["category"]))
    moved = load("cli-moved.json")
    assert moved["replay_matches"] is True and load("moved-cli-exit.json") == {"exit_code": 0}
    assert moved["report"]["operation_input"] == load("moved-headquarters-input.json")
    oracles.append(matrix_oracle(moved["report"], "MATRIX_ROLE_MOVED_TO_RJ", "BRANCH151", "RJ", "3304557"))
    assert (FOLDER / "cli-first.json").read_bytes() == (FOLDER / "cli-second.json").read_bytes()
    assert load("cli-first.json")["replay_matches"] is True
    assert (FOLDER / "cli-fixed-first.json").read_bytes() == (FOLDER / "cli-fixed-second.json").read_bytes()
    for name, field in (("cli-fixed-first.json", "centralized_purchase_qualification"),
                        ("cli-personal.json", "entity_domicile_qualification")):
        wrapped = load(name)
        report = wrapped["report"]
        assert wrapped["replay_matches"] is True
        assert report["contract"] == "RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152"
        assert report["status"] == "LOCAL_SERVICE_CENTRALIZED_DOMICILE_QUALIFIED"
        assert report["locality"] == {"uf": "SP", "municipality_ibge": "3550308"}
        nested = report["entity_domicile_report"]
        assert nested == report["operation_input"][field]
        matrix_oracle(nested, name)
        for key in ("operation_id", "supply_id", "supplier_id", "recipient_id", "operation_date", "knowledge_at"):
            assert nested["operation_input"][key] == report["operation_input"][key]
        assert report["service_scope_record"]["centralized_purchase"] is True
        check_hash(report)
    assert load("cli-fixed-first.json")["report"]["operation_input"]["locations"][0]["uf"] == "RJ"
    invalid = load("cli-invalid.json")["report"]
    assert invalid["status"] == "ACTION_REQUIRED" and invalid["locality"] is None
    assert invalid["reason"] == "DOMICILE152_EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED"
    exits = load("cli-exits.json")
    assert set(exits) == {"native-cases-v1.json", "cli-first.json", "cli-second.json", "cli-invalid.json",
                          "cli-fixed-first.json", "cli-fixed-second.json", "cli-personal.json", "cli-A149-after.json",
                          "cli-A150-after.json", "cli-A151-after.json", "cli-A151-service-after.json"}
    assert all(code == (1 if name == "cli-invalid.json" else 0) for name, code in exits.items())
    legacy = {
        "cli-A149-after.json": "agent-a-A149-service-locality-20261103-v1/cli-first.json",
        "cli-A150-after.json": "agent-a-A150-territorial-measures-20261103-v1/cli-road-first.json",
        "cli-A151-after.json": "agent-a-A151-entity-domicile-20261103-v1/cli-first.json",
        "cli-A151-service-after.json": "agent-a-A151-entity-domicile-20261103-v1/cli-service-first.json"}
    for current, previous in legacy.items():
        assert (FOLDER / current).read_bytes() == (Path("D:/ProjetoSaasExports") / previous).read_bytes(), current
    for name in ("directed-v2", "regression-v1", "pint-v2", "phpstan-v1"):
        assert load(name + "-exit.json") == {"exit_code": 0}
    sources = load("sources.json")
    assert {source["name"] for source in sources} == {"LC214", "CBS"}
    expected_sources = {"LC214": "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde",
                        "CBS": "8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235"}
    for source in sources:
        assert source["status"] == 200 and source["matches_A145"] is True
        raw = (FOLDER / (source["name"] + ".html")).read_bytes()
        assert sha(raw) == source["sha256"] == expected_sources[source["name"]] and len(raw) == source["bytes"]
    preflight = load("preflight.json")
    assert preflight["A151_receipt_files_match"] is True
    for before in preflight["files"]:
        assert before["sha256"] == sha((FOLDER / before["before_file"]).read_bytes())
    directed = suite("agent-a-A152-directed-v2.junit.xml", (185, 722))
    regression = suite("agent-a-A152-regression-v1.junit.xml", (439, 1582))
    paths = ["app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php",
             "app/Console/Commands/QualifyEntityDomicile151Command.php",
             "app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php",
             "app/Console/Commands/QualifyServiceLocality149Command.php",
             "scripts/agent_a/centralized152_fixture.php", "tests/Fiscal/Integration/CentralizedDomicile152Test.php",
             "scripts/agent_a/verify152.py"]
    receipt = {"task": "A152", "scope": "Centralized acquirer headquarters locality; external credit prohibition predicate consumed only",
               "active_matrix_oracles": oracles, "directed_includes_A149_A150_A151_do_not_add": directed,
               "regression_includes_directed_do_not_add": regression,
               "native_clis_identical_and_whole_replay": True, "legacy_A149_A150_A151_cli_bytes_unchanged": True,
               "missing_external_credit_qualification_expected_exit": 1, "phpstan_global_exit": 0, "pint_six_php_files_exit": 0,
               "sources": sources, "files": [{"path": path, "sha256": sha((ROOT / path).read_bytes())} for path in paths],
               "external_credit_predicates_synthetic": True, "B_received": False,
               "credit_right_determined": False, "classification_catalog_integration": "PENDING_AGENT_B",
               "whole_requirement_accepted": False, "full_regression_executed": False, "production_ready": False,
               "matrix_console_proposal": {
                   "writer": "B", "applied": False, "formal_A_groups_closed": 0, "phase_4B_closed": False,
                   "requirement_ids": ["4B.2.4", "4B.2.5", "4B.2.7", "4B.2.10", "4B.3.2", "4B.3.9"],
                   "classification": "PARCIAL_COM_ACEITE_ESCOPADO_A152",
                   "completed": ["Matriz única e estabelecimentos ativos vinculados", "Regime regular dos dois tributos e predicado externo de vedação",
                                 "Ligação opcional em comunicação fixa e serviço pessoal remoto"],
                   "pending": ["Integração da qualificação real de crédito de B", "Pessoas físicas/não cadastrados e demais contextos",
                               "Classificações/documentos de B e regressão integral integrada"],
                   "next_step": "Qualificar domicílio de pessoa física e critérios para não cadastrados sem presumir endereço"}}
    preserve_json(ROOT / "docs/fiscal/agent-a/20261103/validation-A152.json", receipt)
    print(json.dumps({"task": "A152", "independent_matrix_cases": len(oracles), "directed": directed,
                      "regression": regression, "legacy_cli_bytes_preserved": True}))


if __name__ == "__main__":
    main()
