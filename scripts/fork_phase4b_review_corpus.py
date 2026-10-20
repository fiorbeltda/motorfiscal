"""Fork and verify the 53 historical RTC scenarios against the new candidate bundle."""

import json
import subprocess
from datetime import datetime
from pathlib import Path

root = Path(__file__).resolve().parents[1]
fiscal = root / "resources/fiscal"
old_scenarios = fiscal / "scenarios/consumption-phase4b"
old_expected = fiscal / "expected/consumption-phase4b"
new_scenarios = fiscal / "scenarios/consumption-phase4b-2026.09.28-1"
new_expected = fiscal / "expected/consumption-phase4b-2026.09.28-1"
new_bundle = "bundles/consumption-phase4b-2026.09.28-1.json"
manifest = json.loads((fiscal / new_bundle).read_text(encoding="utf-8"))
minimum_knowledge = "2026-09-28T11:00:00-03:00"


def write_immutable(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


prepared = []
for source_path in sorted(old_scenarios.glob("*.json")):
    scenario = json.loads(source_path.read_text(encoding="utf-8"))
    if datetime.fromisoformat(scenario["knowledge_at"]) < datetime.fromisoformat(minimum_knowledge):
        scenario["knowledge_at"] = minimum_knowledge
    scenario["rule_bundle_id"] = manifest["bundle_id"]
    prepared.append((source_path.name, scenario))

for name, scenario in prepared:
    write_immutable(new_scenarios / name, scenario)

results = []
for name, _ in prepared:
    path = f"resources/fiscal/scenarios/consumption-phase4b-2026.09.28-1/{name}"
    command = ["docker", "compose", "exec", "-T", "app", "php", "artisan", "fiscal:rtc:run", path,
               f"--bundle-manifest={new_bundle}"]
    completed = subprocess.run(command, cwd=root, capture_output=True, text=True, check=False)
    if not completed.stdout.strip():
        raise RuntimeError(f"Scenario runner produced no JSON for {name}: {completed.stderr}")
    actual = json.loads(completed.stdout)
    baseline = json.loads((old_expected / name).read_text(encoding="utf-8"))
    for field in ("status", "reason_codes", "missing_facts"):
        if actual[field] != baseline[field]:
            raise RuntimeError(f"Behavior changed for {name}: {field}: {actual[field]} != {baseline[field]}")
    results.append((name, {field: actual[field] for field in
                           ("status", "reason_codes", "missing_facts", "decision_hash")}))

for name, result in results:
    write_immutable(new_expected / name, result)

print(f"Verified {len(results)} candidate scenarios against the historical behavior")
