"""Check independently calculated period amounts before recording golden hashes."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.02-3"
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


positive = {
    "mixed": ("30.00", "70.00", "270.00", "630.00"),
    **{name: ("0.00", "100.00", "0.00", "900.00") for name in
       ["zero-preserved", "reduced-preserved", "export-preserved", "books-preserved", "broadcast-preserved"]},
    "fully-immune": ("100.00", "0.00", "900.00", "0.00"),
    "asymmetric": ("40.00", "60.00", "0.00", "900.00"),
    "adjusted": ("27.00", "63.00", "270.00", "630.00"),
    "rational-rounding": ("0.02", "0.03", "0.03", "0.07"),
}
negative = {
    "incomplete": ("ACTION_REQUIRED", "RTC_SUPPLIER_CREDIT_PERIOD_LEDGER_INCOMPLETE"),
    "wrong-total": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_TOTAL_CONFLICT"),
    "duplicate-output": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_OUTPUT_INVALID"),
    "wrong-owner": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_OWNER_OR_CURRENCY_CONFLICT"),
    "future-period": ("ACTION_REQUIRED", "RTC_SUPPLIER_CREDIT_PERIOD_NOT_CLOSED"),
    "zero-turnover": ("ACTION_REQUIRED", "RTC_SUPPLIER_CREDIT_PERIOD_ZERO_TURNOVER_POLICY_REQUIRED"),
    "special-policy": ("ACTION_REQUIRED", "RTC_SUPPLIER_CREDIT_OUTPUT_POLICY_NOT_PROVEN:IBS"),
    "adjustment-excess": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_EXCEEDS_ENTRY"),
    "adjustment-future": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_ADJUSTMENT_DATE_CONFLICT"),
    "already-annulled": ("INVALID", "RTC_SUPPLIER_CREDIT_PERIOD_ALREADY_ANNULLED"),
}


def verify(decision, name):
    taxes = decision["supplier_credit_period"]["taxes"]
    actual = tuple(taxes[tax][field] for tax in ["IBS", "CBS"]
                   for field in ["annulled_credit", "maintained_credit"])
    assert actual == positive[name], (name, actual)
    assert decision["ibs"]["nominal_amount"] == "0.40"
    assert decision["cbs"]["nominal_amount"] == "3.60"


for name in list(positive) + list(negative):
    full_name = f"supplier-credit-period-{name}-2026"
    value = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"])
    if name in positive:
        assert value["status"] == "VALID" and value["missing_facts"] == [], (name, value)
        verify(value["decision"], name)
    else:
        status, reason = negative[name]
        assert value["status"] == status and value["reason_codes"] == [reason], (name, value)
    save(f"resources/fiscal/expected/consumption-phase4b-{VERSION}/{full_name}.json",
         {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]})

for name in ["mixed", "asymmetric", "adjusted"]:
    full_name = f"integrated-supplier-credit-period-{name}-2026"
    prefix = f"phase4b-supplier-credit-period-{name}-20261002-3"
    snapshot = f"storage/app/{prefix}-snapshot.json"
    args = ["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}/{full_name}.json", f"--bundle-manifest={BUNDLE}"]
    if not (ROOT / snapshot).exists():
        args.append(f"--snapshot={snapshot}")
    value = execute(args)
    assert value["status"] == "VALID_WITH_WARNINGS" and value["missing_facts"] == [], value
    assert value["decision"]["iss"]["calculation"]["tax_amount"] == "50.00"
    assert value["decision"]["iss"]["municipal_parameters"]["source_type"] == "FIXTURE"
    assert value["decision"]["federal_withholdings"]["status"] == "NOT_REQUESTED"
    assert value["decision"]["dps_projection"]["service"]["cNBS"] == "123012100"
    verify(value["decision"]["consumption_reform"]["decision"], name)
    save(f"storage/app/{prefix}-result.json", value)
    save(f"resources/fiscal/expected/consumption-integrated-{VERSION}/{full_name}.json",
         {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]})
    assert execute(["fiscal:decision:replay", snapshot])["replay_matches"]
    print(name, value["decision_hash"])

for family, command in [("consumption-phase4b", "fiscal:rtc:corpus"), ("consumption-integrated", "fiscal:corpus:run")]:
    value = execute([command, f"resources/fiscal/scenarios/{family}-{VERSION}", f"--expected=resources/fiscal/expected/{family}-{VERSION}", f"--bundle-manifest={BUNDLE}"])
    assert value["valid"], value
    save(f"storage/app/phase4b-supplier-credit-period-{family}-corpus-20261002-3.json", value)

gate = execute(["fiscal:rtc:production-assess", f"--bundle-manifest={BUNDLE}",
                f"--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json"])
assert gate["ready"] is False and gate["eligible_for_local_validation"] is True, gate
save("storage/app/phase4b-production-assessment-20261002-3.json", gate)
print("Verified 20 RTC / 3 integrated cases; production false; blockers:", len(gate["blockers"]))
