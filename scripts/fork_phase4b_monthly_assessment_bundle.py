"""Bind local monthly consolidation of previously qualified amounts, no filing."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-7"


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


previous = read("bundles/consumption-phase4b-2026.10.02-6.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
assert hashlib.sha256((ROOT / "sources/official/lc214-camara-observed-20261001.html").read_bytes()).hexdigest() == source
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
codes["monthly_assessment"] = {
    "strategy": "LC214_ART42_45_53_MONTHLY", "status": "ACTIVE", "valid_from": "2026-01-01", "published_at": available,
    "source_id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001", "source_hash": source,
    "source_article": "LC214 arts.42-45,47,53-54", "review_status": "SANDBOX_REVIEW_REQUIRED",
    "scope": "COMPLETE_PREQUALIFIED_MONTHLY_LEDGER_LOCAL_CALCULATION_NO_FILING_OR_POSTING",
}
facts = read(previous["resource_paths"]["fact_definitions"])
facts.extend([{"key": "rtc.assess_monthly_period", "type": "BOOLEAN", "material": True},
              {"key": "rtc.monthly_assessment", "type": "ARRAY", "material": True}])
questions = read(previous["resource_paths"]["questions"])
rank = max(row["rank"] for row in questions) + 1
questions.extend([
    {"fact_key": "rtc.assess_monthly_period", "answer_type": "BOOLEAN", "rank": rank,
     "prompt": "Deseja calcular localmente os saldos de IBS/CBS de um mês encerrado, sem entrega ou recolhimento?"},
    {"fact_key": "rtc.monthly_assessment", "answer_type": "ARRAY", "rank": rank + 1,
     "prompt": "Informe o livro mensal completo de todos os estabelecimentos, débitos exigíveis, créditos já apropriados e líquidos de anulações, saldos anteriores reconciliados, ajustes e extinções comprovados e qualificados por tributo."},
])
assert len({row['key'] for row in facts}) == len(facts)
assert len({row['fact_key'] for row in questions}) == len(questions)
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
for control in controls["controls"]:
    if control["id"] == "4B.5.CREDITS":
        control["gap"] = "Crédito de entrada arts49/50; anulação/manutenção51/52 e cálculo mensal consolidado42-45/53 sobre livro previamente qualificado, sem entrega. Qualificação dos regimes específicos/presumidos e integração contábil ainda pendentes."
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
write(f"sources/official/phase4b-manifest-{VERSION}.json", {"version": VERSION, "retrieved_on": "2026-10-02",
    "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base = read("scenarios/consumption-integrated-2026.10.02-3/integrated-supplier-credit-period-mixed-2026.json")
base.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
base["facts"].pop("rtc.supplier_credit_period")
base["facts"].pop("rtc.assess_supplier_credit_period")
base["facts"]["rtc.assess_monthly_period"] = True
supplier = base["facts"]["rtc.supplier_id"]
establishments = [supplier, "11111111000272"]


def entry(name, tax, amount, date="2026-09-10", branch=0, **extra):
    return {"id": name, "tax": tax, "amount": amount, "date": date, "observed_on": "2026-10-01",
            "establishment_id": establishments[branch], "qualification_status": "RESOLVED",
            "qualification_evidence_id": f"FIXTURE.QUALIFIED.{name}", "qualification_hash": digest({"fixture": name, "tax": tax, "amount": amount}), **extra}


ledger = {
    "month": "2026-09", "supplier_id": supplier, "currency": "BRL", "establishments": establishments,
    "central_establishment_id": supplier,
    **{key: True for key in ["all_establishments_included", "all_regimes_included", "ledger_complete", "credits_net_of_annulments", "prior_debts_complete"]},
    **{key: f"FIXTURE.COMPLETE.{key}" for key in ["ledger_evidence_id", "establishment_registry_evidence_id", "credit_qualification_evidence_id", "prior_debts_evidence_id"]},
    "debits": [entry("debit-ibs", "IBS", "100.00", document_id="output1", due_status="DUE"),
               entry("debit-cbs", "CBS", "90.00", document_id="output1", due_status="DUE"),
               entry("branch-ibs", "IBS", "20.00", branch=1, document_id="output2", due_status="DUE")],
    "credits": [entry("credit-ibs", "IBS", "30.00", document_id="input1", credit_kind="ORDINARY", appropriation_date="2026-09-10",
                      appropriated=True, appropriation_evidence_id="FIXTURE.APPROPRIATED", extinction_evidence_id="FIXTURE.EXTINCT.INPUT"),
                entry("credit-cbs", "CBS", "180.00", document_id="input1", credit_kind="ORDINARY", appropriation_date="2026-09-10",
                      appropriated=True, appropriation_evidence_id="FIXTURE.APPROPRIATED", extinction_evidence_id="FIXTURE.EXTINCT.INPUT")],
    "carryforward": [entry("prior-credit-ibs", "IBS", "40.00", date="2026-08-31", document_id="prior-input1", credit_kind="ORDINARY",
                           appropriation_date="2026-08-15", appropriation_evidence_id="FIXTURE.PRIOR.APPROPRIATED", extinction_evidence_id="FIXTURE.PRIOR.EXTINCT",
                           used_for_compensation="5.00", refunded="10.00", reserved_for_refund="5.00")],
    "prior_debts": [entry("prior-debt-ibs", "IBS", "10.00", date="2026-08-31", due_date="2026-09-15",
                          overdue_unextinguished_not_enrolled=True, legal_additions_evidence_id="FIXTURE.PRIOR.DEBT.INCLUDING.ADDITIONS")],
    "adjustments": [],
    "extinctions": [entry("extinct-ibs", "IBS", "15.00", date="2026-09-20", method="ACQUIRER_PAYMENT", debit_id="debit-ibs", extinction_evidence_id="FIXTURE.EXTINCT.OUTPUT")],
}
cases = {"mixed": ledger}
for name in ["balanced", "adjusted", "presumed-qualified", "after-close-extinction", "incomplete", "future-period", "wrong-owner", "duplicate", "excess-extinction", "wrong-extinction-tax", "compensation-double-count", "unqualified-credit", "carryforward-overdrawn", "nominal-not-due", "future-evidence", "missing-prior-debts", "unqualified-adjustment", "unqualified-presumed"]:
    cases[name] = copy.deepcopy(ledger)
cases["balanced"]["debits"][0]["amount"] = "35.00"
cases["balanced"]["debits"][1]["amount"] = "180.00"
cases["balanced"]["debits"][2]["amount"] = "20.00"
cases["adjusted"]["adjustments"] = [entry("adjust-plus", "IBS", "5.00", direction="PLUS", rule_id="FIXTURE.PREQUALIFIED.ADJUSTMENT", rule_source_hash=source),
                                       entry("adjust-minus", "CBS", "10.00", direction="MINUS", rule_id="FIXTURE.PREQUALIFIED.ADJUSTMENT", rule_source_hash=source)]
for name in ["presumed-qualified", "unqualified-presumed"]:
    cases[name]["credits"][0].update(credit_kind="PRESUMED", rule_id="FIXTURE.PREQUALIFIED.PRESUMED", rule_source_hash=source)
cases["unqualified-presumed"]["credits"][0].pop("rule_source_hash")
cases["after-close-extinction"]["extinctions"][0]["date"] = "2026-10-01"
cases["incomplete"]["all_establishments_included"] = False
cases["future-period"]["month"] = "2026-10"
cases["wrong-owner"]["supplier_id"] = "wrong-owner"
cases["duplicate"]["credits"].append(copy.deepcopy(cases["duplicate"]["credits"][0]))
cases["excess-extinction"]["extinctions"][0]["amount"] = "101.00"
cases["wrong-extinction-tax"]["extinctions"][0]["tax"] = "CBS"
cases["compensation-double-count"]["extinctions"][0]["method"] = "CREDIT_COMPENSATION"
cases["unqualified-credit"]["credits"][0]["qualification_status"] = "PENDING"
cases["carryforward-overdrawn"]["carryforward"][0]["refunded"] = "50.00"
cases["nominal-not-due"]["debits"][0]["due_status"] = "NOT_DUE"
cases["future-evidence"]["credits"][0]["observed_on"] = "2026-10-03"
cases["missing-prior-debts"]["prior_debts_complete"] = False
cases["unqualified-adjustment"]["adjustments"] = [entry("unknown-adjust", "IBS", "5.00", direction="PLUS")]
cases['family-4a'] = copy.deepcopy(ledger)
for name, value in cases.items():
    scenario = copy.deepcopy(base)
    if name == 'family-4a':
        scenario = read('scenarios/consumption-integrated-2026.10.02-6/integrated-institutional-immunity-family-payer-with-4a-2026.json')
        scenario.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        scenario['facts']['rtc.assess_monthly_period'] = True
    scenario["scenario_id"] = f"integrated-monthly-assessment-{name}-2026"
    scenario["facts"]["rtc.monthly_assessment"] = [value]
    write(f"scenarios/consumption-integrated-{VERSION}/{scenario['scenario_id']}.json", scenario)
print(f"Created {bundle['bundle_id']}: {len(cases)} integrated monthly cases")
