"""Version period credit annulment/preservation without changing historical contracts."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-2"


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


previous = read("bundles/consumption-phase4b-2026.10.02-1.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source_hash = hashlib.sha256((ROOT / "sources/official/lc214-camara-observed-20261001.html").read_bytes()).hexdigest()
assert source_hash == "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
policies = {
    "STANDARD": ("PRESERVE", "LC214 art. 47"),
    "REDUCED_30": ("PRESERVE", "LC214 art. 47, §10"),
    "REDUCED_60": ("PRESERVE", "LC214 art. 47, §10"),
    "ZERO_RATE": ("PRESERVE", "LC214 art. 52"),
    "IMMUNITY_EXPORT": ("PRESERVE", "LC214 art. 51, §2º, I; art.79"),
    "IMMUNITY_BOOKS": ("PRESERVE", "LC214 art. 51, §2º, II; art.9º, IV"),
    "IMMUNITY_FREE_BROADCAST": ("PRESERVE", "LC214 art. 51, §2º, II; art.9º, VI"),
    "IMMUNITY_OTHER": ("ANNUL_PROPORTIONALLY", "LC214 art. 51, caput e §1º"),
    "EXEMPT": ("ANNUL_PROPORTIONALLY", "LC214 art. 51, caput e §1º"),
}
codes["supplier_credit_period"] = {
    "strategy": "LC214_ART51_52_SUPPLIER_PERIOD", "status": "ACTIVE", "valid_from": "2026-01-01", "published_at": available,
    "source_id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001", "source_hash": source_hash,
    "source_article": "LC214 arts.43, 47, §10, 51 e 52", "review_status": "SANDBOX_REVIEW_REQUIRED",
    "scope": "QUALIFIED_COMPLETE_MONTHLY_LEDGER_AND_PREVIOUS_OPERATION_CREDIT_POOL_NO_POSTING",
    "output_policies": {key: {"action": action, "source_article": article} for key, (action, article) in policies.items()},
}
facts = read(previous["resource_paths"]["fact_definitions"])
facts.extend([{"key": "rtc.assess_supplier_credit_period", "type": "BOOLEAN", "material": True},
              {"key": "rtc.supplier_credit_period", "type": "ARRAY", "material": True}])
assert len({row["key"] for row in facts}) == len(facts)
questions = read(previous["resource_paths"]["questions"])
rank = max(row["rank"] for row in questions) + 1
questions.extend([
    {"fact_key": "rtc.assess_supplier_credit_period", "answer_type": "BOOLEAN", "rank": rank,
     "prompt": "Deseja avaliar anulação e manutenção dos créditos anteriores do fornecedor em um mês encerrado?"},
    {"fact_key": "rtc.supplier_credit_period", "answer_type": "ARRAY", "rank": rank + 1,
     "prompt": "Informe o livro completo do mês, tratamentos documentados das saídas por IBS/CBS e conjunto de créditos anteriores com apropriação, extinção e ajustes comprovados."},
])
assert len({row["fact_key"] for row in questions}) == len(questions)
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
for control in controls["controls"]:
    if control["id"] == "4B.5.CREDITS":
        control["gap"] = "Crédito da aquisição arts.49/50 e anulação/manutenção quantitativas arts.51/52 sobre livro mensal completo e créditos anteriores comprovados. Elegibilidade das saídas, créditos presumidos, ajustes e regimes especiais ainda requerem regras próprias."
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
base["facts"]["rtc.assess_supplier_credit_period"] = True
ledger = {
    "month": "2026-09", "supplier_id": base["facts"]["rtc.supplier_id"], "currency": "BRL",
    "operations_ledger_evidence_id": "FIXTURE.CLOSED.OPERATIONS.LEDGER", "credit_pool_evidence_id": "FIXTURE.RELATED.PREVIOUS.CREDITS",
    "all_operations_included": True, "credit_pool_complete": True, "annulment_already_recorded": False,
    "total_output_value_brl": "1000.00", "outputs": [],
    "credit_pool": [{"id": "credit-1", "document_id": "prior-acquisition-document-1", "acquirer_id": base["facts"]["rtc.supplier_id"],
                     "appropriation_date": "2026-09-10", "appropriation_evidence_id": "FIXTURE.APPROPRIATION", "extinction_evidence_id": "FIXTURE.PRIOR.EXTINCTION",
                     "credit_kind": "ORDINARY_PREVIOUS_OPERATION", "ibs_amount": "100.00", "cbs_amount": "900.00"}],
    "credit_adjustments": [],
}


def output(row_id, value, ibs="STANDARD", cbs=None):
    return {"id": row_id, "document_id": f"closed-output-{row_id}", "eligibility_evidence_id": f"FIXTURE.QUALIFIED.{row_id}",
            "date": "2026-09-20", "value_brl": value, "ibs_treatment": ibs, "cbs_treatment": cbs or ibs}


mixed = copy.deepcopy(ledger)
mixed["outputs"] = [output("1", "600.00"), output("2", "200.00", "IMMUNITY_OTHER"),
                    output("3", "100.00", "EXEMPT"), output("4", "100.00", "IMMUNITY_EXPORT")]
cases = {"mixed": mixed}
for label, treatment in [("zero-preserved", "ZERO_RATE"), ("reduced-preserved", "REDUCED_60"),
                         ("export-preserved", "IMMUNITY_EXPORT"), ("books-preserved", "IMMUNITY_BOOKS"),
                         ("broadcast-preserved", "IMMUNITY_FREE_BROADCAST"), ("fully-immune", "IMMUNITY_OTHER")]:
    case = copy.deepcopy(ledger)
    case["outputs"] = [output("1", "1000.00", treatment)]
    cases[label] = case
asymmetric = copy.deepcopy(ledger)
asymmetric["outputs"] = [output("1", "400.00", "IMMUNITY_OTHER", "ZERO_RATE"), output("2", "600.00")]
cases["asymmetric"] = asymmetric
adjusted = copy.deepcopy(mixed)
adjusted["credit_adjustments"] = [{"id": "adjustment-1", "credit_entry_id": "credit-1", "tax": "IBS", "kind": "REVERSAL",
                                    "amount": "10.00", "date": "2026-09-15", "evidence_id": "FIXTURE.REVERSAL"}]
cases["adjusted"] = adjusted
ratio = copy.deepcopy(ledger)
ratio.update(total_output_value_brl="3.00", outputs=[output("1", "1.00", "IMMUNITY_OTHER"), output("2", "2.00")])
ratio["credit_pool"][0].update(ibs_amount="0.05", cbs_amount="0.10")
cases["rational-rounding"] = ratio
for label in ["incomplete", "wrong-total", "duplicate-output", "wrong-owner", "future-period", "zero-turnover", "special-policy", "adjustment-excess", "adjustment-future", "already-annulled"]:
    case = copy.deepcopy(adjusted)
    if label == "incomplete":
        case["all_operations_included"] = False
    elif label == "wrong-total":
        case["total_output_value_brl"] = "999.00"
    elif label == "duplicate-output":
        case["outputs"].append(copy.deepcopy(case["outputs"][0]))
    elif label == "wrong-owner":
        case["supplier_id"] = "other-supplier"
    elif label == "future-period":
        case["month"] = "2026-10"
    elif label == "zero-turnover":
        case["outputs"] = []
        case["total_output_value_brl"] = "0.00"
    elif label == "special-policy":
        case["outputs"][0]["ibs_treatment"] = "SUSPENSION"
    elif label == "adjustment-excess":
        case["credit_adjustments"][0]["amount"] = "101.00"
    elif label == "adjustment-future":
        case["credit_adjustments"][0]["date"] = "2026-10-01"
    else:
        case["annulment_already_recorded"] = True
    cases[label] = case
for label, period in cases.items():
    scenario = copy.deepcopy(base)
    scenario["scenario_id"] = f"supplier-credit-period-{label}-2026"
    scenario["facts"]["rtc.supplier_credit_period"] = period
    write(f"scenarios/consumption-phase4b-{VERSION}/{scenario['scenario_id']}.json", scenario)
for label in ["mixed", "asymmetric", "adjusted"]:
    scenario = copy.deepcopy(base)
    scenario["scenario_id"] = f"integrated-supplier-credit-period-{label}-2026"
    scenario["facts"]["rtc.supplier_credit_period"] = cases[label]
    write(f"scenarios/consumption-integrated-{VERSION}/{scenario['scenario_id']}.json", scenario)
print(f"Created {bundle['bundle_id']}: {len(cases)} RTC / 3 integrated supplier period cases")
