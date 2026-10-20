"""Fork Phase 4B candidate with the published 2027 second-semester Simples window."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source_bundle = root / "bundles/consumption-phase4b-2026.09.28-1.json"
target_bundle = root / "bundles/consumption-phase4b-2026.09.28-2.json"
source_catalog = root / "catalogs/rtc-transition-2026.09.25-1.json"
target_catalog = root / "catalogs/rtc-transition-2026.09.28-2.json"
source_manifest = root / "sources/official/phase4b-manifest.json"
target_manifest = root / "sources/official/phase4b-manifest-2026.09.28-2.json"
official_file = root / "sources/official/cgsn-simples-2027-options-govbr-2026.html"
official_hash = hashlib.sha256(official_file.read_bytes()).hexdigest()
source = {
    "id": "CGSN-SIMPLES-OPTIONS-2027-GOVBR-2026",
    "url": "https://www.gov.br/memp/pt-br/assuntos/noticias/cgsn-atualiza-regras-do-simples-nacional-para-adequacao-a-reforma-tributaria-do-consumo",
    "file": official_file.name,
    "sha256": official_hash,
    "version": "2026-08 official CGSN 190/191 explanation",
    "retrieved_on": "2026-09-28",
    "proven_effective_scope": "2027 second-semester regular IBS/CBS option: filing 01-31 March 2027, cancellation through 31 May 2027",
    "superseded_by": None,
}


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite a changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


catalog = json.loads(source_catalog.read_text(encoding="utf-8"))
catalog["version"] = "2026.09.28-2"
catalog["simples_option_windows"].append({
    "valid_from": "2027-07-01",
    "valid_to": "2027-12-31",
    "filing_from": "2027-03-01",
    "filing_to": "2027-03-31",
    "cancellation_through": "2027-05-31",
    "published_at": "2026-08-12T09:41:00-03:00",
    "status": "ACTIVE",
    "source_id": source["id"],
    "source_hash": official_hash,
})
write_new(target_catalog, catalog)

manifest = json.loads(source_manifest.read_text(encoding="utf-8"))
manifest["version"] = "2026.09.28-2"
manifest["artifacts"].append(source)
write_new(target_manifest, manifest)

bundle = json.loads(source_bundle.read_text(encoding="utf-8"))
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-2"
bundle["version"] = "2026.09.28-2"
bundle["knowledge_available_at"] = "2026-09-28T11:30:00-03:00"
bundle["predecessor_bundle_id"] = source_bundle.stem.upper()
bundle["catalog_versions"]["rtc_transition"] = catalog["version"]
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + target_catalog.name
bundle["content_hashes"]["rtc_transition_catalog"] = hashlib.sha256(
    json.dumps(catalog, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
).hexdigest()
bundle["official_sources"].append(source)
write_new(target_bundle, bundle)
print(bundle["bundle_id"], official_hash)
