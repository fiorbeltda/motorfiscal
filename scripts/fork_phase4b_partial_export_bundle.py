"""Fork Phase 4B for documented partial digital exports without rewriting history."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-5.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


facts = json.loads((root / source["resource_paths"]["fact_definitions"]).read_text(encoding="utf-8"))
for key in ["rtc.export_domestic_recipient_id", "rtc.export_domestic_recipient_country", "rtc.export_domestic_document_id"]:
    facts.append({"key": key, "type": "STRING", "material": True, "min_length": 1})
facts_path = root / "facts/consumption-phase4b-2026.09.28-6.json"
write_new(facts_path, facts)

questions = []
for rank, key in enumerate(["rtc.export_domestic_recipient_id", "rtc.export_domestic_recipient_country", "rtc.export_domestic_document_id"], start=240):
    questions.append({"fact_key": key, "prompt": f"Informe e comprove {key} para a parcela consumida no Brasil.",
                      "rank": rank, "answer_type": "STRING"})
question_path = root / "questions/consumption-phase4b-partial-export-2026.09.28-6.json"
write_new(question_path, questions)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-6"
transition["partial_export_domestic_leg"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-6.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-5"
for row in controls["controls"]:
    if row["id"] == "4B.2.EXPORT_IMPORT":
        row["gap"] = "Exportação parcial digital: papéis, destino e valores nominais da parcela brasileira qualificados; obrigação documental e revisão da classificação ainda pendentes. Importação: documento definitivo pendente."
controls_path = root / "production/phase4b-controls-2026.09.28-5.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-6"
bundle["version"] = "2026.09.28-6"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T12:03:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = source["question_overlays"] + ["questions/" + question_path.name]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
all_questions = json.loads((root / source["resource_paths"]["questions"]).read_text(encoding="utf-8"))
for overlay in bundle["question_overlays"]:
    all_questions += json.loads((root / overlay).read_text(encoding="utf-8"))
bundle["content_hashes"]["questions"] = canonical_hash(all_questions)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-6.json", bundle)
print(bundle["bundle_id"])
