# Exportação 79 — tipos ISS do schema observado

Inspeção em 15/10/2026, sem alterar o runtime em regressão78. Artefato fixado:
`resources/fiscal/sources/official/nfse-restricted-xsd-observed-20261014-20260727.zip`,
SHA-256 `6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc`.
Não é afirmação de implantação remota ou de disponibilidade de um pacote novo.

Relatório com fragmentos exatos e hashes dos membros:
`D:/ProjetoSaasExports/phase4b-export79-schema-inspection-20261015/export-iss-schema-types.json`.
Script: `D:/ProjetoSaasExports/inspect_export79_schema.py`.

## Tipos e sequência efetivamente encontrados

`TCTribMunicipal` começa com `tribISSQN`, seguido de `cPaisResult` opcional,
`tpImunidade` opcional, grupos de suspensão/benefício opcionais, `tpRetISSQN`
obrigatório e `pAliq` opcional. O tipo de `tribISSQN` é `TSTribISSQN`, cuja
documentação distingue 1 tributável, 2 imunidade, 3 exportação e 4 não incidência.
Assim, o código de exportação nesse contrato não é o 2 já usado na rota
institucional nem o 4 genérico de não incidência. Não reutilizar enumerações
textuais de outros grupos como substitutas do tipo aplicável.

`cPaisResult` documenta o país de resultado da prestação para exportação.
Sua ocorrência XSD é opcional; seu tipo `TSCodPaisISO` tem apenas pattern
`[A-Z]{2}` na restrição observada. Isso não é enumeração de países reais,
qualificação de residência ou prova do local de resultado. A ocorrência
opcional no leiaute tampouco dispensa evidência fiscal independente.

`tpImunidade` usa `TSTipoImunidadeISSQN` e tem escopo declarado de imunidade.
Seu uso não deve ser transplantado para uma rota de exportação. `tpRetISSQN`
distingue 1 não retido, 2 retido pelo tomador e 3 pelo intermediário. A
faceta desses campos não prova sozinha a compatibilidade entre eles nem a
incidência de ISS da operação. Ainda não foi produzido XML de exportação79.

## Relação com a lacuna anterior

O [diagnóstico77](phase4b-export77-document-gap.md) mostra o retorno RTC de
exportação sem documento e o ISS tributável da reprodução preservada. Esta
inspeção identifica o contrato estrutural necessário, mas não autoriza mudar
a incidência da decisão existente ou preencher códigos RTC por aproximação.
A qualificação ISS precisa de fatos/evidência do resultado, separada de IBS/CBS;
país, pessoa estrangeira, CST/cClassTrib e consumo misto também seguem próprios.

Nenhum schema oficial foi normalizado, corrigido ou editado. Não há ativação,
assinatura, transmissão, qualificação documental integral ou aceitação de
produção nesta pesquisa. A exportação parcial e a matriz original de requisitos
permanecem abertas independentemente do resultado futuro da suíte78.
