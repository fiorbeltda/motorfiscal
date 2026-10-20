"""Fork the Phase 4B candidate for evidenced collection-method eligibility."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-2.json").read_text(encoding="utf-8"))


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
new_facts = [
    {"key": "rtc.payment_instrument_split_capable", "type": "BOOLEAN", "material": True},
    {"key": "rtc.payment_instrument_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.payment_transaction_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.split_deployment_evidence_id", "type": "STRING", "material": True, "min_length": 1},
]
facts.extend(new_facts)
facts_path = root / "facts/consumption-phase4b-2026.09.28-3.json"
write_new(facts_path, facts)

questions_path = root / "questions/consumption-phase4b-collection-2026.09.28-3.json"
questions = []
for rank, fact in enumerate(new_facts, 340):
    questions.append({
        "fact_key": fact["key"],
        "prompt": "Informe a evidência de " + fact["key"] + " para qualificar o recolhimento IBS/CBS.",
        "rank": rank,
        "answer_type": fact["type"],
    })
write_new(questions_path, questions)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-3"
transition["collection_qualification"] = True
transition["split_deployments"] = []
transition_path = root / "catalogs/rtc-transition-2026.09.28-3.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-2"
for row in controls["controls"]:
    if row["id"] == "4B.3.SIMPLES_MEI":
        row["gap"] = "Janelas de opção regular de ambos os semestres de 2027 qualificadas; apuração interna, início de atividade, MEI e anos seguintes ainda pendentes."
    if row["id"] == "4B.3.SUBJECTS_AND_COLLECTION":
        row["state"] = "PARTIAL"
        row["gap"] = "Elegibilidade de split e recolhimento pelo adquirente qualificada, sem afirmar quitação; implantação de split, plataforma e aquisição pública ainda pendentes."
controls_path = root / "production/phase4b-controls-2026.09.28-2.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-3"
bundle["version"] = "2026.09.28-3"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T11:32:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = source["question_overlays"] + ["questions/" + questions_path.name]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
all_questions = []
for path in [bundle["resource_paths"]["questions"], *bundle["question_overlays"]]:
    all_questions.extend(json.loads((root / path).read_text(encoding="utf-8")))
bundle["content_hashes"]["questions"] = canonical_hash(all_questions)
bundle["production_controls"] = {
    "path": "production/" + controls_path.name,
    "sha256": canonical_hash(controls),
}
bundle_path = root / "bundles/consumption-phase4b-2026.09.28-3.json"
write_new(bundle_path, bundle)
print(bundle["bundle_id"], bundle["production_controls"]["sha256"])
