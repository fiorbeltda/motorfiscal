"""Fork Phase 4B for documented donation market-value nominal amounts."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-16.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-17"
transition["donation_nominal_calculation"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-17.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-16"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = (
            "Doação com opção de tributação pelo valor de mercado recebe cálculo nominal segregado quando destino "
            "e alíquotas estão comprovados; documento, anulação quantitativa de créditos, outras operações não onerosas "
            "e locais especiais ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-16.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-17"
bundle["version"] = "2026.09.28-17"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T18:45:00-03:00"
bundle["resource_paths"] = previous["resource_paths"].copy()
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = previous["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["content_hashes"] = previous["content_hashes"].copy()
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-17.json", bundle)
print(bundle["bundle_id"])
