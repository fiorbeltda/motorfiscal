"""Fork Phase 4B with a post-ISS integrated decision path for 2033 onward."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-6.json").read_text(encoding="utf-8"))


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
facts.append({"key": "rtc.legacy_event_spans_transition", "type": "BOOLEAN", "material": True})
facts_path = root / "facts/consumption-phase4b-2026.09.28-7.json"
write_new(facts_path, facts)

questions = [{"fact_key": "rtc.legacy_event_spans_transition",
              "prompt": "A mesma operação tem elemento temporal de ISS/ICMS iniciado até 31/12/2032 e IBS em ano posterior?",
              "rank": 243, "answer_type": "BOOLEAN"}]
questions_path = root / "questions/consumption-phase4b-post-iss-2026.09.28-7.json"
write_new(questions_path, questions)

invariants = json.loads((root / source["resource_paths"]["invariants"]).read_text(encoding="utf-8"))
for row in invariants:
    if row["id"].startswith("ISS_") or row["id"] == "DPS_RETENTION_TYPE_REQUIRED":
        row["effective_to"] = "2032-12-31"
invariants.append({"id": "ISS_POST_2032_STATUS_REQUIRED", "type": "REQUIRED_DECISION_PATH",
                   "path": "iss.status", "severity": "BLOCKING", "effective_from": "2033-01-01"})
invariants_path = root / "invariants/consumption-phase4b-2026.09.28-7.json"
write_new(invariants_path, invariants)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-7"
transition["post_iss_integrated"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-7.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-6"
for row in controls["controls"]:
    if row["id"] == "4B.1.TEMPORALITY":
        row["gap"] = "2033 sem ISS encaminhado ao IBS/CBS com prova de evento não transicional; operações cruzando 2032/2033 ainda exigem regra específica. CBS 2027+ e IBS por destino 2029+ sem alíquotas comprovadas."
    if row["id"] == "INTEGRATED.4A":
        row["gap"] = "O bundle 4A depende de revisão temporal e atos locais; retenções federais solicitadas após 2032 exigem perfil sem PIS/Cofins de consumo antes de integrar."
controls_path = root / "production/phase4b-controls-2026.09.28-6.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-7"
bundle["version"] = "2026.09.28-7"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T12:13:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["invariants"] = "invariants/" + invariants_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = source["question_overlays"] + ["questions/" + questions_path.name]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["invariants"] = canonical_hash(invariants)
all_questions = json.loads((root / source["resource_paths"]["questions"]).read_text(encoding="utf-8"))
for overlay in bundle["question_overlays"]:
    all_questions += json.loads((root / overlay).read_text(encoding="utf-8"))
bundle["content_hashes"]["questions"] = canonical_hash(all_questions)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-7.json", bundle)
print(bundle["bundle_id"])
