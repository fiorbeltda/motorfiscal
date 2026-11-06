"""B36 (4B.4.10): regime específico das agências de turismo, intermediação (LC 214/2025, arts. 288-291).

Forks bundle B35 without touching it or any earlier bundle.
- Adds TravelAgencyRegimeB36::POLICY under `travel_agency_regime_b36`, with `published_at` = knowledge availability.
- Adds engine decision rules (SANDBOX_REVIEW_REQUIRED) for cTribNac 090201 (agenciamento e intermediação), NBS reservation
  services 1.1805.1/1.1805.2/1.1805.3, destination by acquirer domicile (LC214 art. 11, X, a; cIndOp 100301), official
  cClassTrib 200051 (CST 200, indNFSe = 1, reduction 40%).
- Extends `rtc.specific_regime` with TRAVEL_AGENCY and adds the regime facts, including the settlement record used for the
  art. 289 base (total charged + third-party commissions - documented pass-through to intermediated suppliers).
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
VERSION = "2026.11.06-b36-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.06-b35-draft1.json"
PLANALTO = "sources/official/lc214-planalto-observed-20261103.htm"
PLANALTO_HASH = "0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")

FACTS = [
    ("rtc.travel_agency_intermediation", "BOOLEAN", None,
     "A operação é intermediação de serviços turísticos pela agência? (LC 214, art. 289)"),
    ("rtc.travel_agency_scope_evidence_id", "STRING", None, "Prova do agenciamento (contrato/voucher da intermediação)"),
    ("rtc.travel_agency_settlement", "ARRAY", None,
     "Apuração da base da agência (um registro, contrato RTC-TRAVEL-AGENCY-B36-V1): total cobrado do usuário, comissões e "
     "incentivos pagos por terceiros e repasses aos fornecedores intermediados com o documento que subsidia cada repasse."),
]
RANK = 246
NBS = ["1.1805.1", "1.1805.2", "1.1805.3"]


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
    code = ("require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\TravelAgencyRegimeB36::POLICY,"
            " JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


def rule(published):
    return {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
            "canonical_service_id": "CTRIBNAC_090201", "ctribnac_code": "090201", "operation_kind": "SERVICE",
            "market": "DOMESTIC", "delivery_modes": ["REMOTE", "DIGITAL"], "destination_basis": "ACQUIRER_DOMICILE",
            "tax_treatment": "SPECIFIC_REGIME", "specific_regime": "TRAVEL_AGENCY", "nbs_prefixes": NBS,
            "cindop": "100301", "cclasstrib": "200051", "valid_from": "2026-01-01", "published_at": published,
            "source_article": "LC214 art. 11, X, a; arts. 288-291", "source_hash": PLANALTO_HASH}


def main():
    assert hashlib.sha256((ROOT / PLANALTO).read_bytes()).hexdigest() == PLANALTO_HASH
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    assert "travel_agency_regime_b36" not in codes
    row = codes["cclasstrib"]["200051"]
    assert row["cst"] == "200" and row["nfse_supported"] is True and row["legal_reference"] == "Art. 289, II"
    assert row["source_reduction_ibs_percent"] == 40 and row["source_reduction_cbs_percent"] == 40
    assert codes["cindop"]["100301"]["nfse_supported"] is True
    codes["travel_agency_regime_b36"] = {**php_policy(), "published_at": available}
    rid = "RTC.CLASSIFICATION.TRAVEL_AGENCY.090201.ACQUIRER_DOMICILE.B36-1"
    assert rid not in codes["engine_decision_rules"]
    codes["engine_decision_rules"][rid] = rule(available)

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    regime = [r for r in facts if r["key"] == "rtc.specific_regime"]
    assert len(regime) == 1 and regime[0]["allowed_values"] == ["HOSPITALITY"]
    regime[0]["allowed_values"] = ["HOSPITALITY", "TRAVEL_AGENCY"]
    regime_q = [q for q in questions if q["fact_key"] == "rtc.specific_regime"]
    assert len(regime_q) == 1
    regime_q[0]["options"] = ["HOSPITALITY", "TRAVEL_AGENCY"]
    for key, kind, values, prompt in FACTS:
        assert key not in {r["key"] for r in facts}, key
        fact = {"key": key, "type": kind, "material": True}
        question = {"fact_key": key, "prompt": prompt, "rank": RANK, "answer_type": kind}
        if values is not None:
            fact["allowed_values"] = values
            question["options"] = values
        if kind == "STRING":
            fact["min_length"] = 1
        facts.append(fact)
        questions.append(question)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    for control in controls["controls"]:
        if control["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
            control["gap"] += (" B36: regime específico das agências de turismo (LC 214, arts. 288-291), intermediação: base "
                               "= total cobrado + comissões de terceiros - repasses documentados aos fornecedores intermediados; "
                               "redução de 40%; cClassTrib 200051; crédito do adquirente permitido (art. 290) e vedado à agência "
                               "sobre os valores deduzidos (art. 291). Operadoras/guias/promoção turística ficam para revisão.")

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B36: regime específico das agências de turismo (LC 214, arts. 288-291) numa versão nova; "
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
