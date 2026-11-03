"""Independent household/criteria/impossibility and canonical hash oracle for A153 evidence."""
import hashlib
import itertools
import json
from datetime import date, datetime
from pathlib import Path
from xml.etree import ElementTree

from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A153-party-domicile-20261103-v1")
KINDS = {"DECLARED_ADDRESS", "COMMERCIAL_INFO", "PAYMENT_ARRANGEMENT", "IP_GEOLOCATION"}


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical_hash(value):
    return sha(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode("utf-8"))


def load(name):
    return json.loads((FOLDER / name).read_text(encoding="utf-8-sig"))


def normalized(address):
    if address["in_brazil"] is False:
        return {key: address[key] for key in ("in_brazil", "granularity", "foreign_address_id")}
    return {"in_brazil": True, "uf": address["uf"], "municipality_ibge": address["municipality_ibge"],
            "granularity": address["granularity"], "components": address.get("components", [])}


def compatible(left, right):
    if left["in_brazil"] != right["in_brazil"]:
        return False
    if left["in_brazil"] is False:
        return left["foreign_address_id"] == right["foreign_address_id"]
    if (left["uf"], left["municipality_ibge"]) != (right["uf"], right["municipality_ibge"]):
        return False
    a, b = dict(left.get("components", [])), dict(right.get("components", []))
    return all(a[key] == b[key] for key in a.keys() & b.keys())


def check_hash(report):
    assert report["input_hash"] == canonical_hash(report["operation_input"])
    saved = dict(report)
    claimed = saved.pop("report_hash")
    assert claimed == canonical_hash(saved)


def oracle(report, case):
    assert report["contract"] == "RTC-MATERIAL-PARTY-DOMICILE-2026.11.03-A153"
    assert report["status"] == "LOCAL_PARTY_DOMICILE_QUALIFIED"
    operation = report["operation_input"]
    qualification = operation["party_domicile_qualification"]
    registry = qualification["registration_evidence"]["record"]
    acquirer = operation["onerous"] is True and operation["acquirer_domiciled_in_brazil"] is True
    party = operation["acquirer_id"] if acquirer else operation["recipient_id"]
    assert report["selected_party_id"] == registry["party_id"] == party
    assert report["destination_basis"] == ("ACQUIRER_DOMICILE" if acquirer else "RECIPIENT_DOMICILE")
    on = date.fromisoformat(operation["operation_date"])
    assert date.fromisoformat(registry["valid_from"]) <= on
    assert registry["valid_until"] is None or date.fromisoformat(registry["valid_until"]) >= on
    method = qualification["method"]
    envelopes = [operation["scope_evidence"], qualification["registration_evidence"]]
    if method == "REGISTERED_NATURAL_PERSON":
        assert registry["regularly_registered"] is True and registry["party_kind"] == "NATURAL_PERSON"
        assert registry["natural_person_subject_to_registration105"] is False
        material = qualification["material_evidence"]["record"]
        envelopes.append(qualification["material_evidence"])
        active = [home for home in material["permanent_homes"] if date.fromisoformat(home["valid_from"]) <= on
                  and (home["valid_until"] is None or date.fromisoformat(home["valid_until"]) >= on)]
        assert material["complete"] is True and material["no_permanent_home"] == (len(active) == 0)
        if len(active) == 1:
            address = normalized(active[0]["address"])
            basis = "PERMANENT_HOME"
        else:
            assert material["economic_relevance_qualified"] is True and material["economic_relevance_qualification_reference"]
            address = normalized(material["economic_relation_location"])
            basis = "MAIN_ECONOMIC_RELATIONS"
        assert address == normalized(registry["domicile_address"])
        assert report["active_permanent_home_count"] == len(active)
    else:
        assert registry["regularly_registered"] is False
        envelopes.extend(qualification["criteria"])
        criteria = {envelope["record"]["criterion_id"]: envelope["record"] for envelope in qualification["criteria"]}
        assert len(criteria) == len(qualification["criteria"])
        if method == "UNREGISTERED_COMBINED_CRITERIA":
            choice = qualification["selection_evidence"]["record"]
            envelopes.append(qualification["selection_evidence"])
            ids = choice["selected_criterion_ids"]
            assert choice["supplier_selection_confirmed"] is True and len(ids) == len(set(ids)) >= 2
            selected = [criteria[key] for key in ids]
            assert len({row["kind"] for row in selected}) >= 2
            assert all(compatible(a["address"], b["address"]) for a, b in itertools.combinations(selected, 2))
            address = normalized(selected[0]["address"])
            components = {}
            for row in selected:
                components.update(dict(row["address"].get("components", [])))
            address["components"] = components or []
            address["granularity"] = "ADDRESS" if any(row["address"]["granularity"] == "ADDRESS" for row in selected) else "MUNICIPALITY"
            assert report["selected_criterion_ids"] == sorted(ids)
            assert report["selected_criterion_kinds"] == sorted({row["kind"] for row in selected})
            for unselected in report["nonselected_criteria"]:
                assert unselected["criterion_id"] not in ids
                row = criteria[unselected["criterion_id"]]
                assert unselected["status"] == ("NOT_SELECTED_CONSISTENT" if compatible(address, row["address"]) else "NOT_SELECTED_CONFLICTING")
            basis = "SELECTED_NONCONFLICTING_CRITERIA"
        else:
            assert method == "UNREGISTERED_DECLARED_FALLBACK"
            proof = qualification["impossibility_evidence"]["record"]
            envelopes.append(qualification["impossibility_evidence"])
            assert proof["criteria_coverage_complete"] is True
            assert len(proof["availability"]) == 4 and {row["kind"] for row in proof["availability"]} == KINDS
            for row in proof["availability"]:
                ids = sorted(key for key, criterion in criteria.items() if criterion["kind"] == row["kind"])
                assert sorted(row["available_criterion_ids"]) == ids and row["available"] == (len(ids) > 0)
                if not ids:
                    assert row["unavailability_reason"] and row["unavailability_evidence_id"]
            pairs = [(a, b) for a, b in itertools.combinations(criteria.values(), 2)
                     if a["kind"] != b["kind"] and compatible(a["address"], b["address"])]
            assert pairs == [] and report["nonconflicting_pairs_available"] == 0
            declared = [row for row in criteria.values() if row["kind"] == "DECLARED_ADDRESS"]
            assert len(declared) == 1
            address = normalized(declared[0]["address"])
            assert report["selected_criterion_ids"] == [declared[0]["criterion_id"]]
            basis = "DECLARED_ADDRESS_PROVEN_IMPOSSIBILITY"
    assert address["in_brazil"] is True and report["domicile_address"] == address and report["domicile_basis"] == basis
    assert report["locality"] == {"uf": address["uf"], "municipality_ibge": address["municipality_ibge"]}
    for envelope in envelopes:
        record = envelope["record"]
        assert envelope["record_hash"] == canonical_hash(record)
        assert record["applicable_on"] == operation["operation_date"]
        assert datetime.fromisoformat(record["observed_at"]) <= datetime.fromisoformat(operation["knowledge_at"])
        for field in ("operation_id", "supply_id", "supplier_id", "acquirer_id", "recipient_id", "onerous",
                      "acquirer_domiciled_in_brazil", "recipient_domiciled_in_brazil"):
            assert record[field] == operation[field]
    for flag in ("official_registry_queried", "source_record_authenticity_proven", "tax_incidence_determined", "credit_right_determined", "production_ready"):
        assert report[flag] is False
    assert report["selected_establishment_id"] is None
    check_hash(report)
    return {"case": case, "method": method, "domicile_basis": basis, "party": party, "locality": report["locality"],
            "independent_address_dates_choice_or_impossibility_and_hashes_checked": True}


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
    assert native["synthetic_personal_records"] is True
    expected = {"home", "no-home", "multiple-homes", "combined", "geo-combined", "fallback", "conflicting-fallback", "foreign-recipient", "nononerous", "unregistered-business"}
    assert len(native["cases"]) == 10 and {row["case"] for row in native["cases"]} == expected
    oracles = []
    for row in native["cases"]:
        assert row["replay"] is True
        observed = oracle(row["report"], row["case"])
        assert observed["locality"]["uf"] == ("RJ" if row["case"] in ("no-home", "multiple-homes") else "SP")
        oracles.append(observed)
    assert (FOLDER / "cli-first.json").read_bytes() == (FOLDER / "cli-second.json").read_bytes()
    assert (FOLDER / "cli-service-first.json").read_bytes() == (FOLDER / "cli-service-second.json").read_bytes()
    for name in ("cli-first.json", "cli-combined.json", "cli-fallback.json"):
        wrapped = load(name)
        assert wrapped["replay_matches"] is True
        oracle(wrapped["report"], name)
    service = load("cli-service-first.json")
    assert service["replay_matches"] is True
    report = service["report"]
    assert report["contract"] == "RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153" and report["status"] == "LOCAL_SERVICE_PARTY_DOMICILE_QUALIFIED"
    assert report["entity_domicile_report"] == report["operation_input"]["entity_domicile_qualification"]
    oracle(report["entity_domicile_report"], "personal_service_linkage")
    check_hash(report)
    invalid = load("cli-invalid.json")["report"]
    assert invalid["status"] == "ACTION_REQUIRED" and invalid["locality"] is None
    assert invalid["reason"] == "DOMICILE153_COMPLETE_IMPOSSIBILITY_PROOF_REQUIRED"
    exits = load("cli-exits.json")
    assert set(exits) == {"native-cases-v1.json", "cli-first.json", "cli-second.json", "cli-combined.json", "cli-fallback.json",
                          "cli-invalid.json", "cli-service-first.json", "cli-service-second.json", "cli-A149-after.json",
                          "cli-A150-after.json", "cli-A151-after.json", "cli-A151-service-after.json", "cli-A152-after.json",
                          "cli-A152-service-after.json"}
    assert all(code == (1 if name == "cli-invalid.json" else 0) for name, code in exits.items())
    legacy = {"cli-A149-after.json": "agent-a-A149-service-locality-20261103-v1/cli-first.json",
              "cli-A150-after.json": "agent-a-A150-territorial-measures-20261103-v1/cli-road-first.json",
              "cli-A151-after.json": "agent-a-A151-entity-domicile-20261103-v1/cli-first.json",
              "cli-A151-service-after.json": "agent-a-A151-entity-domicile-20261103-v1/cli-service-first.json",
              "cli-A152-after.json": "agent-a-A152-centralized-domicile-20261103-v1/cli-first.json",
              "cli-A152-service-after.json": "agent-a-A152-centralized-domicile-20261103-v1/cli-fixed-first.json"}
    for current, previous in legacy.items():
        assert (FOLDER / current).read_bytes() == (Path("D:/ProjetoSaasExports") / previous).read_bytes(), current
    for name in ("directed-v2", "regression-v1", "pint-v2", "phpstan-v2"):
        assert load(name + "-exit.json") == {"exit_code": 0}
    sources = load("sources.json")
    pinned = {"LC214": "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde", "CBS": "8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235"}
    assert {row["name"] for row in sources} == set(pinned)
    for source in sources:
        raw = (FOLDER / (source["name"] + ".html")).read_bytes()
        assert source["status"] == 200 and source["matches_A145_A152"] is True
        assert sha(raw) == source["sha256"] == pinned[source["name"]] and len(raw) == source["bytes"]
    preflight = load("preflight.json")
    assert preflight["A152_receipt_files_match"] is True
    for row in preflight["files"]:
        assert sha((FOLDER / row["before_file"]).read_bytes()) == row["sha256"]
    directed = suite("agent-a-A153-directed-v2.junit.xml", (254, 947))
    regression = suite("agent-a-A153-regression-v1.junit.xml", (508, 1807))
    paths = ["app/Modules/Fiscal/ConsumptionReform/PartyDomicile153.php", "app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php",
             "app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php", "app/Console/Commands/QualifyEntityDomicile151Command.php",
             "app/Console/Commands/QualifyServiceLocality149Command.php", "scripts/agent_a/party153_fixture.php",
             "tests/Fiscal/Integration/PartyDomicile153Test.php", "scripts/agent_a/verify153.py"]
    receipt = {"task": "A153", "scope": "Personal consumer domicile, selected unregistered criteria and proven declared-address fallback",
               "independent_oracles": oracles, "directed_includes_A149_A152_do_not_add": directed,
               "regression_includes_directed_do_not_add": regression, "native_clis_identical_and_replayed": True,
               "legacy_A149_A152_six_cli_bytes_unchanged": True, "expected_missing_impossibility_exit": 1,
               "phpstan_global_exit": 0, "pint_seven_php_files_exit": 0, "sources": sources,
               "files": [{"path": path, "sha256": sha((ROOT / path).read_bytes())} for path in paths],
               "official_registry_queried": False, "source_record_authenticity_proven": False, "B_received": False,
               "classification_catalog_integration": "PENDING_AGENT_B", "whole_requirement_accepted": False,
               "full_regression_executed": False, "production_ready": False,
               "matrix_console_proposal": {"writer": "B", "applied": False, "formal_A_groups_closed": 0,
                   "requirement_ids": ["4B.2.4", "4B.2.5", "4B.2.6", "4B.2.7", "4B.2.10", "4B.3.2"],
                   "classification": "PARCIAL_COM_ACEITE_ESCOPADO_A153", "phase_4B_closed": False,
                   "completed": ["PF consumidora: residência/relevância econômica confrontadas com cadastro",
                                 "Não cadastrados: escolha de critérios vinculados e não conflitantes", "Endereço declarado apenas com impossibilidade comprovada"],
                   "pending": ["PF em contexto de atividade econômica/estabelecimento", "Integração de classificação/projeção e dados reais de B", "Cobertura integral dos 44 e regressão final integrada"],
                   "next_step": "Qualificar o estabelecimento da pessoa física em atividade econômica e revisar as lacunas independentes restantes de A"}}
    preserve_json(ROOT / "docs/fiscal/agent-a/20261103/validation-A153.json", receipt)
    print(json.dumps({"task": "A153", "independent_cases": len(oracles), "directed": directed, "regression": regression,
                      "six_legacy_cli_outputs_byte_identical": True}))


if __name__ == "__main__":
    main()
