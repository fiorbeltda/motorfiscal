"""Print required DPS branches of the preserved production XSD for local fixture work."""

import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path


archive = Path(__file__).resolve().parents[1] / "resources/fiscal/sources/official/nfse-esquemas_xsd-v1-01-20260209.zip"
prefix = "Schemas/1.01/"
x = "{http://www.w3.org/2001/XMLSchema}"
with zipfile.ZipFile(archive) as source:
    documents = [ET.fromstring(source.read(name)) for name in source.namelist()
                 if name.startswith(prefix) and name[len(prefix):].endswith(".xsd")
                 and "/" not in name[len(prefix):]]

types = {}
elements = {}
for document in documents:
    for node in document:
        name = node.get("name")
        if name and node.tag in {f"{x}complexType", f"{x}simpleType"}:
            types[name] = node
        elif name and node.tag == f"{x}element":
            elements[name] = node


def walk(node, level=0, ancestors=()):
    if level > 10:
        return
    name = node.get("name") or node.get("ref", "").split(":")[-1]
    kind = node.tag.removeprefix(x)
    optional = node.get("minOccurs") == "0"
    if kind == "element":
        if optional:
            return
        print("  " * level + name + " : " + node.get("type", "inline"))
        type_name = node.get("type", "").split(":")[-1]
        definition = types.get(type_name)
        if definition is None:
            definition = node.find(f"{x}complexType")
        if definition is not None and type_name not in ancestors:
            walk(definition, level + 1, ancestors + (type_name,))
        return
    if kind == "attribute" and node.get("use") == "required":
        print("  " * level + "@" + name + " : " + node.get("type", "inline"))
    if kind == "choice":
        options = [child.get("name", "") for child in node if child.tag == f"{x}element"]
        print("  " * level + "choice: " + " | ".join(options))
        return
    for child in node:
        if child.tag in {f"{x}sequence", f"{x}all", f"{x}choice", f"{x}element", f"{x}attribute"}:
            walk(child, level, ancestors)


walk(elements["DPS"])
print("\nIBSCBS subtree:")
walk(ET.Element(f"{x}element", {"name": "IBSCBS", "type": "TCRTCInfoIBSCBS"}))
print("\nSelected simple type restrictions:")
for name in ("TSIdDPS", "TVerNFSe", "TSVerAplic", "TSSerieDPS", "TSNumDPS", "TSData",
             "TSTipoAmbiente", "TSOpSimpNac", "TSRegEspTrib", "TSTribISSQN",
             "TSTipoRetISSQN", "TSDesc2000"):
    node = types.get(name)
    if node is None:
        continue
    restriction = node.find(f"{x}restriction")
    if restriction is not None:
        print(name, [(child.tag.removeprefix(x), child.get("value")) for child in restriction])
