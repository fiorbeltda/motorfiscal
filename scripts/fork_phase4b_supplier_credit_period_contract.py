"""Bind one period as a singleton ARRAY, preserving the invalid object draft -2."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-3"


def read(path):
    return json.loads((ROOT / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.02-2.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
codes["supplier_credit_period"].update(input_contract="SINGLE_PERIOD_ARRAY", published_at=available,
                                       source_article="LC214 arts.43, 47, §10, 51, 52 e 54")
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
              predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = VERSION
bundle["resource_paths"]["rtc_code_catalog"] = f"catalogs/rtc-codes-{VERSION}.json"
bundle["content_hashes"]["rtc_code_catalog"] = digest(codes)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
write(bundle["resource_paths"]["rtc_code_catalog"], codes)
write(bundle["production_controls"]["path"], controls)
write(target, bundle)
write(f"sources/official/phase4b-manifest-{VERSION}.json", {"version": VERSION, "retrieved_on": "2026-10-02", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})
for family in ["consumption-phase4b", "consumption-integrated"]:
    for path in sorted((ROOT / f"scenarios/{family}-2026.10.02-2").glob("*.json")):
        scenario = json.loads(path.read_text(encoding="utf-8"))
        scenario.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
        scenario["facts"]["rtc.supplier_credit_period"] = [scenario["facts"]["rtc.supplier_credit_period"]]
        write(f"scenarios/{family}-{VERSION}/{path.name}", scenario)
print(f"Created {bundle['bundle_id']} with a singleton monthly period array")
