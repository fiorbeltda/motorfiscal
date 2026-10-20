"""Materialize evidenced supplier immunity; preserve every predecessor payload."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-4"


def read(path):
    return json.loads((ROOT / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.02-3.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
lc_hash = hashlib.sha256((ROOT / "sources/official/lc214-camara-observed-20261001.html").read_bytes()).hexdigest()
ctn_hash = hashlib.sha256((ROOT / "sources/official/ctn-camara-observed-20261002.html").read_bytes()).hexdigest()
assert ctn_hash == "dc32f69ddba5b4c0cdd7952091c74d755c1e0e699a444aa3566819b94422b650"
ctn_source = {"id": "CTN-CONSOLIDATED-CAMARA-OBSERVED-20261002",
    "url": "https://www2.camara.leg.br/legin/fed/lei/1960-1969/lei-5172-25-outubro-1966-358971-normaatualizada-pl.html",
    "file": "ctn-camara-observed-20261002.html", "sha256": ctn_hash,
    "version": "consolidated-observed-2026-10-02", "published_on": "1966-10-25", "retrieved_on": "2026-10-02",
    "source_kind": "OFFICIAL_CONSOLIDATED_ACT", "superseded_by": None,
    "proven_effective_scope": "CTN art.14 I-III and paragraphs1-2, supplier eligibility referred to by LC214 art.9 paragraph3; observation availability bound by bundle."}
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
codes["institutional_immunity"] = {
    "strategy": "LC214_ART9_II_III_CTN14", "status": "ACTIVE", "valid_from": "2026-01-01", "published_at": available,
    "source_id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001", "source_hash": lc_hash,
    "ctn_source_id": ctn_source["id"], "ctn_source_hash": ctn_hash, "review_status": "SANDBOX_REVIEW_REQUIRED",
    "source_article": "LC214 art.9 II/III paragraphs2-4; CTN art.14", "input_contract": "SINGLE_SUPPLIER_PROOF_ARRAY",
}
new_rules = {}
for rule_id, rule in codes["engine_decision_rules"].items():
    if rule.get("ctribnac_code") != "080101" or rule.get("tax_treatment") != "STANDARD":
        continue
    for cclass in ["410006", "410007"]:
        row = copy.deepcopy(rule)
        row.update(tax_treatment="IMMUNE", cclasstrib=cclass, immunity_cclasstrib=cclass,
                   published_at=available, source_article="LC214 art.9 II/III; CTN art.14", source_hash=lc_hash)
        new_rules[f"{rule_id}.IMMUNITY.{cclass}"] = row
assert len(new_rules) == 4, len(new_rules)
codes["engine_decision_rules"].update(new_rules)
facts = read(previous["resource_paths"]["fact_definitions"])
facts.append({"key": "rtc.institutional_immunity_proof", "type": "ARRAY", "material": True})
questions = read(previous["resource_paths"]["questions"])
questions.append({"fact_key": "rtc.institutional_immunity_proof", "answer_type": "ARRAY",
    "rank": max(row["rank"] for row in questions) + 1,
    "prompt": "Informe uma prova da instituição fornecedora vinculada ao documento e ao período, natureza jurídica, categoria, requisitos legais e referências documentais; a condição do cliente não torna a aquisição imune."})
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
for control in controls["controls"]:
    if control["id"] in ["4B.4.BASE_AND_TREATMENTS", "4B.6.CLASSIFICATION"]:
        control["source_ids"] = sorted(set(control["source_ids"] + [ctn_source["id"]]))
        control["gap"] += " Imunidade institucional II/III qualificada por fornecedor/documento/período; quatro regras educacionais candidatas; demais imunidades, perfis e aprovação formal pendentes."
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
              predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = VERSION
bundle["official_sources"].append(ctn_source)
for key, path, value in [
    ("rtc_code_catalog", f"catalogs/rtc-codes-{VERSION}.json", codes),
    ("fact_definitions", f"facts/consumption-phase4b-{VERSION}.json", facts),
    ("questions", f"questions/consumption-integrated-{VERSION}.json", questions),
]:
    write(path, value)
    bundle["resource_paths"][key] = path
    bundle["content_hashes"][key] = digest(value)
write(f"production/phase4b-controls-{VERSION}.json", controls)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
write(target, bundle)
write(f"sources/official/phase4b-manifest-{VERSION}.json", {"version": VERSION, "retrieved_on": "2026-10-02",
    "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base = read("scenarios/consumption-phase4b-2026.10.01-6/education-presential-2026.json")
base.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
base["facts"].update({"rtc.tax_treatment": "IMMUNE", "rtc.treatment_rule_id": "RTC.IMMUNITY.LC214.ART9.III-1",
    "rtc.treatment_evidence_id": "FIXTURE.INSTITUTIONAL.SUPPLY.QUALIFICATION"})
proof = {"supplier_id": base["facts"]["rtc.supplier_id"], "document_id": base["facts"]["rtc.document_id"],
    "category": "NONPROFIT_EDUCATION", "valid_from": "2026-01-01", "valid_to": "2026-12-31", "observed_on": "2026-09-30"}
for condition in ["private_legal_entity", "nonprofit", "no_asset_or_income_distribution", "resources_applied_in_brazil",
                  "formal_accurate_bookkeeping", "service_directly_related_to_statutory_objectives", "benefit_not_suspended"]:
    proof[condition] = True
for evidence in ["legal_entity_evidence_id", "statute_evidence_id", "nonprofit_evidence_id", "operation_scope_evidence_id",
                 "no_distribution_evidence_id", "resources_application_evidence_id", "bookkeeping_evidence_id", "benefit_status_evidence_id"]:
    proof[evidence] = f"FIXTURE.INSTITUTIONAL.{evidence.upper()}"
base["facts"]["rtc.institutional_immunity_proof"] = [proof]
cases = {"nonprofit-education": copy.deepcopy(base)}
religious = copy.deepcopy(base)
religious["facts"]["rtc.treatment_rule_id"] = "RTC.IMMUNITY.LC214.ART9.II-1"
religious["facts"]["rtc.institutional_immunity_proof"][0].update(category="RELIGIOUS_ENTITY", professes_faith_and_practices_religion=True)
cases["religious-entity"] = religious
for name in ["missing-proof", "wrong-supplier", "wrong-document", "future-observation", "expired-proof", "distribution",
             "resources-abroad", "bookkeeping", "unrelated-service", "suspended-benefit", "for-profit", "employers-union",
             "missing-bookkeeping-evidence", "unknown-condition", "wrong-rule", "not-religious"]:
    case = copy.deepcopy(religious if name == "not-religious" else base)
    row = case["facts"]["rtc.institutional_immunity_proof"][0]
    if name == "missing-proof":
        del case["facts"]["rtc.institutional_immunity_proof"]
    elif name in ["wrong-supplier", "wrong-document"]:
        row["supplier_id" if name == "wrong-supplier" else "document_id"] = "wrong-owner"
    elif name == "future-observation":
        row["observed_on"] = "2026-10-03"
    elif name == "expired-proof":
        row["valid_to"] = "2026-09-30"
    elif name == "employers-union":
        row["category"] = "EMPLOYERS_UNION"
    elif name == "missing-bookkeeping-evidence":
        del row["bookkeeping_evidence_id"]
    elif name == "unknown-condition":
        del row["resources_applied_in_brazil"]
    elif name == "wrong-rule":
        case["facts"]["rtc.treatment_rule_id"] = "RTC.IMMUNITY.LC214.ART9.II-1"
    else:
        condition = {"distribution": "no_asset_or_income_distribution", "resources-abroad": "resources_applied_in_brazil",
            "bookkeeping": "formal_accurate_bookkeeping", "unrelated-service": "service_directly_related_to_statutory_objectives",
            "suspended-benefit": "benefit_not_suspended", "for-profit": "nonprofit", "not-religious": "professes_faith_and_practices_religion"}[name]
        row[condition] = False
    cases[name] = case
for name, case in cases.items():
    case["scenario_id"] = f"institutional-immunity-{name}-2026"
    write(f"scenarios/consumption-phase4b-{VERSION}/{case['scenario_id']}.json", case)
print(f"Created {bundle['bundle_id']}: {len(cases)} cases, {len(new_rules)} classification rules, 19 official sources")
