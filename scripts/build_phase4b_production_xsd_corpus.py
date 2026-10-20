"""Build RTC replay cases bound to the production DPS XSD source catalog."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-18.json").read_text(encoding="utf-8"))
source_paths = {
    "regular-saas-production-xsd-2026": root / "scenarios/consumption-phase4b/regular-saas-2026.json",
    "import-digital-production-xsd-2026": root / "scenarios/consumption-phase4b-2026.09.28-1/import-digital-2026-document-gap.json",
}
target = root / "scenarios/consumption-phase4b-2026.09.28-18"
target.mkdir(exist_ok=True)
for name, source_path in source_paths.items():
    scenario = json.loads(source_path.read_text(encoding="utf-8"))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T19:01:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")
print(len(source_paths))
