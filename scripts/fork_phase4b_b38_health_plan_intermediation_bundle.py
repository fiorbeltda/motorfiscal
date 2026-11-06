"""B38 (4B.4.10): intermediação de planos de assistência à saúde (LC 214/2025, art. 240 c/c art. 237).

Forks bundle B37 without touching it or any earlier bundle.
- Adds HealthPlanIntermediationB38::POLICY under `health_plan_intermediation_b38`, with `published_at` = knowledge availability.
- Adds one engine decision rule (SANDBOX_REVIEW_REQUIRED): cTribNac 100104 (agenciamento, corretagem ou intermediação de planos
  de saúde), NBS 1.0906.12 / 1.0910.20, destination by acquirer domicile (art. 11, X, a; cIndOp 100301), official cClassTrib
  011003 (CST 011, indNFSe = 1, uniform national rate = reference rates reduced by 60%).
- Extends `rtc.specific_regime` with HEALTH_PLAN_INTERMEDIATION and adds the regime facts.
Every legal element is quoted from lc214-planalto-observed-20261103.htm (SHA256 0a0d190b...); nothing is estimated.
Re-running is idempotent; it refuses to overwrite a file with different content.
"""

import copy
import hashlib
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
ROOT = REPO / "resources/fiscal"
VERSION = "2026.11.06-b38-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.06-b37-draft1.json"
PLANALTO = "sources/official/lc214-planalto-observed-20261103.htm"
PLANALTO_HASH = "0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")

FACTS = [
    ("rtc.health_plan_intermediation_scope_evidence_id", "STRING", None,
     "Prova da intermediação de plano de assistência à saúde (contrato de corretagem/agenciamento)"),
    ("rtc.health_plan_operator_id", "STRING", None, "Operadora do plano intermediado (art. 234)"),
]
RANK = 248
PATTERNS = {}


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
    target.write_bytes((json.dumps(value, ensure_ascii=False, indent=2) + "\n").encode("utf-8"))


def php_policy():
    code = ("require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\HealthPlanIntermediationB38::POLICY,"
            " JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


def rule(canonical, ctribnac, kind, nbs, cindop, cclass, label, published):
    r = {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
         "canonical_service_id": canonical, "operation_kind": kind, "market": "DOMESTIC",
         "delivery_modes": ["PRESENTIAL", "REMOTE", "DIGITAL"], "destination_basis": "PROPERTY",
         "tax_treatment": "SPECIFIC_REGIME", "specific_regime": "REAL_ESTATE", "nbs_prefixes": [nbs],
         "cindop": cindop, "cclasstrib": cclass, "valid_from": "2026-01-01", "published_at": published,
         "source_article": f"LC214 art. 11, II; arts. 251-261 ({label})", "source_hash": PLANALTO_HASH}
    if ctribnac:
        r["ctribnac_code"] = ctribnac
    return r


RULES = [
    ("RTC.CLASSIFICATION.HEALTH_PLAN_INTERMEDIATION.100104.ACQUIRER_DOMICILE.B38-1", "CTRIBNAC_100104", "100104", "SERVICE",
     ["1.0906.12", "1.0910.20"], "100301", "011003", "intermediação de plano de saúde, art. 240"),
]


def rule2(canonical, ctribnac, kind, nbs, cindop, cclass, label, published):
    return {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
            "canonical_service_id": canonical, "ctribnac_code": ctribnac, "operation_kind": kind, "market": "DOMESTIC",
            "delivery_modes": ["REMOTE", "DIGITAL", "PRESENTIAL"], "destination_basis": "ACQUIRER_DOMICILE",
            "tax_treatment": "SPECIFIC_REGIME", "specific_regime": "HEALTH_PLAN_INTERMEDIATION", "nbs_prefixes": nbs,
            "cindop": cindop, "cclasstrib": cclass, "valid_from": "2026-01-01", "published_at": published,
            "source_article": f"LC214 art. 11, X, a; art. 240 c/c art. 237 ({label})", "source_hash": PLANALTO_HASH}


def main():
    assert hashlib.sha256((ROOT / PLANALTO).read_bytes()).hexdigest() == PLANALTO_HASH
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    assert "health_plan_intermediation_b38" not in codes
    row = codes["cclasstrib"]["011003"]
    assert row["cst"] == "011" and row["nfse_supported"] is True and row["legal_reference"] == "Art. 240"
    assert row["source_reduction_ibs_percent"] == 60 and row["source_reduction_cbs_percent"] == 60
    assert codes["cindop"]["100301"]["nfse_supported"] is True
    codes["health_plan_intermediation_b38"] = {**php_policy(), "published_at": available}
    for rid, canonical, ctribnac, kind, nbs, cindop, cclass, label in RULES:
        assert rid not in codes["engine_decision_rules"]
        codes["engine_decision_rules"][rid] = rule2(canonical, ctribnac, kind, nbs, cindop, cclass, label, available)

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    regime = [r for r in facts if r["key"] == "rtc.specific_regime"]
    assert len(regime) == 1 and regime[0]["allowed_values"] == ["HOSPITALITY", "TRAVEL_AGENCY", "REAL_ESTATE"]
    regime[0]["allowed_values"] = ["HOSPITALITY", "TRAVEL_AGENCY", "REAL_ESTATE", "HEALTH_PLAN_INTERMEDIATION"]
    regime_q = [q for q in questions if q["fact_key"] == "rtc.specific_regime"]
    assert len(regime_q) == 1
    regime_q[0]["options"] = ["HOSPITALITY", "TRAVEL_AGENCY", "REAL_ESTATE", "HEALTH_PLAN_INTERMEDIATION"]
    for key, kind, values, prompt in FACTS:
        assert key not in {r["key"] for r in facts}, key
        fact = {"key": key, "type": kind, "material": True}
        question = {"fact_key": key, "prompt": prompt, "rank": RANK, "answer_type": kind}
        if values is not None:
            fact["allowed_values"] = values
            question["options"] = values
        if kind == "STRING":
            fact["min_length"] = 1
        if key in PATTERNS:
            fact["pattern"] = PATTERNS[key]
        facts.append(fact)
        questions.append(question)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    for control in controls["controls"]:
        if control["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
            control["gap"] += (" B38: intermediação de planos de saúde (LC 214, art. 240 c/c 237): alíquota de referência "
                               "reduzida em 60%, uniforme nacional, cClassTrib 011003; materializada para 2026 (alíquotas de teste "
                               "nacionais); de 2027 em diante exige a rota de alíquotas de referência. O regime das operadoras "
                               "(apuração por período, documentos CST 820) segue pendente.")

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B38: intermediação de planos de saúde (LC 214, art. 240) numa versão nova; "
                         "bundles anteriores permanecem inalterados.")
    bundle["catalog_versions"]["rtc_codes"] = VERSION
    for res_key, path, value in [
        ("rtc_code_catalog", f"catalogs/rtc-codes-{VERSION}.json", codes),
        ("fact_definitions", f"facts/consumption-phase4b-{VERSION}.json", facts),
        ("questions", f"questions/consumption-integrated-{VERSION}.json", questions),
    ]:
        write(path, value)
        bundle["resource_paths"][res_key] = path
        bundle["content_hashes"][res_key] = digest(value)
    write(f"production/phase4b-controls-{VERSION}.json", controls)
    bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
    write(target, bundle)
    print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available,
                      "rtc_codes_hash": bundle["content_hashes"]["rtc_code_catalog"]}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
