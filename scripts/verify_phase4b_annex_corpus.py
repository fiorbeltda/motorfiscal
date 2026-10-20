"""Check independently specified annex outcomes before recording immutable goldens."""

import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.01-6"
BUNDLE = f"bundles/consumption-phase4b-{VERSION}.json"


def execute(command):
    completed = subprocess.run(
        ["docker", "compose", "exec", "-T", "app", "php", "artisan", *command],
        cwd=ROOT, capture_output=True, text=True, encoding="utf-8", check=False,
    )
    value = json.loads(completed.stdout)
    if value.get("status") == "ERROR":
        raise RuntimeError(value)
    return value


def capture(family, name, value):
    expected = {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]}
    path = ROOT / f"resources/fiscal/expected/{family}-{VERSION}/{name}.json"
    payload = json.dumps(expected, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {path}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


positive = ["education-presential-2026", "education-remote-2026", "education-special-2026",
            "health-presential-2026", "health-unpaid-glosa-2026", "health-paid-glosa-2026"]
negative = {
    "education-nbs-conflict-2026": ("INVALID", "RTC_ANNEX_SERVICE_NBS_CONFLICT"),
    "health-presence-conflict-2026": ("INVALID", "RTC_PERSON_PRESENCE_DESTINATION_CONFLICT"),
    "education-mixed-consideration-2026": ("ACTION_REQUIRED", "RTC_MIXED_SERVICES_REQUIRE_SEPARATE_TREATMENT"),
}
for year in range(2027, 2034):
    negative[f"education-rate-gap-{year}"] = ("ACTION_REQUIRED", "RTC_RATE_NOT_PROVEN:" + ("CBS" if year < 2029 else "IBS_STATE"))

for name in positive + list(negative):
    value = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}/{name}.json", f"--bundle-manifest={BUNDLE}"])
    if name in positive:
        assert value["status"] == "VALID", (name, value)
        assert value["missing_facts"] == []
        decision = value["decision"]
        glosa = name == "health-unpaid-glosa-2026"
        assert decision["base"]["taxable"] == ("900.00" if glosa else "1000.00")
        assert decision["ibs"]["nominal_amount"] == ("0.36" if glosa else "0.40")
        assert decision["cbs"]["nominal_amount"] == ("3.24" if glosa else "3.60")
        assert decision["classification"]["cclasstrib"] == ("200029" if name.startswith("health") else "200028")
        assert decision["classification"]["cindop"] == ("100301" if "remote" in name else "030101")
        assert decision["document"]["national_enrichment"]["IBSCBS.valores.fed.pRedAliqCBS"] == "60.00"
    else:
        status, reason = negative[name]
        assert value["status"] == status, (name, value)
        assert value["reason_codes"] == [reason], (name, value["reason_codes"])
    capture("consumption-phase4b", name, value)

for name in ["integrated-education-presential-2026", "integrated-health-presential-2026", "integrated-health-unpaid-glosa-2026"]:
    value = execute(["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}/{name}.json", f"--bundle-manifest={BUNDLE}"])
    assert value["status"] == "VALID_WITH_WARNINGS" and value["missing_facts"] == []
    decision = value["decision"]
    assert decision["iss"]["calculation"]["tax_amount"] == "50.00"
    assert decision["iss"]["municipal_parameters"]["source_type"] == "FIXTURE"
    assert decision["federal_withholdings"]["status"] == "NOT_REQUESTED"
    assert decision["dps_projection"]["service"]["cNBS"] == ("122012000" if "education" in name else "123012100")
    rtc = decision["consumption_reform"]["decision"]
    glosa = "unpaid" in name
    assert rtc["base"]["taxable"] == ("900.00" if glosa else "1000.00")
    assert rtc["ibs"]["nominal_amount"] == ("0.36" if glosa else "0.40")
    assert rtc["cbs"]["nominal_amount"] == ("3.24" if glosa else "3.60")
    capture("consumption-integrated", name, value)

print("Verified 16 RTC and 3 integrated annex cases")
