"""Build scenarios covering issuer fields checked against the NT004 XSD."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-10.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-10"
target.mkdir(exist_ok=True)
cases = {
    "regular-saas-nt004-xsd-2026": "scenarios/consumption-phase4b/regular-saas-2026.json",
    "import-nt004-xsd-document-pending-2026": "scenarios/consumption-phase4b-2026.09.28-1/import-digital-2026-document-gap.json",
}
for name, relative_path in cases.items():
    scenario = json.loads((root / relative_path).read_text(encoding="utf-8"))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T14:31:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed scenario: {path}")
    path.write_text(payload, encoding="utf-8")
    print(path.name)
