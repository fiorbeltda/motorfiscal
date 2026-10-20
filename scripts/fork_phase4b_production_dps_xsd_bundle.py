"""Fork Phase 4B to bind the currently listed production DPS XSD package."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-17.json").read_text(encoding="utf-8"))
field_catalog = json.loads((root / "catalogs/rtc-dps-xsd-fields-production-2026.02.09.json").read_text(encoding="utf-8"))
source_file = root / "sources/official" / field_catalog["source_file"]
if hashlib.sha256(source_file.read_bytes()).hexdigest() != field_catalog["source_sha256"]:
    raise RuntimeError("Official production XSD ZIP hash changed")


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


official_source = {
    "id": "NFSE-PRODUCTION-XSD-20260209",
    "url": "https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/nfse-esquemas_xsd-v1-01-20260209.zip",
    "file": source_file.name,
    "sha256": field_catalog["source_sha256"],
    "version": "v1.01-20260209",
    "published_on": None,
    "retrieved_on": "2026-09-28",
    "proven_effective_scope": (
        "Listed as current production XSD on the official page updated 2026-08-15; "
        "first deployment date not established by that listing"
    ),
    "superseded_by": None,
}
inventory = json.loads((root / "sources/official/phase4b-manifest.json").read_text(encoding="utf-8"))
inventory["version"] = "2026.09.28-18"
inventory["artifacts"] = previous["official_sources"] + [official_source]
inventory_path = root / "sources/official/phase4b-manifest-2026.09.28-18.json"
write_new(inventory_path, inventory)

codes = json.loads((root / previous["resource_paths"]["rtc_code_catalog"]).read_text(encoding="utf-8"))
codes["version"] = "2026.09.28-18"
codes["sources"]["dps_xsd"] = {
    "source_id": official_source["id"],
    "file": source_file.name,
    "sha256": field_catalog["source_sha256"],
    "version": field_catalog["version"],
    "root_schema": field_catalog["root_schema"],
}
codes["dps_xsd"] = {
    "version": field_catalog["version"],
    "source_file": source_file.name,
    "source_sha256": field_catalog["source_sha256"],
    "root_schema": field_catalog["root_schema"],
}
codes["dps_xsd_fields"] = field_catalog["fields"]
for row in codes["document_layouts"]:
    if row["version"] == "NT004-RTC-1.01.03":
        row["xsd_source_hash"] = field_catalog["source_sha256"]
codes_path = root / "catalogs/rtc-codes-2026.09.28-18.json"
write_new(codes_path, codes)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-17"
for row in controls["controls"]:
    if row["id"] == "4B.7.DOCUMENT":
        row["gap"] = (
            "Validador de XML completo vinculado por hash ao XSD oficial de produção disponível; "
            "projeção DPS completa, obrigação por operação/ambiente e implantação NT009 ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-17.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-18"
bundle["version"] = "2026.09.28-18"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T19:00:00-03:00"
bundle["resource_paths"] = previous["resource_paths"].copy()
bundle["resource_paths"]["rtc_code_catalog"] = "catalogs/" + codes_path.name
bundle["catalog_versions"] = previous["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_codes"] = codes["version"]
bundle["content_hashes"] = previous["content_hashes"].copy()
bundle["content_hashes"]["rtc_code_catalog"] = canonical_hash(codes)
bundle["official_sources"] = previous["official_sources"] + [official_source]
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-18.json", bundle)
print(bundle["bundle_id"])
