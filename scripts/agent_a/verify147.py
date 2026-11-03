"""Independent datetime oracle and integrity checks for the lifecycle parser correction."""
import hashlib
import json
import subprocess
from datetime import datetime
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A147-temporal-lifecycle-20261103-v1")
CODE = "app/Modules/Fiscal/ConsumptionReform/TaxPointContext86.php"


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def suite(filename, expected_success):
    path = ROOT / "storage/app" / filename
    xml = ElementTree.parse(path)
    cases = xml.findall(".//testcase")
    failed = xml.findall(".//failure")
    errors = xml.findall(".//error")
    if expected_success:
        assert not failed and not errors, filename
    else:
        assert failed or errors, "The previous implementation did not reproduce the defect"
    preserve_bytes(FOLDER / filename, path.read_bytes())
    return {"file": filename, "tests": len(cases), "failures": len(failed), "errors": len(errors),
            "assertions": sum(int(case.get("assertions", "0")) for case in cases), "sha256": sha(path.read_bytes())}


def main():
    before_source = (FOLDER / "TaxPointContext86-before.php").read_bytes()
    assert sha(before_source) == sha(subprocess.check_output(["git", "show", "HEAD:" + CODE], cwd=ROOT))
    assert sha(before_source) != sha((ROOT / CODE).read_bytes())
    before = json.loads((FOLDER / "probe-before.json").read_text(encoding="utf-8-sig"))
    after = json.loads((FOLDER / "probe-after.json").read_text(encoding="utf-8-sig"))
    changed = 0
    observations = []
    for old, new in zip(before["observations"], after["observations"], strict=True):
        assert (old["field"], old["lifecycle_at"], old["known_at"]) == (new["field"], new["lifecycle_at"], new["known_at"])
        known = datetime.fromisoformat(new["known_at"])
        cutoff = datetime.fromisoformat(new["lifecycle_at"])
        expected = "RESOLVED" if known < cutoff else "ACTION_REQUIRED"
        assert new["observed"]["status"] == expected
        if old["observed"] != new["observed"]:
            changed += 1
            assert old["observed"]["reason"] == "RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID"
        observations.append({"field": new["field"], "cutoff": new["lifecycle_at"], "known": new["known_at"],
                             "independent_datetime_expected": expected, "observed": new["observed"]["status"]})
    assert changed == 6
    assert (FOLDER / "cli-before.json").read_bytes() == (FOLDER / "cli-after.json").read_bytes()
    cli = json.loads((FOLDER / "cli-after.json").read_text(encoding="utf-8-sig"))
    assert cli["status"] == "VALID"
    replay = json.loads((FOLDER / "cli-baseline-replay.json").read_text(encoding="utf-8-sig"))
    assert replay["replay_matches"] is True
    assert json.loads((FOLDER / "cli-exits.json").read_text(encoding="utf-8-sig")) == {"before": 0, "after": 0, "baseline_replay": 0}
    quality = json.loads((FOLDER / "quality-exits.json").read_text(encoding="utf-8-sig"))
    assert quality == {"phpstan": 0, "pint": 0}
    assert json.loads((FOLDER / "regression-exit.json").read_text(encoding="utf-8-sig"))["regression_exit"] == 0
    original = suite("agent-a-A147-before.junit.xml", False)
    targeted = suite("agent-a-A147-final.junit.xml", True)
    regression = suite("agent-a-A147-regression.junit.xml", True)
    checkpoint = json.loads((ROOT / "docs/fiscal/checkpoints/20261027-import143/import143-integration-scoped-validation.json").read_text(encoding="utf-8"))
    runtime_diff = []
    for path, expected_hash in checkpoint["runtime_hashes"].items():
        if sha((ROOT / path).read_bytes()) != expected_hash:
            runtime_diff.append(path)
    assert set(runtime_diff) <= {CODE}, runtime_diff
    receipt = {"task": "A147", "scope": "4B.1.6/4B.1.7/4B.1.8; precision and validation of existing lifecycle instants",
               "code_before_sha256": sha(before_source), "code_after_sha256": sha((ROOT / CODE).read_bytes()),
               "changed_native_observations": changed, "datetime_oracles": observations,
               "before_characterization": original, "directed_suite": targeted,
               "regression_includes_directed_do_not_add": regression, "quality_exit_codes": quality,
               "unchanged_native_cli_sha256": sha((FOLDER / "cli-after.json").read_bytes()),
               "baseline_snapshot_replay": True, "checkpoint143_runtime_differences": runtime_diff,
               "synthetic_lifecycle_not_official_revocation": True,
               "whole_requirement_accepted": False, "full_regression_executed": False,
               "global_console_edited": False, "B_received": False, "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A147.json"
    preserve_json(destination, receipt)
    print(json.dumps({"task": "A147", "directed": targeted, "regression": regression,
                      "native_differences_corrected": changed, "original_cli_preserved": True}))


if __name__ == "__main__":
    main()
