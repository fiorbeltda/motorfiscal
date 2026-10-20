"""Verify credit amounts and prohibitions before recording immutable golden hashes."""

import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.02-1"
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
    "missing-treatment": ("ACTION_REQUIRED", "RTC_INPUT_CREDIT_TREATMENT_PROOF_MISSING"),
    "unlinked-document": ("INVALID", "RTC_INPUT_CREDIT_TREATMENT_COMPONENT_INVALID"),
    "duplicate-tax": ("INVALID", "RTC_INPUT_CREDIT_TREATMENT_COMPONENT_INVALID"),
    "presumed-rule-missing": ("ACTION_REQUIRED", "RTC_PRESUMED_CREDIT_RULE_NOT_PROVEN"),
    "zero-positive-claim": ("INVALID", "RTC_INPUT_CREDIT_EXCEEDS_TREATMENT_LIMIT:CBS"),
    "suspension-additions-claim": ("INVALID", "RTC_INPUT_CREDIT_EXCEEDS_TREATMENT_LIMIT:IBS"),
    "suspension-future-extinction": ("INVALID", "RTC_SUSPENDED_INPUT_EXTINCTION_CONFLICT"),
}
positive = {
    **{name: ("ALLOWED", "2.00", "ALLOWED", "18.00") for name in ["standard", "reduced-30", "reduced-60", "suspension-principal-only"]},
    **{name: ("DENIED", "0.00", "DENIED", "0.00") for name in ["immunity", "exempt", "zero-rate", "deferral", "suspension"]},
    "suspension-pending": ("CONDITIONAL", "0.00", "CONDITIONAL", "0.00"),
    "asymmetric": ("ALLOWED", "2.00", "DENIED", "0.00"),
}


def verify_credits(decision, name):
    ibs_status, ibs_amount, cbs_status, cbs_amount = positive[name]
    assert decision["ibs"]["credit"]["status"] == ibs_status
    assert decision["ibs"]["credit"]["amount"] == ibs_amount
    assert decision["cbs"]["credit"]["status"] == cbs_status
    assert decision["cbs"]["credit"]["amount"] == cbs_amount
    assert decision["ibs"]["nominal_amount"] == "0.40"
    assert decision["cbs"]["nominal_amount"] == "3.60"
    if name == "suspension-principal-only":
        assert decision["cbs"]["credit"]["treatment_qualification"]["legal_additions_excluded"] == "2.25"


for name in list(positive) + list(negative):
    full_name = f"input-credit-{name}-2026"
    value = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"])
    if name in positive:
        assert value["status"] == "VALID" and value["missing_facts"] == [], (name, value)
        verify_credits(value["decision"], name)
    else:
        status, reason = negative[name]
        assert value["status"] == status and value["reason_codes"] == [reason], (name, value)
    expected = {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]}
    save(f"resources/fiscal/expected/consumption-phase4b-{VERSION}/{full_name}.json", expected)

for name in ["asymmetric", "suspension-principal-only"]:
    full_name = f"integrated-input-credit-{name}-2026"
    prefix = f"phase4b-input-credit-{name}-20261002-1"
    snapshot = f"storage/app/{prefix}-snapshot.json"
    args = ["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"]
    if not (ROOT / snapshot).exists():
        args.append(f"--snapshot={snapshot}")
    value = execute(args)
    assert value["status"] == "VALID_WITH_WARNINGS" and value["missing_facts"] == []
    assert value["decision"]["iss"]["calculation"]["tax_amount"] == "50.00"
    assert value["decision"]["iss"]["municipal_parameters"]["source_type"] == "FIXTURE"
    assert value["decision"]["federal_withholdings"]["status"] == "NOT_REQUESTED"
    assert value["decision"]["dps_projection"]["service"]["cNBS"] == "123012100"
    verify_credits(value["decision"]["consumption_reform"]["decision"], name)
    save(f"storage/app/{prefix}-result.json", value)
    expected = {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]}
    save(f"resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json", expected)
    assert execute(["fiscal:decision:replay", snapshot])["replay_matches"]
    print(name, value["decision_hash"])

for family, command in [("consumption-phase4b", "fiscal:rtc:corpus"), ("consumption-integrated", "fiscal:corpus:run")]:
    value = execute([command, f"resources/fiscal/scenarios/{family}-{VERSION}", f"--expected=resources/fiscal/expected/{family}-{VERSION}", f"--bundle-manifest={BUNDLE}"])
    assert value["valid"], value
    save(f"storage/app/phase4b-input-credit-{family}-corpus-20261002-1.json", value)

gate = execute(["fiscal:rtc:production-assess", f"--bundle-manifest={BUNDLE}",
                f"--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json"])
assert gate["ready"] is False and gate["eligible_for_local_validation"] is True, gate
save("storage/app/phase4b-production-assessment-20261002-1.json", gate)
print("Verified 18 RTC / 2 integrated cases; production false; blockers:", len(gate["blockers"]))
