"""Extract DPS scalar paths from the official 2026-02-09 production XSD package."""

import hashlib
import json
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
archive_path = root / "sources/official/nfse-esquemas_xsd-v1-01-20260209.zip"
prefix = "Schemas/1.01/"
x = "{http://www.w3.org/2001/XMLSchema}"


def scalar_facets(node):
    restriction = node.find(f"{x}restriction")
    if restriction is None:
        return {}
    result = {"base": restriction.get("base", "")}
    enums = [item.get("value") for item in restriction.findall(f"{x}enumeration")]
    if enums:
        result["enum"] = enums
    for facet in ("pattern", "length", "minLength", "maxLength"):
        item = restriction.find(f"{x}{facet}")
        if item is not None:
            result[facet] = item.get("value")
    return result


with zipfile.ZipFile(archive_path) as archive:
    documents = [ET.fromstring(archive.read(name)) for name in archive.namelist()
                 if name.startswith(prefix) and name[len(prefix):].endswith(".xsd")
                 and "/" not in name[len(prefix):]]

types = {}
global_elements = {}
for document in documents:
    for node in document:
        name = node.get("name")
        if name and node.tag in {f"{x}complexType", f"{x}simpleType"}:
            types[name] = node
        elif name and node.tag == f"{x}element":
            global_elements[name] = node


def children_of(node):
    for child in node:
        if child.tag == f"{x}element":
            yield child
        elif child.tag in {f"{x}sequence", f"{x}choice", f"{x}all", f"{x}group",
                           f"{x}complexContent", f"{x}extension"}:
            yield from children_of(child)


fields = {}


def walk(element, path, visited):
    type_name = element.get("type", "").split(":")[-1]
    declared = types.get(type_name)
    inline = element.find(f"{x}complexType")
    if inline is None:
        inline = element.find(f"{x}simpleType")
    type_node = inline if inline is not None else declared
    if type_node is None:
        return
    if type_node.tag == f"{x}simpleType":
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
required = {
    "DPS.infDPS.tpEmit", "DPS.infDPS.cMotivoEmisTI", "DPS.infDPS.prest.NIF",
    "DPS.infDPS.prest.end.endExt.cPais", "DPS.infDPS.IBSCBS.finNFSe",
    "DPS.infDPS.IBSCBS.indDest", "DPS.infDPS.IBSCBS.cIndOp",
    "DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.CST",
    "DPS.infDPS.IBSCBS.valores.trib.gIBSCBS.cClassTrib",
}
if required - fields.keys():
    raise RuntimeError(f"Production XSD lacks projected fields: {sorted(required - fields.keys())}")

result = {
    "version": "NFSE-PRODUCTION-DPS-XSD-2026-02-09",
    "source_file": archive_path.name,
    "source_sha256": hashlib.sha256(archive_path.read_bytes()).hexdigest(),
    "root_schema": prefix + "DPS_v1.01.xsd",
    "fields": fields,
}
output = root / "catalogs/rtc-dps-xsd-fields-production-2026.02.09.json"
payload = json.dumps(result, ensure_ascii=False, indent=2) + "\n"
if output.exists() and output.read_text(encoding="utf-8") != payload:
    raise RuntimeError(f"Refusing to overwrite changed artifact: {output}")
output.write_text(payload, encoding="utf-8")
print(f"{len(fields)} DPS scalar paths; {output.name}")
