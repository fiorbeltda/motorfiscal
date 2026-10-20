"""Fork Phase 4B with the LC 116 frozen 2028 ISS rate transition."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-4.json").read_text(encoding="utf-8"))
official_file = root / "sources/official/lc116-camara-2026-09-28.html"
official_hash = hashlib.sha256(official_file.read_bytes()).hexdigest()
official = {
    "id": "LC116-CAMARA-20260928",
    "url": "https://www2.camara.leg.br/legin/fed/leicom/2003/leicomplementar-116-31-julho-2003-492028-normaatualizada-pl.html",
    "file": official_file.name,
    "sha256": official_hash,
    "version": "consolidated through 2026-09-28 retrieval",
    "retrieved_on": "2026-09-28",
    "proven_effective_scope": "LC 116 art. 8-B: 2029-2032 ISS factors applied to municipal rate effective on 2028-12-31",
    "superseded_by": None,
}


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
transition["version"] = "2026.09.28-5"
transition["iss_frozen_transition"] = True
transition["iss_frozen_law_source_id"] = official["id"]
transition["iss_frozen_law_source_hash"] = official_hash
transition_path = root / "catalogs/rtc-transition-2026.09.28-5.json"
write_new(transition_path, transition)

official_manifest = json.loads((root / "sources/official/phase4b-manifest-2026.09.28-2.json").read_text(encoding="utf-8"))
official_manifest["version"] = "2026.09.28-5"
official_manifest["artifacts"].append(official)
write_new(root / "sources/official/phase4b-manifest-2026.09.28-5.json", official_manifest)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-4"
for row in controls["controls"]:
    if row["id"] == "4B.1.TEMPORALITY":
        row["gap"] = "ISS 2029-2032 exige alíquota municipal oficial vigente em 31/12/2028; CBS 2027+ e IBS por destino 2029+ sem alíquotas comprovadas; 2033 ainda sem apuração conjunta."
    if row["id"] == "INTEGRATED.ISS_MUNICIPAL":
        row["gap"] = "O bundle usa parâmetros municipais sintéticos; a transição exige fonte municipal oficial para 31/12/2028 e parâmetros atuais de responsabilidade."
controls_path = root / "production/phase4b-controls-2026.09.28-4.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-5"
bundle["version"] = "2026.09.28-5"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T11:48:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["official_sources"] = source["official_sources"] + [official]
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-5.json", bundle)
print(bundle["bundle_id"], official_hash)
