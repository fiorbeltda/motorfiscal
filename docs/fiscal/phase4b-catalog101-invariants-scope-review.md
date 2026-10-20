# Evidência delimitada4B.8.11 — invariantes no candidato101

Revisão16/10/2026,sem alterações de código,testes,recursos ou scripts congelados.
Regressão101 ainda ativa;esta revisão não constitui seu aceite terminal.

O requisito original4B.8 exige produzir invariantes. O inventário72 identifica
esse artefato como4B.8.11. A prova abaixo demonstra materialização,vínculo e
execução no cenárioSaaS positivo101;não demonstra cobertura fiscal integral.

## Caminho efetivamente examinado

O manifesto101 referencia
`invariants/consumption-phase4b-2026.10.02-5.json`,preservado do predecessor.
O arquivo contém18 definições `REQUIRED_DECISION_PATH`,com vigências para os
camposISS até2032 e o status pósISS desde2033,além de caminhos federais,DPS eRTC.

`FiscalEngineFactory` carrega o documento,calcula seu hash,verifica hashes do
manifesto e constrói `FiscalInvariantEngine` com o resultado de
`InvariantDefinitionFileLoader`. O loader recusa IDs vazios/duplicados,tipos
desconhecidos,vigências inválidas e isenção incompleta.

`RequiredDecisionPathInvariant` verifica presença e valor não nulo/vazio do
caminho,considerando a competência e uma isenção explícita de tratamento.
Não recalcula impostos,alíquotas ou base. `FiscalInvariantEngine` executa todas
as definições. `DefaultFiscalDecisionEngine` registra `INVARIANTS_EVALUATED`
e impede conclusão válida quando o resultado é bloqueante ou exige ação.
Os caminhos imunidadeISS e pósISS possuem sua própria chamada de invariantes.

O teste `InstitutionalIssImmunityTest.php` examinado verifica que a dispensa
da alíquotaISS exige incidênciaIMMUNE explícita;TAXABLE e incidência ausente
não recebem dispensa. A presença do teste não é declaração de nova execução
isolada neste corte;a regressão completa continua ativa.

## Prova nativa já terminal do corpus101

`regular-saas-positive-integrated.stdout`,no corpusCLI101 aprovado,retorna
`VALID_WITH_WARNINGS` e registra18 resultados `INVARIANTS_EVALUATED`:

- 17 caminhos presentes,válidos,severidadeINFO.
- `ISS_POST_2032_STATUS_REQUIRED` válido como fora de sua vigência,na operação
  de25/09/2026. Não significa aprovação de uma operação2033.

O caso possui snapshot preservado e replay exato no relatórioCLI101. Não é
necessário executar novo cálculo nem alterar o snapshot para examinar o trace.

HashesSHA256 conferidos nesta revisão:

- Definições: `25e4d801956bf7b040c6be571226f969b4ef82c861e520b972a0a38cbf9edd31`.
- Saída nativaSaaS: `dd5936fa4bd42a1d8b9ae46b921d7ea1d0ecde1fec24f0de85fe645d6d3d1caa`.

## Limites de aceitação

Materialização e execução estruturais demonstradas neste caso. Não confundir
presença de `consumption_reform.status` ou `dps_projection.rtc.status` com
correção integral da classificação,base,câmbio,alíquota ouXML. Esses aspectos
dependem de regras,gates e corpora específicos. Cenários interrompidos antes
da decisão final não necessariamente chegam a essa etapa do plano.

Antes de encerrar4B.8.11 no escopo integral,reunir a matriz de invariantes
relevantes por família/período e provas positivas/negativas correspondentes.
Não inventar obrigação de cálculo aritmético dentro de uma invariante de
presença;documentar separadamente quais propriedades são verificadas onde.
Nenhum dos oito grupos4B está formalmente encerrado por esta revisão.

Evidências:
`D:/ProjetoSaasExports/phase4b-catalog101-integration-20261016/cli/regular-saas-positive-integrated.stdout`,
`D:/ProjetoSaasExports/phase4b-catalog101-integration-20261016/cli/catalog101-cli-validation.json`,
manifesto101,arquivo de invariantes e classes mencionadas acima no checkout.
