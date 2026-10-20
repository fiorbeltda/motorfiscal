"""Build integrated 2033 scenarios pinned to the 2026.09.28-7 bundle."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2033-rate-gap.json").read_text(encoding="utf-8"))
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-7.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-7"
target.mkdir(exist_ok=True)

cases = {
    "regular-2033-rate-gap": {"rtc.legacy_event_spans_transition": False},
    "regular-2033-legacy-timing-missing": {},
    "regular-2033-cross-year": {"rtc.legacy_event_spans_transition": True},
    "regular-2033-rtc-skipped": {"rtc.legacy_event_spans_transition": False, "rtc.evaluate": False},
    "regular-2033-relevant-date-2032": {"rtc.legacy_event_spans_transition": False,
                                        "rtc.operation_date": "2032-12-31"},
}

for name, facts in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2033-02-02T10:00:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(facts)
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed scenario: {path}")
    path.write_text(payload, encoding="utf-8")
    print(path.name)
