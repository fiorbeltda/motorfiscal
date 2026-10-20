"""Extract DPS issuer field paths and scalar facets from the official NT004 XSD ZIP."""

import hashlib
import json
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
XSD_ZIP = ROOT / "sources/official/nfse-esquemas_xsd-rtc-v1-00-20251210.zip"
SCHEMA_NS = "http://www.w3.org/2001/XMLSchema"
X = "{" + SCHEMA_NS + "}"


def scalar_facets(simple_type):
    restriction = simple_type.find(f"{X}restriction")
    if restriction is None:
        return {}
    result = {"base": restriction.get("base", "")}
    enums = [item.get("value") for item in restriction.findall(f"{X}enumeration")]
    if enums:
        result["enum"] = enums
    for facet in ("pattern", "length", "minLength", "maxLength"):
        item = restriction.find(f"{X}{facet}")
        if item is not None:
            result[facet] = item.get("value")
    return result


with zipfile.ZipFile(XSD_ZIP) as archive:
    documents = [ET.fromstring(archive.read(name)) for name in archive.namelist() if name.endswith(".xsd")]

types = {}
global_elements = {}
for document in documents:
    for node in document:
        name = node.get("name")
        if name and node.tag in {f"{X}complexType", f"{X}simpleType"}:
            types[name] = node
        elif name and node.tag == f"{X}element":
            global_elements[name] = node


def children_of(node):
    for child in node:
        if child.tag == f"{X}element":
            yield child
        elif child.tag in {f"{X}sequence", f"{X}choice", f"{X}all", f"{X}group", f"{X}complexContent", f"{X}extension"}:
            yield from children_of(child)


fields = {}


def walk(element, path, visited):
    type_name = element.get("type", "").split(":")[-1]
    declared = types.get(type_name)
    inline = element.find(f"{X}complexType")
    if inline is None:
        inline = element.find(f"{X}simpleType")
    type_node = inline if inline is not None else declared
    if type_node is None:
        return
    if type_node.tag == f"{X}simpleType":
        fields[path] = {"xsd_type": type_name, **scalar_facets(type_node)}
        return
    if type_name in visited:
        return
    for child in children_of(type_node):
        name = child.get("name")
        if name:
            walk(child, f"{path}.{name}", visited | {type_name})


walk(global_elements["DPS"], "DPS", set())
fields = {key: value for key, value in sorted(fields.items()) if key.startswith("DPS.infDPS.")}
expected = {
    "DPS.infDPS.tpEmit", "DPS.infDPS.cMotivoEmisTI", "DPS.infDPS.prest.NIF",
    "DPS.infDPS.prest.end.endExt.cPais", "DPS.infDPS.IBSCBS.finNFSe",
    "DPS.infDPS.IBSCBS.indDest", "DPS.infDPS.IBSCBS.cIndOp",
    "DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.CST",
    "DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.cClassTrib",
}
missing = expected - fields.keys()
if missing:
    raise RuntimeError(f"Official XSD does not contain required projection fields: {sorted(missing)}")

result = {
    "version": "NT004-DPS-XSD-2025-12-10",
    "source_file": XSD_ZIP.name,
    "source_sha256": hashlib.sha256(XSD_ZIP.read_bytes()).hexdigest(),
    "root_schema": "DPS_v1.01.xsd",
    "fields": fields,
}
output = ROOT / "catalogs/rtc-dps-xsd-fields-nt004-2025.12.10.json"
payload = json.dumps(result, ensure_ascii=False, indent=2) + "\n"
if output.exists() and output.read_text(encoding="utf-8") != payload:
    raise RuntimeError(f"Refusing to overwrite changed artifact: {output}")
output.write_text(payload, encoding="utf-8")
print(f"{len(fields)} DPS scalar paths; {output.name}")
