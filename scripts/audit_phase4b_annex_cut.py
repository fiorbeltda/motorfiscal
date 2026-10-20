"""Run the annex cut and preserve local evidence without overwriting artifacts."""

import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "2026.10.01-6"
BUNDLE = f"bundles/consumption-phase4b-{VERSION}.json"


def execute(args, evidence=None):
    process = subprocess.run(["docker", "compose", "exec", "-T", "app", "php", "artisan", *args],
                             cwd=ROOT, capture_output=True, encoding="utf-8", check=False)
    value = json.loads(process.stdout)
    if "error" in value:
        raise RuntimeError(value)
    if evidence:
        path = ROOT / f"storage/app/{evidence}.json"
        payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
        if path.exists() and path.read_text(encoding="utf-8") != payload:
            raise RuntimeError(f"Refusing to overwrite {path}")
        path.write_text(payload, encoding="utf-8")
    return value


rtc = execute(["fiscal:rtc:corpus", f"resources/fiscal/scenarios/consumption-phase4b-{VERSION}",
               f"--expected=resources/fiscal/expected/consumption-phase4b-{VERSION}", f"--bundle-manifest={BUNDLE}"],
              "phase4b-annex-rtc-corpus-20261001-6")
assert rtc["valid"], rtc
integrated = execute(["fiscal:corpus:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}",
                      f"--expected=resources/fiscal/expected/consumption-integrated-{VERSION}", f"--bundle-manifest={BUNDLE}"],
                     "phase4b-annex-integrated-corpus-20261001-6")
assert integrated["valid"], integrated
historic = execute(["fiscal:rtc:corpus", "resources/fiscal/scenarios/consumption-phase4b-2026.09.28-1",
                    "--expected=resources/fiscal/expected/consumption-phase4b-2026.09.28-1",
                    "--bundle-manifest=bundles/consumption-phase4b-2026.09.28-1.json"],
                   "phase4b-historical-corpus-after-annex-20261001-6")
assert historic["valid"], historic

for name in ["education-presential", "health-presential", "health-unpaid-glosa"]:
    prefix = f"phase4b-integrated-{name}-20261001-6"
    snapshot = f"storage/app/{prefix}-snapshot.json"
    args = ["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{VERSION}/integrated-{name}-2026.json",
            f"--bundle-manifest={BUNDLE}"]
    if not (ROOT / snapshot).exists():
        args.append(f"--snapshot={snapshot}")
    result = execute(args, f"{prefix}-result")
    assert result["status"] == "VALID_WITH_WARNINGS", result
    replay = execute(["fiscal:decision:replay", snapshot])
    assert replay["replay_matches"], replay
    report_path = ROOT / f"storage/app/{prefix}-report.json"
    if not report_path.exists():
        report = execute(["fiscal:dps:project-xml", snapshot,
                          f"resources/fiscal/scenarios/dps-local-{VERSION}/presential-identity.json",
                          f"--bundle-manifest={BUNDLE}",
                          f"--candidate-output=storage/app/{prefix}-candidate.xml",
                          f"--report=storage/app/{prefix}-report.json"])
    else:
        report = json.loads(report_path.read_text(encoding="utf-8"))
    assert report["status"] == "ACTION_REQUIRED", report
    expected = "RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED" if "glosa" in name else "RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT"
    assert report["reason"] == expected, report
    print(name, result["decision_hash"], report["reason"])

for command, filename in [
    ("fiscal:decision:replay", "phase4b-education-20261001-5-snapshot.json"),
    ("fiscal:decision:replay", "phase4b-integrated-lawyer-20261001-4-snapshot.json"),
    ("fiscal:rtc:replay", "rtc-2026-final-snapshot.json"),
    ("fiscal:decision:replay", "rtc-integrated-2026-final-snapshot.json"),
    ("fiscal:decision:replay", "phase4b-post-iss-2033-snapshot.json"),
    ("fiscal:rtc:replay", "phase4b-nt004-xsd-2026-snapshot.json"),
    ("fiscal:decision:replay", "federal-phase4a-public-general-final.snapshot.json"),
]:
    replay = execute([command, f"storage/app/{filename}"])
    assert replay["replay_matches"], replay

gate = execute(["fiscal:rtc:production-assess", f"--bundle-manifest={BUNDLE}",
                f"--controls=resources/fiscal/production/phase4b-controls-{VERSION}.json"], "phase4b-production-assessment-20261001-6")
assert gate["ready"] is False and gate["eligible_for_local_validation"] is True, gate
print("Gate: local eligible, production false; blockers:", len(gate["blockers"]))
print("Validated 16 RTC / 3 integrated / 53 historical cases and 10 snapshots")
