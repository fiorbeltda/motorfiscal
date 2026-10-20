"""Fork the professional cut with explicit municipal sandbox fixtures and integrated cases."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
version = "2026.10.01-4"


def read(path):
    return json.loads((root / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = root / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if target.exists():
        if json.loads(target.read_text(encoding="utf-8")) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(payload, encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.01-3.json")
target = f"bundles/consumption-phase4b-{version}.json"
available = read(target)["knowledge_available_at"] if (root / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
municipal = read(previous["resource_paths"]["municipal_parameters"])
municipal.update(catalog_id="MUNICIPAL_PROFESSIONAL_SANDBOX_FIXTURE", version=version,
                 notice="Synthetic professional service rates for integrated regression only; no municipal act or production approval.")
for code in ["171401", "171901"]:
    municipal["parameters"].append({"municipality_ibge": "3550308", "ctribnac_code": code,
        "effective_from": "2026-01-01", "effective_to": None, "rate": "5.00", "responsibility": "PROVIDER"})
write(f"catalogs/municipal-professional-sandbox-{version}.json", municipal)
controls = read(previous["production_controls"]["path"])
controls["version"] = version
write(f"production/phase4b-controls-{version}.json", controls)
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{version}", version=version,
              predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["municipal_parameters"] = version
bundle["resource_paths"]["municipal_parameters"] = f"catalogs/municipal-professional-sandbox-{version}.json"
bundle["content_hashes"]["municipal_parameters"] = digest(municipal)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{version}.json", "sha256": digest(controls)}
write(target, bundle)
write(f"sources/official/phase4b-manifest-{version}.json", {"version": version, "retrieved_on": "2026-10-01",
    "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

for path in sorted((root / "scenarios/consumption-phase4b-2026.10.01-3").glob("*.json")):
    scenario = json.loads(path.read_text(encoding="utf-8"))
    scenario.update(rule_bundle_id=bundle["bundle_id"], knowledge_at=available, catalog_versions=bundle["catalog_versions"])
    scenario["facts"] = {key: value for key, value in scenario["facts"].items() if value is not None}
    if scenario["facts"].get("provider.person_type") == "INDIVIDUAL":
        scenario["facts"].pop("provider.tax_regime", None)
        scenario["facts"]["rtc.supplier_id"] = "12345678901"
    write(f"scenarios/consumption-phase4b-{version}/{path.name}", scenario)
for name in ["lawyer-reduced-2026", "accountant-reduced-2026"]:
    scenario = read(f"scenarios/consumption-phase4b-{version}/{name}.json")
    scenario["scenario_id"] = f"integrated-{name}"
    # Use the validated 4A fact contract of the existing integrated scenario.
    federal = read("scenarios/consumption-integrated-2026.10.01-2/dps-integrated-iss-4a-rtc-2026.json")
    for key, value in federal["facts"].items():
        if key.startswith(("federal.", "payer.", "payment.", "invoice.", "inss.")):
            scenario["facts"][key] = value
    scenario["facts"]["inss.service_category"] = "OTHER"
    write(f"scenarios/consumption-integrated-{version}/{scenario['scenario_id']}.json", scenario)
identity = read("scenarios/dps-local-2026.10.01-2/federal-identity.json")
write(f"scenarios/dps-local-{version}/federal-identity.json", identity)
print(f"Created {bundle['bundle_id']} with 7 RTC cases, 2 integrated cases and explicitly synthetic municipal parameters")
