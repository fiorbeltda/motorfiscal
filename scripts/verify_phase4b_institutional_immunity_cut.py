"""Check supplier eligibility and immunity amounts before preserving golden hashes."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.02-4"
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


negative = {
    "missing-proof": ("ACTION_REQUIRED", "RTC_INSTITUTIONAL_IMMUNITY_PROOF_MISSING"),
    "wrong-supplier": ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT"),
    "wrong-document": ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT"),
    "future-observation": ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_PERIOD_CONFLICT"),
    "expired-proof": ("ACTION_REQUIRED", "RTC_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD"),
    **{name: ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:" + key) for name, key in {
        "distribution": "no_asset_or_income_distribution", "resources-abroad": "resources_applied_in_brazil",
        "bookkeeping": "formal_accurate_bookkeeping", "unrelated-service": "service_directly_related_to_statutory_objectives",
        "suspended-benefit": "benefit_not_suspended", "for-profit": "nonprofit",
        "not-religious": "professes_faith_and_practices_religion"}.items()},
    "employers-union": ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_CATEGORY_NOT_ELIGIBLE"),
    "missing-bookkeeping-evidence": ("ACTION_REQUIRED", "RTC_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:bookkeeping_evidence_id"),
    "unknown-condition": ("ACTION_REQUIRED", "RTC_INSTITUTIONAL_IMMUNITY_CONDITION_UNPROVEN:resources_applied_in_brazil"),
    "wrong-rule": ("INVALID", "RTC_INSTITUTIONAL_IMMUNITY_RULE_CONFLICT"),
}
positive = {"nonprofit-education": "410007", "religious-entity": "410006"}
for name in list(positive) + list(negative):
    full_name = f"institutional-immunity-{name}-2026"
    snapshot = f"storage/app/phase4b-institutional-immunity-{name}-20261002-4-snapshot.json"
    args = ["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"]
    if name in positive and not (ROOT / snapshot).exists():
        args.append(f"--snapshot={snapshot}")
    value = execute(args)
    if name in positive:
        decision = value["decision"]
        assert value["status"] == "VALID" and value["reason_codes"] == ["RTC_INSTITUTIONAL_IMMUNITY"]
        assert decision["classification"]["cclasstrib"] == positive[name]
        assert decision["ibs"]["status"] == decision["cbs"]["status"] == "IMMUNE"
        assert decision["ibs"]["nominal_amount"] == decision["cbs"]["nominal_amount"] == "0.00"
        assert decision["ibs"]["state_rate_percent"] is None and decision["cbs"]["federal_rate_percent"] is None
        assert decision["base"]["taxable"] == "0.00" and decision["document"]["national_enrichment"] == []
        assert decision["document"]["issuer_field_validation"]["status"] == "VALID"
        save(f"storage/app/phase4b-institutional-immunity-{name}-20261002-4-result.json", value)
        assert execute(["fiscal:rtc:replay", snapshot])["replay_matches"]
        print(name, value["decision_hash"])
    else:
        status, reason = negative[name]
        assert value["status"] == status and value["reason_codes"] == [reason], (name, value)
    save(f"resources/fiscal/expected/consumption-phase4b-{VERSION}/{full_name}.json",
         {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]})

corpus = execute(["fiscal:rtc:corpus", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}",
    f"--expected=resources/fiscal/expected/consumption-phase4b-{VERSION}", f"--bundle-manifest={BUNDLE}"])
assert corpus["valid"], corpus
save("storage/app/phase4b-institutional-immunity-rtc-corpus-20261002-4.json", corpus)
gate = execute(["fiscal:rtc:production-assess", f"--bundle-manifest={BUNDLE}",
    f"--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json"])
assert gate["ready"] is False and gate["eligible_for_local_validation"] is True, gate
assert gate["official_source_hashes_valid"] == gate["official_sources_checked"] == 19, gate
save("storage/app/phase4b-production-assessment-20261002-4.json", gate)
print("Verified 18 RTC cases / 2 replays; production false; blockers:", len(gate["blockers"]))
