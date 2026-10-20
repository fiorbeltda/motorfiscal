"""Version the NBS-bearing local DPS contract without rewriting captured cut -5."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
version = "2026.10.01-6"


def read(path):
    return json.loads((root / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = root / path
    if target.exists():
        if json.loads(target.read_text(encoding="utf-8")) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.01-5.json")
target = f"bundles/consumption-phase4b-{version}.json"
available = read(target)["knowledge_available_at"] if (root / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes.update(version=version, project_service_nbs=True)
codes["local_xml_projection"]["contract_version"] = "DPS-LOCAL-2026.10.01-2"
write(f"catalogs/rtc-codes-{version}.json", codes)
controls = read(previous["production_controls"]["path"])
controls["version"] = version
write(f"production/phase4b-controls-{version}.json", controls)
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{version}", version=version,
    predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = version
bundle["resource_paths"]["rtc_code_catalog"] = f"catalogs/rtc-codes-{version}.json"
bundle["content_hashes"]["rtc_code_catalog"] = digest(codes)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{version}.json", "sha256": digest(controls)}
write(target, bundle)
write(f"sources/official/phase4b-manifest-{version}.json", {"version": version, "retrieved_on": "2026-10-01", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})
for family in ["consumption-phase4b", "consumption-integrated"]:
    for path in sorted((root / f"scenarios/{family}-2026.10.01-5").glob("*.json")):
        scenario = json.loads(path.read_text(encoding="utf-8"))
        scenario.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
        write(f"scenarios/{family}-{version}/{path.name}", scenario)
identity = read("scenarios/dps-local-2026.10.01-5/presential-identity.json")
write(f"scenarios/dps-local-{version}/presential-identity.json", identity)
print(f"Created {bundle['bundle_id']} with versioned NBS projection")
