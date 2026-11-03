"""Create immutable A-owned evidence; repeated identical verification never rewrites accepted bytes."""
import json
from pathlib import Path


def preserve_bytes(path, content):
    destination = Path(path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    try:
        with destination.open("xb") as stream:
            stream.write(content)
    except FileExistsError:
        if destination.read_bytes() != content:
            raise RuntimeError(f"Accepted evidence differs; preserve {destination} and use a new versioned artifact.") from None


def preserve_json(path, payload):
    destination = Path(path)
    content = (json.dumps(payload, ensure_ascii=False, indent=2, allow_nan=False) + "\n").encode("utf-8")
    destination.parent.mkdir(parents=True, exist_ok=True)
    try:
        with destination.open("xb") as stream:
            stream.write(content)
    except FileExistsError:
        # Existing Windows receipts may use CRLF. Compare typed JSON content, retaining every accepted byte.
        saved = json.loads(destination.read_bytes().decode("utf-8-sig"))
        expected = json.dumps(payload, ensure_ascii=False, sort_keys=True, allow_nan=False)
        actual = json.dumps(saved, ensure_ascii=False, sort_keys=True, allow_nan=False)
        if expected != actual:
            raise RuntimeError(f"Accepted receipt differs; preserve {destination} and use a new versioned artifact.") from None
