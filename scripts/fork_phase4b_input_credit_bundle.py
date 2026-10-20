"""Version the per-tax acquisition credit contract of LC214 arts. 49 and 50."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-1"


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


previous = read("bundles/consumption-phase4b-2026.10.01-6.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source_hash = hashlib.sha256((ROOT / "sources/official/lc214-camara-observed-20261001.html").read_bytes()).hexdigest()
assert source_hash == "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
codes["input_credit_treatment"] = {
    "strategy": "LC214_ART49_50", "status": "ACTIVE", "valid_from": "2026-01-01", "published_at": available,
    "source_id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001", "source_hash": source_hash,
    "source_article": "LC214 arts. 47, 49 e 50", "review_status": "SANDBOX_REVIEW_REQUIRED",
    "scope": "ACQUIRER_ORDINARY_CREDIT_PER_TAX_NOT_SUPPLIER_PERIOD_REVERSAL",
}
facts = read(previous["resource_paths"]["fact_definitions"])
facts.extend([{"key": "rtc.input_tax_treatments", "type": "ARRAY", "material": True},
              {"key": "rtc.presumed_credit_requested", "type": "BOOLEAN", "material": True}])
assert len({row["key"] for row in facts}) == len(facts)
questions = read(previous["resource_paths"]["questions"])
rank = max(row["rank"] for row in questions) + 1
questions.extend([
    {"fact_key": "rtc.input_tax_treatments", "answer_type": "ARRAY", "rank": rank,
     "prompt": "Informe o tratamento de IBS e CBS da aquisição, documento e evidência de classificação; em suspensão exigida, segregue principal extinto e acréscimos legais com suas provas."},
    {"fact_key": "rtc.presumed_credit_requested", "answer_type": "BOOLEAN", "rank": rank + 1,
     "prompt": "Há pedido de crédito presumido específico nesta aquisição?"},
])
assert len({row["fact_key"] for row in questions}) == len(questions)
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
for control in controls["controls"]:
    if control["id"] == "4B.5.CREDITS":
        control["source_ids"] = sorted(set(control["source_ids"] + ["LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001"]))
        control["gap"] = "Tratamento da aquisição segregado por IBS/CBS: vedação do art.49, suspensão exigida com limite ao principal extinto do art.50 e prova documental. Créditos presumidos específicos, anulação proporcional das saídas e demais regimes ainda pendentes."
    if control["id"] == "4B.6.CLASSIFICATION":
        control["gap"] = "92 regras interpretativas de SaaS, profissionais e educação/saúde disponíveis, sem aprovação formal; outros mapeamentos e destinos ainda pendentes."
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
              predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = VERSION
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
write(f"sources/official/phase4b-manifest-{VERSION}.json", {"version": VERSION, "retrieved_on": "2026-10-02", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base = read("scenarios/consumption-phase4b-2026.10.01-6/health-presential-2026.json")
base.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
base["facts"].update({"rtc.credit_context": "INPUT", "rtc.acquirer_regime": "REGULAR",
    "rtc.input_document_id": "independent-acquisition-document", "rtc.input_document_valid": True,
    "rtc.prior_tax_extinguished": True, "rtc.input_ibs_amount": "2.00", "rtc.input_cbs_amount": "18.00",
    "rtc.credit_adjustments": [], "rtc.presumed_credit_requested": False})


def treatments(ibs="STANDARD", cbs="STANDARD"):
    return [{"tax": tax, "treatment": treatment, "document_id": "independent-acquisition-document",
             "evidence_id": f"FIXTURE.INPUT.{tax}.CLASSIFICATION"}
            for tax, treatment in [("IBS", ibs), ("CBS", cbs)]]


cases = {}
for treatment in ["STANDARD", "REDUCED_30", "REDUCED_60", "IMMUNITY", "EXEMPT", "ZERO_RATE", "DEFERRAL", "SUSPENSION"]:
    scenario = copy.deepcopy(base)
    scenario["facts"]["rtc.input_tax_treatments"] = treatments(treatment, treatment)
    if treatment not in ["STANDARD", "REDUCED_30", "REDUCED_60"]:
        scenario["facts"].update({"rtc.input_ibs_amount": "0.00", "rtc.input_cbs_amount": "0.00", "rtc.prior_tax_extinguished": False})
    if treatment == "SUSPENSION":
        for row in scenario["facts"]["rtc.input_tax_treatments"]:
            row["suspension_demanded"] = False
    cases[f"input-credit-{treatment.lower().replace('_', '-')}-2026"] = scenario

demanded = copy.deepcopy(base)
demanded["facts"]["rtc.input_tax_treatments"] = treatments("SUSPENSION", "SUSPENSION")
for row in demanded["facts"]["rtc.input_tax_treatments"]:
    tax = row["tax"]
    row.update(suspension_demanded=True, demanded_principal="3.00" if tax == "IBS" else "27.00",
               extinguished_principal="2.00" if tax == "IBS" else "18.00", legal_additions="0.25" if tax == "IBS" else "2.25",
               demand_evidence_id=f"FIXTURE.DEMAND.{tax}", extinction_evidence_id=f"FIXTURE.EXTINCTION.{tax}", extinction_date="2026-09-30")
cases["input-credit-suspension-principal-only-2026"] = demanded
pending = copy.deepcopy(demanded)
pending["facts"].update({"rtc.input_ibs_amount": "0.00", "rtc.input_cbs_amount": "0.00", "rtc.prior_tax_extinguished": False})
for row in pending["facts"]["rtc.input_tax_treatments"]:
    row["extinguished_principal"] = "0.00"
    del row["extinction_evidence_id"], row["extinction_date"]
cases["input-credit-suspension-pending-2026"] = pending
mixed = copy.deepcopy(base)
mixed["facts"]["rtc.input_tax_treatments"] = treatments("STANDARD", "ZERO_RATE")
mixed["facts"]["rtc.input_cbs_amount"] = "0.00"
cases["input-credit-asymmetric-2026"] = mixed
for name, original in [
    ("missing-treatment", mixed), ("unlinked-document", mixed), ("duplicate-tax", mixed),
    ("presumed-rule-missing", mixed), ("zero-positive-claim", mixed),
    ("suspension-additions-claim", demanded), ("suspension-future-extinction", demanded),
]:
    scenario = copy.deepcopy(original)
    facts_case = scenario["facts"]
    if name == "missing-treatment":
        del facts_case["rtc.input_tax_treatments"]
    elif name == "unlinked-document":
        facts_case["rtc.input_tax_treatments"][0]["document_id"] = "other-document"
    elif name == "duplicate-tax":
        facts_case["rtc.input_tax_treatments"][1]["tax"] = "IBS"
    elif name == "presumed-rule-missing":
        facts_case["rtc.presumed_credit_requested"] = True
    elif name == "zero-positive-claim":
        facts_case["rtc.input_cbs_amount"] = "1.00"
    elif name == "suspension-additions-claim":
        facts_case["rtc.input_ibs_amount"] = "2.25"
    else:
        facts_case["rtc.input_tax_treatments"][0]["extinction_date"] = "2026-10-02"
    cases[f"input-credit-{name}-2026"] = scenario
for name, scenario in cases.items():
    scenario["scenario_id"] = name
    write(f"scenarios/consumption-phase4b-{VERSION}/{name}.json", scenario)
for name in ["input-credit-asymmetric-2026", "input-credit-suspension-principal-only-2026"]:
    scenario = copy.deepcopy(cases[name])
    scenario["scenario_id"] = "integrated-" + name
    write(f"scenarios/consumption-integrated-{VERSION}/{scenario['scenario_id']}.json", scenario)
print(f"Created {bundle['bundle_id']}: {len(cases)} RTC and 2 integrated input credit cases")
