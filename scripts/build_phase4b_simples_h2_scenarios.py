"""Create review scenarios for the published second-semester 2027 Simples option."""

import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/simples-regular-h1-2027-confirmed.json").read_text(encoding="utf-8"))
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-2.json").read_text(encoding="utf-8"))
directory = root / "scenarios/consumption-phase4b-2026.09.28-2"
directory.mkdir(exist_ok=True)

for name, filed_on in [
    ("simples-regular-h2-2027-confirmed", "2027-03-20"),
    ("simples-regular-h2-2027-outside-window", "2027-04-01"),
]:
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["competence_date"] = "2027-10-10"
    scenario["knowledge_at"] = "2027-10-10T10:00:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"]["rtc.operation_date"] = "2027-10-10"
    scenario["facts"]["rtc.simples_option_filed_on"] = filed_on
    path = directory / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed scenario: {path}")
    path.write_text(payload, encoding="utf-8")
    print(path)

    command = ["docker", "compose", "exec", "-T", "app", "php", "artisan", "fiscal:rtc:run",
               f"resources/fiscal/scenarios/consumption-phase4b-2026.09.28-2/{name}.json",
               "--bundle-manifest=bundles/consumption-phase4b-2026.09.28-2.json"]
    completed = subprocess.run(command, cwd=root.parents[1], capture_output=True, text=True, check=True)
    result = json.loads(completed.stdout)
    expected_reason = "RTC_RATE_NOT_PROVEN:CBS" if "confirmed" in name else "RTC_SIMPLES_OPTION_OUTSIDE_STANDARD_WINDOW"
    if result["status"] != "ACTION_REQUIRED" or result["reason_codes"] != [expected_reason]:
        raise RuntimeError(f"Unexpected result for {name}: {result['status']} {result['reason_codes']}")
    expected = {key: result[key] for key in ("status", "reason_codes", "missing_facts", "decision_hash")}
    expected_path = root / "expected/consumption-phase4b-2026.09.28-2" / f"{name}.json"
    expected_path.parent.mkdir(exist_ok=True)
    expected_payload = json.dumps(expected, ensure_ascii=False, indent=2) + "\n"
    if expected_path.exists() and expected_path.read_text(encoding="utf-8") != expected_payload:
        raise RuntimeError(f"Refusing to overwrite changed expected result: {expected_path}")
    expected_path.write_text(expected_payload, encoding="utf-8")
