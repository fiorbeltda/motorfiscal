"""B28: remaining non-onerous species within NFS-e (NonOnerousSpeciesB28, LC 214 art. 5, II and IV; art. 12 par. 4).

Forks bundle B27 without touching it. Adds the exact NonOnerousSpeciesB28::POLICY and five facts with questions.
With the gate, taxed non-onerous supplies (including the existing art. 5, I species) get a market-value NFS-e
candidate with regular taxation (000001), review_status SANDBOX_REVIEW_REQUIRED.
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
VERSION = "2026.11.05-b28-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.05-b27-draft1.json"
CAMARA_HASH = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")
NAMESPACE = "\\App\\Modules\\Fiscal\\ConsumptionReform\\"

POLICY_CLASSES = {"non_onerous_species_b28": "NonOnerousSpeciesB28"}

NEW_FACTS = [
    ("rtc.market_value_method", "STRING",
     "Como foi apurado o valor de mercado? Deve ser o valor praticado em operações comparáveis entre partes não "
     "relacionadas (LC 214 art. 12 §4º): informe COMPARABLE_UNRELATED_OPERATIONS e a evidência."),
    ("rtc.related_party_hypothesis", "STRING",
     "No fornecimento não oneroso ou abaixo do mercado a parte relacionada, qual hipótese do art. 5º §§2º-3º se aplica "
     "(influência do §2º ou incisos I a VII do §3º)?"),
    ("rtc.related_party_evidence_id", "STRING",
     "Informe a evidência da relação entre as partes (quadro societário, controle, coligação, parentesco)."),
    ("rtc.gift_bonus_evidence_id", "STRING",
     "Informe a evidência do brinde ou da bonificação (campanha, termo ou regra comercial)."),
    ("rtc.bonus_in_document_without_later_event", "BOOLEAN",
     "A bonificação consta do próprio documento fiscal da venda e não depende de evento posterior (art. 5º §1º, I)?"),
]


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


def php_policies():
    code = ("require 'vendor/autoload.php'; echo json_encode(["
            + ",".join(f"'{k}' => {NAMESPACE}{c}::POLICY" for k, c in POLICY_CLASSES.items())
            + "], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


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
        "4B.2.INCIDENCE_AND_DESTINATION": " B28: art. 5º II (brindes/bonificações; bonificação no documento sem evento "
                                          "posterior = 410001, não é operação separada) e IV (parte relacionada) tributados "
                                          "pelo valor de mercado de operações comparáveis (art. 12 §4º); documento NFS-e "
                                          "candidato a valor de mercado com 000001 para as espécies tributadas, em revisão.",
    }
    for control in controls["controls"]:
        if control["id"] in extra:
            control["gap"] += extra[control["id"]]

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B28: ativa as espécies não onerosas restantes (art. 5º II e IV) e o documento candidato numa versão nova; "
                         "bundles anteriores permanecem sem o gate.")
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
