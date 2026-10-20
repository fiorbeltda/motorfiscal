# Pesquisa documental92 — fontes atuais, sem ativação

Captura de15/10/2026,17:34UTC, paralela à regressão91. Preparação da frente
documental, não implementação ou aceitação de XML. Os inputs congelados da91
foram conferidos por hash e permaneceram intactos.

Evidência exclusiva em
`D:/ProjetoSaasExports/phase4b-document92-primary-research-20261015/`:
`primary-source-observation.json`, `schema-closure-inspection.json` e
`document92-source-comparison.json`, com URL, observação, tamanho e SHA-256.

## Resultado observado

Sete capturas com conteúdo validado: três portais, dois PDFs e dois ZIPs.
Os quatro binários coincidem byte a byte com a pesquisa74:

- NT009v1.01: `cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e`.
- Ato Técnico Conjunto7: `95f2427ac7f5dc81997a600850e6ac0fda4521006ccbafe70779f24f65c9ab57`.
- ZIP produção: `e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072`.
- ZIP restrito: `6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc`.

A tentativa da imagem do cronograma foi preservada, mas retornou HTML de login;
`content_verified=false`. O arquivo com extensão jpeg não é imagem válida.
Não substitui nem revalida a evidência visual histórica74.

Os fechamentos DPS1.01 examinados têm quatro membros cada e continuam sem
`nFatura`, `vFatura`, `nContCambio`, `nDUIMP`, `nDUE` e
`vAjusteBCIBSCBSComExt`. A conclusão é limitada aos links e fechamentos observados,
não prova inexistência de qualquer outro artefato oficial nem implantação remota.

## Vínculo que a próxima implementação precisa resolver

A NT009, seção2.12, páginas15–17, distingue a parcela estrangeira da brasileira
e pede documentos separados na exportação mista. Páginas16 e18 renderizadas e
inspecionadas visualmente com a skillPDF, além da extração textual. Seção2.13
separa ajuste de base RTC por câmbio do valor do serviço informado no documento.

O runtime já produz alocação e nominal doméstico em
`ConsumptionReformEngine::partialExportDomesticLeg`, mas para em
`RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`. O candidato76 de comprador
estrangeiro é doméstico/estrutural e usa os domínios antigos; não deve ser
reutilizado como prova de comércio exterior misto NT009.

Na próxima implementação, ligar os dois documentos à mesma operação, fonte,
alocação e decisão; separar identidades e destinatários; conciliar valores
RTC com a base documental e o ISS efetivamente qualificados. Não copiar a
conversão RTC para o ISS nem repartir seu imposto automaticamente. Se houver
conversões diferentes, preservar ajuste de base com memória própria. Não mudar
o significado dos modos3/4 em snapshots antigos e não inserir modo5 no XSD
antigo para obter aprovação artificial.

Ainda faltam seleção/qualificação temporal do leiaute aplicável, mapper,
identidades, tabelas, regras de negócio, dois documentos completos, invariantes,
corpus e replays. Um plano de parcelas ou XML manual não encerra essa obrigação.
Schema, runtime, produção e status da Fase4B não foram promovidos nesta pesquisa.

Fontes oficiais consultadas:
[portal RTC](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc),
[homologação](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita),
[NT009v1.01](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nota-tecnica-009-se-cgnfs-e-v-1-01.pdf).
