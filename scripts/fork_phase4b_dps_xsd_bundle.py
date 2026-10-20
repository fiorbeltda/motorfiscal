"""Fork Phase 4B with NT004 issuer field checks derived from its official XSD."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-9.json").read_text(encoding="utf-8"))
field_catalog = json.loads((root / "catalogs/rtc-dps-xsd-fields-nt004-2025.12.10.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


codes = json.loads((root / source["resource_paths"]["rtc_code_catalog"]).read_text(encoding="utf-8"))
codes["version"] = "2026.09.28-10"
codes["sources"]["dps_xsd"] = {
    "file": field_catalog["source_file"],
    "sha256": field_catalog["source_sha256"],
    "version": field_catalog["version"],
    "root_schema": field_catalog["root_schema"],
}
codes["dps_xsd"] = {
    "version": field_catalog["version"],
    "source_sha256": field_catalog["source_sha256"],
    "root_schema": field_catalog["root_schema"],
}
codes["dps_xsd_fields"] = field_catalog["fields"]
for row in codes["document_layouts"]:
    if row["version"] == "NT004-RTC-1.01.03":
        row["xsd_source_hash"] = field_catalog["source_sha256"]
codes_path = root / "catalogs/rtc-codes-2026.09.28-10.json"
write_new(codes_path, codes)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-9"
for row in controls["controls"]:
    if row["id"] == "4B.7.DOCUMENT":
        row["gap"] = (
            "Campos projetados pelo emissor para NT004 validados por caminho e restrições simples do XSD oficial; "
            "XML completo, obrigação de importação/exportação parcial e implantação/vigência de NT009 ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-9.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-10"
bundle["version"] = "2026.09.28-10"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T14:30:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["rtc_code_catalog"] = "catalogs/" + codes_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_codes"] = codes["version"]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["rtc_code_catalog"] = canonical_hash(codes)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-10.json", bundle)
print(bundle["bundle_id"])
