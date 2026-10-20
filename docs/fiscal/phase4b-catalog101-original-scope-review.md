# Revisão delimitada101 do requisito original de catálogos

Revisão16/10/2026 durante a regressão101 ativa. Não é auditoria final dos80
itens nem aceite da regressão. Nenhuma entrada congelada foi modificada.

## Requisito e prova necessária

O plano original,seção4B.6,exige materializar/versionar cIndOp,cClassTrib,NBS,
correlação oficial existente e regra decisória quando não houver correlação.
Exige distinguir `OFFICIAL_CORRELATION` de `ENGINE_DECISION_RULE` e obter o
artefato oficial atual cClassTrib,com preservação local,hash,versão e vigência.
Índice dos itens:inventário72,4B.6.1–4B.6.10. Não substituir essas exigências
pelo número de drafts ou pelo volume de testes.

## Evidência efetivamente examinada

1. Preparação99:seis capturas primárias,planilha1.70 e informe técnico preservados,
   identificação173 classes/18CST,comparação com a fonte anterior164 classes.
   Nove códigos adicionais,tpDoacao novo e NFSe410028 desabilitado. A revisão
   anterior incluiu13 páginas PDF e172 linhas ocultas;esta revisão usa os
   relatórios e artefatos já verificados,não declara nova análise da planilha/PDF.
2. Preparação100:contrato vincula SHA da planilha,instante de observação e hashes
   dos mapas completos.2.981 casos nativos/37.029 verificações isoladas;
   essas contagens não são a regressão integrada101.
3. Código instalado101:o verificador é chamado após seleção de regra explícita
   nos dois caminhos que consultam cClassTrib,padrão e parcela doméstica.
   `REFERENCE_ROW_AVAILABLE` não escolhe tratamento,alíquota ou campos XML.
4. Testes permanentes101:122 comparações pelos dois executores mantêm os
   status/motivos98;20 testes adicionais exercitam recusas de fonte/política,
   modeloNFSE410028,limite de1 microssegundo,históricos e assessor.
   Conjunto dirigido terminal:3.186 testes/40.482 verificações.
5. CorpusCLI101 terminal:61 cenários/122 decisões e replays próprios,215 replays
   históricos exatos,66 fontes válidas e122 bloqueios de produção.
6. Comparação local dos recursos98/101:alíquotas,transição,facts,perguntas,
   `engine_decision_rules`,`correlations` e `cindop` idênticos. O novo catálogo
   altera a referência,não transforma correlação em regra decisória.

Fonte1.70:SHA `bee3e106d24c06257d31105658679e97e2544dcc3ac61467fbe0ca4f78c6e850`.
Observação `2026-10-16T16:56:31.622022+00:00`;publicação no índice01/10/2026.
Vigência por linha é independente da publicação/observação;fim é inclusive.
Não se atribui conhecimento retroativo do conteúdo exato nem implantação remota.

## O que continua não demonstrado

- Regressão integral101 terminal,integridade final e auditoria independente.
- Encerramento funcional de todos os mapeamentos cIndOp/NBS/correlações.
  Preservá-los byte/semanticamente não prova atualidade ou cobertura integral.
- Qualificação fiscal automática dos novos códigos de autônomos e das doações.
  `tpDoacao` consultável não prova emissão documental nem incidência específica.
- Projeção do par documental da exportação parcial,conversão/base por documento,
  identidades fiscais,data material,XML e demais requisitos4B.7/4B.8.
- Aprovação legal,produção e encerramento de qualquer um dos oito grupos4B.

Portanto,4B.6.3/4B.6.10 têm nova evidência concreta de referência atual preservada
e integração local,mas esta revisão não promove o grupo4B.6 inteiro nem os80
itens a concluídos. A última aceitação completa permanece98 enquanto101 executa.

## Localização das evidências

- `D:/ProjetoSaasExports/phase4b-catalog99-primary-research-20261016/catalog99-preparation-validation.json`.
- `D:/ProjetoSaasExports/phase4b-catalog100-reference-preparation-20261016/catalog100-reference-preparation-validation.json`.
- `D:/ProjetoSaasExports/phase4b-catalog101-integration-20261016/directed-v5.json`.
- `D:/ProjetoSaasExports/phase4b-catalog101-integration-20261016/cli/catalog101-cli-validation.json`.
- `D:/ProjetoSaasExports/phase4b-catalog101-full-suite-20261016/preflight.json`.
- `resources/fiscal/bundles/consumption-phase4b-2026.10.16-101-draft1.json`.
- `tests/Fiscal/Integration/CatalogReference101Test.php`.
- `tests/Fiscal/Unit/CatalogReferenceEngine101Test.php`.

Esses caminhos identificam provas com escopos diferentes;não são equivalentes
entre si e não substituem o resultado terminal ainda pendente.
