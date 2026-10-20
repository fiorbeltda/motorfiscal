"""Create the next candidate without rewriting the historical Phase 4B bundle."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = root / "bundles/consumption-phase4b-2026.09.25-1.json"
target = root / "bundles/consumption-phase4b-2026.09.28-1.json"
controls_path = root / "production/phase4b-controls-2026.09.28-1.json"

manifest = json.loads(source.read_text(encoding="utf-8"))
controls = json.loads(controls_path.read_text(encoding="utf-8"))
controls_hash = hashlib.sha256(
    json.dumps(controls, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
).hexdigest()

manifest["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-1"
manifest["version"] = "2026.09.28-1"
manifest["knowledge_available_at"] = "2026-09-28T10:45:00-03:00"
manifest["status"] = "SANDBOX_CANDIDATE"
manifest["predecessor_bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.25-1"
manifest["production_controls"] = {
    "path": "production/phase4b-controls-2026.09.28-1.json",
    "sha256": controls_hash,
}
manifest["notice"] = (
    "RTC candidate pending integrated production controls, legal sign-off, future rates, "
    "special treatments, municipal sources and document deployment proof."
)

payload = json.dumps(manifest, ensure_ascii=False, indent=2) + "\n"
if target.exists() and target.read_text(encoding="utf-8") != payload:
    raise SystemExit("Refusing to overwrite a changed candidate bundle")
target.write_text(payload, encoding="utf-8")
print(manifest["bundle_id"], controls_hash)
