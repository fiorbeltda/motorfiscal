"""Independent party/establishment/date and canonical hash checks for A151 native reports."""
import hashlib
import json
from datetime import date, datetime
from pathlib import Path
from xml.etree import ElementTree

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A151-entity-domicile-20261103-v1")


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical_hash(value):
    return sha(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode("utf-8"))


def load(name):
    return json.loads((FOLDER / name).read_text(encoding="utf-8-sig"))


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
    cases = load("native-cases-v2.json")["cases"]
    assert load("native-cases-v2.json") == load("native-cases-v1.json")
    expected = {"domestic": ("ACQUIRER_DOMICILE", "ACQUIRER151"),
                "foreign-acquirer": ("RECIPIENT_DOMICILE", "RECIPIENT151"),
                "nononerous": ("RECIPIENT_DOMICILE", "RECIPIENT151"),
                "unincorporated": ("ACQUIRER_DOMICILE", "ACQUIRER151"),
                "remote-personal": ("ACQUIRER_DOMICILE", "ACQUIRER151")}
    assert len(cases) == 5 and {row["variant"] for row in cases} == set(expected)
    oracles = []
    for row in cases:
        report = row["result"]
        operation = report["operation_input"]
        registry = operation["registry_evidence"]["record"]
        supply = operation["supply_establishment_evidence"]["record"]
        assert report["status"] == "LOCAL_ENTITY_DOMICILE_QUALIFIED" and row["replay"] is True
        basis, party = expected[row["variant"]]
        assert report["destination_basis"] == basis and report["selected_party_id"] == party
        assert registry["entity_id"] == supply["entity_id"] == party
        assert registry["unique_registration"] is True and registry["regularly_registered"] is True
        assert supply["supplied_establishment_ids"] == ["BRANCH151"]
        choices = [entry for entry in registry["establishments"] if entry["id"] == "BRANCH151"]
        assert len(choices) == 1
        entry = choices[0]
        on = date.fromisoformat(operation["operation_date"])
        assert date.fromisoformat(entry["valid_from"]) <= on
        assert entry["valid_until"] is None or date.fromisoformat(entry["valid_until"]) >= on
        assert entry["entity_id"] == party and entry["headquarters"] is False
        assert report["selected_establishment_id"] == entry["id"] == "BRANCH151"
        assert report["locality"] == {"uf": "RJ", "municipality_ibge": "3304557"}
        assert report["headquarters_selected"] is False
        assert any(e["headquarters"] is True and e["uf"] == "SP" for e in registry["establishments"])
        for key in ("scope_evidence", "registry_evidence", "supply_establishment_evidence"):
            envelope = operation[key]
            record = envelope["record"]
            assert envelope["record_hash"] == canonical_hash(record)
            assert datetime.fromisoformat(record["observed_at"]) <= datetime.fromisoformat(operation["knowledge_at"])
            assert record["applicable_on"] == operation["operation_date"]
            for field in ("operation_id", "supply_id", "supplier_id", "acquirer_id", "recipient_id", "onerous",
                          "acquirer_domiciled_in_brazil", "recipient_domiciled_in_brazil"):
                assert record[field] == operation[field]
        assert report["input_hash"] == canonical_hash(operation)
        payload = dict(report)
        claimed = payload.pop("report_hash")
        assert claimed == canonical_hash(payload)
        for flag in ("tax_rate_selected", "tax_incidence_determined", "credit_right_determined", "official_registry_queried",
                     "source_record_authenticity_proven", "production_ready"):
            assert report[flag] is False
        oracles.append({"case": row["variant"], "party": party, "basis": basis, "supplied_establishment": "BRANCH151",
                        "destination": "RJ/3304557", "SP_headquarters_not_substituted": True,
                        "record_dates_and_independent_canonical_hashes": True})
    assert (FOLDER / "cli-first.json").read_bytes() == (FOLDER / "cli-second.json").read_bytes()
    assert load("cli-first.json")["replay_matches"] is True
    invalid = load("cli-invalid.json")["report"]
    assert invalid["status"] == "ACTION_REQUIRED" and invalid["locality"] is None
    assert invalid["reason"] == "DOMICILE151_SUPPLIED_ESTABLISHMENT_ABSENT_FROM_REGISTRY"
    assert load("cli-exits.json") == {"native": 0, "first": 0, "second": 0, "invalid": 1}
    assert load("cli-exits-v2.json") == {"native": 0, "service_first": 0, "service_second": 0, "legacy149": 0, "legacy150": 0}
    assert (FOLDER / "cli-service-first.json").read_bytes() == (FOLDER / "cli-service-second.json").read_bytes()
    linked = load("cli-service-first.json")
    assert linked["replay_matches"] is True
    linked_report = linked["report"]
    assert linked_report["contract"] == "RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151"
    assert linked_report["status"] == "LOCAL_SERVICE_ENTITY_DOMICILE_QUALIFIED"
    assert linked_report["selected_establishment_id"] == "BRANCH151"
    assert linked_report["locality"] == {"uf": "RJ", "municipality_ibge": "3304557"}
    assert linked_report["entity_domicile_report"] == linked_report["operation_input"]["entity_domicile_qualification"]
    assert linked_report["input_hash"] == canonical_hash(linked_report["operation_input"])
    payload = dict(linked_report)
    claimed = payload.pop("report_hash")
    assert claimed == canonical_hash(payload)
    legacy149 = Path("D:/ProjetoSaasExports/agent-a-A149-service-locality-20261103-v1/cli-first.json")
    legacy150 = Path("D:/ProjetoSaasExports/agent-a-A150-territorial-measures-20261103-v1/cli-road-first.json")
    assert (FOLDER / "cli-A149-after.json").read_bytes() == legacy149.read_bytes()
    assert (FOLDER / "cli-A150-after.json").read_bytes() == legacy150.read_bytes()
    accepted150 = json.loads((ROOT / "docs/fiscal/agent-a/20261103/validation-A150.json").read_text(encoding="utf-8-sig"))
    assert accepted150["code_after_sha256"] == sha((FOLDER / "ServiceLocality149-before.php").read_bytes())
    for name in ("directed-v3", "regression-v2", "phpstan-v3", "pint-v3"):
        assert load(name + "-exit.json") == {"exit_code": 0}
    source = load("source.json")
    assert source["status"] == 200 and source["matches_A145"] is True
    assert source["sha256"] == sha((FOLDER / "LC214.html").read_bytes()) == "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
    directed = suite("agent-a-A151-directed-v3.junit.xml", (120, 483))
    regression = suite("agent-a-A151-regression-v2.junit.xml", (374, 1343))
    paths = ["app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php",
             "app/Console/Commands/QualifyEntityDomicile151Command.php", "scripts/agent_a/domicile151_fixture.php",
             "tests/Fiscal/Integration/EntityDomicile151Test.php", "scripts/agent_a/verify151.py",
             "app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php",
             "app/Console/Commands/QualifyServiceLocality149Command.php"]
    receipt = {"task": "A151", "scope": "4B.2.4/2.5/2.7/2.10 and 4B.3.2; registered entity actual supplied establishment",
               "party_establishment_oracles": oracles, "directed": directed,
               "regression_includes_directed_do_not_add": regression, "native_cli_identical": True,
               "native_whole_report_replay": True, "expected_refusal_exit": 1, "source": source,
               "phpstan_global_exit": 0, "pint_six_php_files_exit": 0,
               "service_linkage_cli_identical_and_replayed": True, "legacy_A149_A150_cli_bytes_unchanged": True,
               "files": [{"path": path, "sha256": sha((ROOT / path).read_bytes())} for path in paths],
               "source_record_authenticity_proven": False, "official_registry_queried": False,
               "classification_catalog_integration": "PENDING_AGENT_B", "whole_requirement_accepted": False,
               "full_regression_executed": False, "production_ready": False,
               "matrix_console_proposal": {
                   "writer": "B", "applied": False, "B_received": False,
                   "requirement_ids": ["4B.2.4", "4B.2.5", "4B.2.7", "4B.2.10", "4B.3.2"],
                   "classification": "PARCIAL_COM_ACEITE_ESCOPADO_A151",
                   "completed": ["Parte aplicável por onerosidade/residência", "Estabelecimento atendido vinculado ao cadastro",
                                 "Serviço pessoal remoto integrado opcionalmente no qualificador A149"],
                   "pending": ["Pessoas físicas e não cadastrados", "Centralização e vários estabelecimentos atendidos",
                               "Classificação/projeção de B e regressão integral integrada"],
                   "next_step": "Qualificar a exceção material de contratação centralizada sem determinar direitos de crédito",
                   "formal_A_groups_closed": 0, "phase_4B_closed": False}}
    preserve_json(ROOT / "docs/fiscal/agent-a/20261103/validation-A151.json", receipt)
    print(json.dumps({"task": "A151", "independent_cases": len(oracles), "directed": directed,
                      "regression": regression, "receipt_preserved": True}))


if __name__ == "__main__":
    main()
