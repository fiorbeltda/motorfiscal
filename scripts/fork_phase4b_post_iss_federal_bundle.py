"""Fork Phase 4B with guarded federal evaluation after ISS extinction."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-8.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-9"
transition["post_iss_federal_withholdings"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-9.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-8"
for row in controls["controls"]:
    if row["id"] == "INTEGRATED.4A":
        row["gap"] = (
            "Avaliação federal após 2032 encaminhada à 4A com prova de data e veto a PIS/Cofins "
            "legados; perfis públicos/cooperativos, obrigações temporais e atos locais ainda exigem revisão."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-8.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-9"
bundle["version"] = "2026.09.28-9"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T13:45:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-9.json", bundle)
print(bundle["bundle_id"])
