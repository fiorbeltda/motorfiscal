"""Build a new immutable Phase 4B manifest from the Phase 4A bundle."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
fiscal = root / "resources/fiscal"
source = json.loads((fiscal / "bundles/federal-phase4a-2026.09.24-4.json").read_text(encoding="utf-8"))


def read(path):
    return json.loads((fiscal / path).read_text(encoding="utf-8"))


def write(path, data):
    target = fiscal / path
    payload = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
    if path.startswith("bundles/") and target.exists() and target.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to rewrite an existing bundle: {path}")
    target.write_text(payload, encoding="utf-8")


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


invariants = read("invariants/federal-phase4a.json")
invariants.extend([
    {"id": "RTC_STATUS_REQUIRED", "type": "REQUIRED_DECISION_PATH", "path": "consumption_reform.status", "severity": "BLOCKING"},
    {"id": "RTC_PROJECTION_STATUS_REQUIRED", "type": "REQUIRED_DECISION_PATH", "path": "dps_projection.rtc.status", "severity": "BLOCKING"},
])
write("invariants/consumption-phase4b.json", invariants)

manifest = dict(source)
manifest["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.25-1"
manifest["bundle_key"] = "CONSUMPTION-PHASE4B"
manifest["version"] = "2026.09.25-1"
manifest["engine_version"] = "1.3.0-phase4b"
manifest["status"] = "SANDBOX_CANDIDATE"
manifest["knowledge_available_at"] = "2026-09-25T09:35:00-03:00"
manifest["catalog_versions"] = dict(source["catalog_versions"], rtc_rates="2026.09.25-1", rtc_codes="2026.09.25-1", rtc_transition="2026.09.25-1")
manifest["resource_paths"] = dict(source["resource_paths"],
    fact_definitions="facts/consumption-phase4b.json",
    invariants="invariants/consumption-phase4b.json",
    rtc_rate_catalog="catalogs/rtc-rates-2026.09.25-1.json",
    rtc_code_catalog="catalogs/rtc-codes-2026.09.25-1.json",
    rtc_transition_catalog="catalogs/rtc-transition-2026.09.25-1.json")
manifest["question_overlays"] = list(source["question_overlays"]) + ["questions/consumption-phase4b.json"]
manifest["official_sources"] = read("sources/official/phase4b-manifest.json")["artifacts"]

research_dir = Path("K:/GERAL/TI/Projeto - SAAS/Motor Fiscal/Pesquisas Definitivas")
for number, filename in [
    ("01", "Pesquisa 01 - Respostas - DEFINITIVA.txt"),
    ("06", "Pesquisa 06 - Respostas - DEFINITIVA.txt"),
    ("07", "Pesquisa 07 - Respostas - DEFINITIVA.txt"),
    ("10", "Pesquisa 10 - Respostas - DEFINITIVA.txt"),
    ("27", "Pesquisa 27 - Respostas - OK.txt"),
    ("28", "Pesquisa 28 - Respostas - OK.txt"),
    ("30", "Pesquisa 30 - Respostas - OK.txt"),
    ("31", "Pesquisa 31 - Respotas - OK.txt"),
    ("32", "Pesquisa 32 - Respostas - OK.txt"),
    ("33", "Pesquisa 33 - Respostas - OK.txt"),
    ("36", "Pesquisa 36 - Respostas - OK.txt"),
    ("38", "Pesquisa 38 - Respostas - OK.txt"),
]:
    content = (research_dir / filename).read_bytes()
    manifest["research_sources"].append({"source_id": "SOURCE.RESEARCH." + number,
        "file": filename, "size_bytes": len(content), "sha256": hashlib.sha256(content).hexdigest()})

paths = manifest["resource_paths"]
questions = read(paths["questions"])
for overlay in manifest["question_overlays"]:
    questions.extend(read(overlay))
rules_dir = fiscal / paths["rules"]
rules = [json.loads(path.read_text(encoding="utf-8")) for path in rules_dir.glob("*.json")]
rules.sort(key=lambda item: (item.get("rule_id", ""), item.get("version", "")))
documents = {
    "fact_definitions": read(paths["fact_definitions"]),
    "questions": questions,
    "service_catalog": read(paths["service_catalog"]),
    "municipal_parameters": read(paths["municipal_parameters"]),
    "invariants": read(paths["invariants"]),
    "scenario_schema": read(paths["scenario_schema"]),
    "rules": rules,
    "public_annex_catalog": read(paths["public_annex_catalog"]),
    "federal_obligation_catalog": read(paths["federal_obligation_catalog"]),
    "federal_banking_calendar": read(paths["federal_banking_calendar"]),
    "rtc_rate_catalog": read(paths["rtc_rate_catalog"]),
    "rtc_code_catalog": read(paths["rtc_code_catalog"]),
    "rtc_transition_catalog": read(paths["rtc_transition_catalog"]),
}
manifest["content_hashes"] = {key: digest(value) for key, value in documents.items()}
manifest["notice"] = "RTC sandbox only: future rates, special treatments, split payment, legal review and NT009 production deployment require independent proof."
write("bundles/consumption-phase4b-2026.09.25-1.json", manifest)
print(manifest["bundle_id"], len(documents["fact_definitions"]), len(questions))
