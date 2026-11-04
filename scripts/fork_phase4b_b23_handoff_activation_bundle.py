"""B23: first versioned bundle that activates the A155 handoffs (H1 B02, H2 B07, H3 A155).

Forks bundle 141 without touching it (accepted bundles keep their replays and hashes).
Adds the three exact policy blocks to a new rtc_codes catalog and the eleven fact
definitions/questions the A155 contract consumes. H4 (synthetic rates) needs no gate.
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
VERSION = "2026.11.04-b23-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.10.27-141-draft1.json"
CAMARA_HASH = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"


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


PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")


NAMESPACE = "\\App\\Modules\\Fiscal\\ConsumptionReform\\"


def php_policies():
    """Read the POLICY constants through PHP itself, so each catalog block is the code's exact policy."""
    code = ("require 'vendor/autoload.php'; echo json_encode(["
            + ",".join(f"'{k}' => {NAMESPACE}{c}::POLICY" for k, c in POLICY_CLASSES.items())
            + "], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


POLICY_CLASSES = {
    "credit_zero_semantics_b02": "CreditZeroSemanticsPolicyB02",
    "annex_standard_qualification_b07": "AnnexStandardQualificationPolicyB07",
    "credit_integration_a155": "CreditIntegration155",
}
# Exact (key, type) list of A155 handoff155Knowledge(), the A-side in-memory candidate.
NEW_FACTS = [
    ("rtc.annex_noncorrespondence_justification", "STRING",
     "Se a NBS consta dos Anexos II/III e foi declarada tributação integral, justifique por que o serviço contratado não corresponde à descrição do item do anexo."),
    ("rtc.annex_noncorrespondence_evidence_id", "STRING",
     "Informe a evidência (laudo, contrato ou parecer) que comprova a não correspondência com o item do anexo."),
    ("rtc.annex_nbs_classification_confirmed", "BOOLEAN",
     "A NBS informada foi confirmada como a classificação correta do serviço contratado?"),
    ("rtc.acquired_regime", "STRING",
     "Qual o regime de tributação aplicável ao bem ou serviço adquirido (regime geral ou regime específico/diferenciado)?"),
    ("rtc.fuel_destination", "STRING",
     "Se a aquisição é de combustível, qual a sua destinação na atividade do adquirente?"),
    ("rtc.credit_exception_evidence_id", "STRING",
     "Informe a evidência da exceção que afasta a vedação de crédito, quando alegada."),
    ("rtc.employee_collective_agreement_plan", "BOOLEAN",
     "A aquisição corresponde a plano fornecido a empregados por acordo ou convenção coletiva?"),
    ("rtc.saf_sports_rights_acquisition", "BOOLEAN",
     "A aquisição é de direitos desportivos por Sociedade Anônima do Futebol?"),
    ("rtc.financial_permission_article", "STRING",
     "Para operação financeira, qual o artigo da LC 214 que permite o crédito alegado?"),
    ("rtc.presumed_credit_claims", "ARRAY",
     "Informe o pedido de crédito presumido (no máximo um), com data de aquisição, documento, adquirente e regime iguais aos da operação."),
    ("rtc.supplier_specific_credit_debits", "ARRAY",
     "Para a exceção do plano coletivo, informe os débitos IBS/CBS específicos do fornecedor, extintos, com documento e evidência."),
]


def main():
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    for key, policy in php_policies().items():
        assert key not in codes, key
        codes[key] = policy

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    rank = max(row["rank"] for row in questions) + 1
    for offset, (key, kind, prompt) in enumerate(NEW_FACTS):
        facts.append({"key": key, "type": kind, "material": True})
        questions.append({"fact_key": key, "answer_type": kind, "rank": rank + offset, "prompt": prompt})
    assert len({row["key"] for row in facts}) == len(facts)
    assert len({row["fact_key"] for row in questions}) == len(questions)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    extra = {
        "4B.5.CREDITS": " B23 (A155 ativado): B02 explica cada crédito zero; B13 aplica vedações antes da decisão; "
                        "B17 qualifica pedido presumido separado do crédito ordinário, sem apropriação automática; "
                        "apuração B11→B14 em contrato próprio. Sem integração contábil, posting ou validação externa.",
        "4B.6.CLASSIFICATION": " B23: tabela Q-B06-1 (B07) ativa para as 40 regras STANDARD com NBS dos Anexos II/III; "
                               "sem aprovação formal das regras interpretativas.",
        "4B.1.TEMPORALITY": " A155/H4: linha de alíquota sintética ou não oficial nunca é resolvida como oficial.",
    }
    for control in controls["controls"]:
        if control["id"] in extra:
            control["gap"] += extra[control["id"]]

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B23: ativa H1 (B02), H2 (B07) e H3 (A155) do commit 213f2c5 numa versão nova; "
                         "bundles anteriores permanecem sem os gates.")
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
    camara = ROOT / "sources/official/lc214-camara-observed-20261001.html"
    assert hashlib.sha256(camara.read_bytes()).hexdigest() == CAMARA_HASH
    print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available,
                      "rtc_codes_hash": bundle["content_hashes"]["rtc_code_catalog"],
                      "facts_added": len(NEW_FACTS), "policies": list(POLICY_CLASSES)}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
