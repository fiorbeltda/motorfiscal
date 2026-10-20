"""Verify independent ISS/RTC eligibility, actual 4A, replay and official XML."""
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.02-6"
BUNDLE = f"bundles/consumption-phase4b-{VERSION}.json"


def execute(args):
    process = subprocess.run(["docker", "compose", "exec", "-T", "app", "php", "artisan", *args],
                             cwd=ROOT, capture_output=True, encoding="utf-8", check=False)
    value = json.loads(process.stdout)
    if "error" in value:
        raise RuntimeError(value)
    return value


def save(path, value):
    target = ROOT / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if target.exists() and target.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {target}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding="utf-8")


positive = {"nonprofit-education": ("410007", "3"), "religious-entity": ("410006", "2"),
            "family-payer-with-4a": ("410007", "3")}
negative = {
    "missing-iss-proof": ("ACTION_REQUIRED", "ISS_INSTITUTIONAL_IMMUNITY_PROOF_MISSING"),
    "wrong-iss-owner": ("INVALID", "ISS_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT"),
    "wrong-iss-document": ("INVALID", "ISS_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT"),
    "expired-iss-proof": ("ACTION_REQUIRED", "ISS_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD"),
    "unrelated-iss-service": ("INVALID", "ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_NOT_MET"),
    "missing-essential-evidence": ("ACTION_REQUIRED", "ISS_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:essential_scope_evidence_id"),
    "unknown-essential-scope": ("ACTION_REQUIRED", "ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_UNPROVEN"),
    "conflicting-rtc-treatment": ("INVALID", "ISS_RTC_INSTITUTIONAL_TREATMENT_CONFLICT"),
}
for name in list(positive) + list(negative):
    full_name = f"integrated-institutional-immunity-{name}-2026"
    prefix = f"storage/app/phase4b-integrated-institutional-immunity-{name}-20261002-6"
    snapshot = prefix + "-snapshot.json"
    args = ["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"]
    if name in positive and not (ROOT / snapshot).exists():
        args.append(f"--snapshot={snapshot}")
    value = execute(args)
    if name in positive:
        assert value["status"] == "VALID_WITH_WARNINGS" and value["missing_facts"] == [], value
        decision = value["decision"]
        assert decision["iss"]["incidence"]["value"] == "IMMUNE"
        assert decision["iss"]["calculation"]["tax_amount"] == "0.00"
        assert decision["iss"]["rate"]["rate"] is None
        assert decision["iss"]["municipal_parameters"]["status"] == "NOT_APPLICABLE"
        rtc = decision["consumption_reform"]["decision"]
        assert rtc["classification"]["cclasstrib"] == positive[name][0]
        assert rtc["ibs"]["nominal_amount"] == rtc["cbs"]["nominal_amount"] == "0.00"
        federal = decision["federal_withholdings"]
        if name == "family-payer-with-4a":
            assert federal["status"] == "RESOLVED"
            assert all(federal[tax]["status"] == "NOT_APPLICABLE" for tax in ["irrf", "pcc", "inss"])
            assert federal["total_withholding"] == "0.00"
            assert federal["obligations"]["status"] == "NO_WITHHOLDING_EVENT"
        else:
            assert federal["status"] == "NOT_REQUESTED"
        save(prefix + "-result.json", value)
        assert execute(["fiscal:decision:replay", snapshot])["replay_matches"]
        identity = "resources/fiscal/scenarios/dps-local-2026.10.02-6/family-identity.json" if name == "family-payer-with-4a" else "resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json"
        report_path, candidate = prefix + "-xml-report.json", prefix + "-candidate.xml"
        if (ROOT / report_path).exists():
            report = json.loads((ROOT / report_path).read_text(encoding="utf-8"))
        else:
            report = execute(["fiscal:dps:project-xml", snapshot, identity, f"--bundle-manifest={BUNDLE}", f"--candidate-output={candidate}", f"--report={report_path}"])
        assert report["reason"] == "RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT", report
        assert report["projection_contract"] == "DPS-LOCAL-2026.10.02-1" and report["production_ready"] is False
        errors = report["schema_validation"]["errors"]
        assert len(errors) == 1 and "}serie':" in errors[0]["message"], errors
        xml = ET.parse(ROOT / candidate)
        ns = {"n": "http://www.sped.fazenda.gov.br/nfse"}
        assert xml.findtext(".//n:tribISSQN", namespaces=ns) == "2"
        assert xml.findtext(".//n:tpImunidade", namespaces=ns) == positive[name][1]
        assert xml.find(".//n:pAliq", ns) is None
        print(name, value["decision_hash"])
    else:
        status, reason = negative[name]
        assert value["status"] == status and value["reason_codes"] == [reason], (name, value)
    save(f"resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json",
         {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]})

corpus = execute(["fiscal:corpus:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}",
    f"--expected=resources/fiscal/expected/consumption-integrated-{VERSION}", f"--bundle-manifest={BUNDLE}"])
assert corpus["valid"], corpus
save("storage/app/phase4b-integrated-institutional-immunity-corpus-20261002-6.json", corpus)
gate = execute(["fiscal:rtc:production-assess", f"--bundle-manifest={BUNDLE}",
    f"--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json"])
assert gate["ready"] is False and gate["eligible_for_local_validation"] is True, gate
assert gate["official_source_hashes_valid"] == gate["official_sources_checked"] == 20, gate
save("storage/app/phase4b-production-assessment-20261002-6.json", gate)
print("Verified 11 integrated cases / 3 replays / 3 XML candidates; blockers:", len(gate["blockers"]))
