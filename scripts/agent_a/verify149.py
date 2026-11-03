"""Check native service-locality outputs, bound-record hashes and preserved primary source bytes."""
import hashlib
import json
from datetime import datetime
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A149-service-locality-20261103-v1")
EXPECTED = {
    "IMMOVABLE_RELATED": ("II", "PROPERTY_LOCATION"), "PERSONAL_PRESENTIAL": ("III", "PERSONAL_PERFORMANCE"),
    "EVENT_ORGANIZATION": ("IV", "EVENT_LOCATION"), "MOVABLE_PHYSICAL_SERVICE": ("V", "PHYSICAL_PERFORMANCE"),
    "PORT_SERVICE": ("V", "PORT_LOCATION"), "PASSENGER_TRANSPORT": ("VI", "TRANSPORT_START"),
    "CARGO_TRANSPORT": ("VII", "DOCUMENTED_DELIVERY"), "FIXED_COMMUNICATION": ("IX", "TERMINAL_INSTALLATION"),
}


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical_hash(value):
    return sha(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode("utf-8"))


def load(filename):
    return json.loads((FOLDER / filename).read_text(encoding="utf-8-sig"))


def main():
    rows = load("families.json")["families"]
    assert len(rows) == len(EXPECTED) == 8
    for row in rows:
        report = row["result"]
        inciso, role = EXPECTED[row["category"]]
        assert report["status"] == "LOCAL_SERVICE_LOCATION_QUALIFIED" and row["replay"] is True
        assert report["source_article"] == "LC214 art.11 " + inciso and report["location_role"] == role
        assert report["locality"] == {"uf": "SP", "municipality_ibge": "3550308"}
        assert all(report[key] is False for key in ("cIndOp_selected", "tax_rate_selected", "credit_right_determined",
                   "source_record_authenticity_proven", "historical_juridical_validity_proven", "production_ready"))
        operation = report["operation_input"]
        assert report["input_hash"] == canonical_hash(operation)
        for envelope in (operation["scope_evidence"], operation["locations"][0]["evidence"]):
            record = envelope["record"]
            assert envelope["record_hash"] == canonical_hash(record)
            for key in ("operation_id", "supply_id", "supplier_id", "recipient_id"):
                assert record[key] == operation[key]
            assert record["applicable_on"] == operation["operation_date"]
            assert datetime.fromisoformat(record["observed_at"]) <= datetime.fromisoformat(operation["knowledge_at"])
        claimed = report["report_hash"]
        payload = dict(report)
        del payload["report_hash"]
        assert claimed == canonical_hash(payload)
    assert (FOLDER / "cli-first.json").read_bytes() == (FOLDER / "cli-second.json").read_bytes()
    cli = load("cli-first.json")
    assert cli["replay_matches"] is True and cli["report"]["location_role"] == "DOCUMENTED_DELIVERY"
    negative = load("cli-invalid-document.json")
    assert negative["report"]["reason"] == "LOCALITY149_CARGO_DOCUMENT_BINDING_UNPROVEN"
    assert negative["report"]["locality"] is None
    assert load("cli-exits.json") == {"families": 0, "first": 0, "second": 0, "wrong_document": 1}
    assert load("quality-exits.json") == {"phpstan": 0, "pint": 0}
    assert "PASS" in (FOLDER / "pint-final-v2.log").read_text(encoding="utf-8-sig")
    primary_folder = Path("D:/ProjetoSaasExports/agent-a-A145-combined-supplies-20261103-v1")
    sources = [json.loads(line) for line in (primary_folder / "sources.jsonl").read_text(encoding="utf-8-sig").splitlines()]
    primary = next(source for source in sources if source["id"] == "LC214")
    assert primary["sha256"] == sha(Path(primary["path"]).read_bytes()) == cli["report"]["source"]["sha256"]
    assert primary["http_status"] == 200
    junit = ROOT / "storage/app/agent-a-A149-final-v2.junit.xml"
    tree = ElementTree.parse(junit)
    assert not tree.findall(".//failure") and not tree.findall(".//error")
    cases = tree.findall(".//testcase")
    preserve_bytes(FOLDER / "directed-final-v2.junit.xml", junit.read_bytes())
    receipt = {"task": "A149", "scope": "4B.2 material locality of eight special service families",
               "family_rule_results": [{"category": row["category"], "article": row["result"]["source_article"],
                                        "role": row["result"]["location_role"], "replay": row["replay"]} for row in rows],
               "tests": len(cases), "assertions": sum(int(case.get("assertions", "0")) for case in cases),
               "junit_sha256": sha(junit.read_bytes()), "identical_cli_runs": True,
               "python_canonical_record_and_report_hashes_verified": True,
               "primary_source": primary, "quality_exit_codes": load("quality-exits.json"),
               "implementation_sha256": sha((ROOT / "app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php").read_bytes()),
               "command_sha256": sha((ROOT / "app/Console/Commands/QualifyServiceLocality149Command.php").read_bytes()),
               "cli_sha256": sha((FOLDER / "cli-first.json").read_bytes()),
               "classification_catalog_integration": "PENDING_AGENT_B", "whole_requirement_accepted": False,
               "source_record_authenticity_proven": False, "full_regression_executed": False, "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A149.json"
    preserve_json(destination, receipt)
    print(json.dumps({"task": "A149", "families": len(rows), "tests": len(cases), "assertions": receipt["assertions"],
                      "python_hashes_verified": True, "cli_replay": True}))


if __name__ == "__main__":
    main()
