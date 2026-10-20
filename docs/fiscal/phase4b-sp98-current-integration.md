# Integração98 - resultado municipalSP nos fatos atuais

Em16/10/2026,preparação97 instalada com contrato próprio no fluxo opt-in da
exportação parcial atual. Validação dirigida após formatação:392 testes/
5.989 verificações aprovados,com211 testes novos permanentes. PHPStan/Pint554
gerais aprovados.60 decisões/replays próprios e155 históricos exatos aprovados,
62 fontes válidas e121 bloqueios confirmados.98 aceito em desenvolvimento local:
regressão terminal3.173 testes/55.383 verificações,exit0;7.577 entradas intactas,
77 hashes runtime e duas cópias conferidos. PHPStan/Pint554 finais e auditoria
independente aprovados;96 e suas evidências preservados.

Aceitação:`storage/app/phase4b-sp98-development-20261016/sp98-development-validation.json`,
SHA:`29b80efd975c9a5597938aefd55e77a6f0fca7d7dd779efc9e220342b3a8fad7`.
Bundle98 SHA:`924062f885ce01442b5911a8bcc351224dd8d57899e11b5e8adc693bcc2ff439`.
99/100 permanecem preparações isoladas;98 ainda usa referência cClassTrib1.60,
pendente de migração versionada. Este aceite não encerra a Fase4B.

## Implementação

- `app/Modules/Fiscal/ISS/SpSoftwareExportResult97.php`: mesmo componente97 preparado,
  com mudança apenas de namespace e remoção do import que passou a ser local.
  Corpus permanente140 casos,sem substituir suas limitações por autorização fiscal.
- `CurrentPartialExportSp98`: liga provas municipais por papel aos dois contextos95/96
  atuais,com operação,documento,partes,data e hash nacional correspondentes.
  Também exige que `provider.municipality_ibge` seja3550308; não utiliza município
  do destinatário para fingir estabelecimento prestador em São Paulo.
- `ConsumptionReformEngine`: novo opt-in `rtc.assess_export_sp_results` exige a
  avaliação nacional96 solicitada. Rota parcial digital e combinações temporais
  continuam submetidas aos gates anteriores. Câmbio,alocação,classificação e cálculo
  nominal RTC precedem os resultados nacionais e municipais.
- Duas provas em `rtc.export_sp_result_proofs`,com papéis `RTC_FOREIGN` e
  `RTC_DOMESTIC`,campo `municipal_proof`. Papéis não impõem onde ocorre o resultado
  ISS. Troca de provas entre documentos,hashes e vínculos divergentes são recusados.
- Stage `PARTIAL_EXPORT_SP_RESULTS` somente quando solicitado e após96 válido.
  Sem solicitação,não existe stage novo;provas irrelevantes/malformadas são ignoradas
  pelo ramo não solicitado. Sem avaliação96 solicitada,98 pede contexto nacional.

O candidato municipal estrangeiro depende de sujeito estrangeiro e ausência
documentada de vínculo do software a pessoaBR. VínculoBR afasta a exportação
mesmo com consumo RTC estrangeiro;isso não gera automaticamente tributação1.
Uma parcela doméstica RTC pode ter resultado ISS estrangeiro se provas próprias
assim indicarem. Consulte as fontes e limites em `phase4b-sp97-municipal-result-preparation.md`.

Continuação mensal exige prova da etapa,mas não autoriza inferir regra cambial,
data material de fato gerador ou exigência geral de entrega final do produto.
Evidência local autodeclarada/com hash não autentica contribuinte,documento ou governo.

## Recursos e verificação

Bundle `consumption-phase4b-2026.10.16-98-draft1.json`,catálogo,facts/perguntas e
controles próprios. Duas fontes adicionais observadas do PN4/2016 e seus detalhes,
com62 fontes no total. Publicação10/11/2016 não foi usada para declarar toda
vigência histórica comprovada: `effective_from` permanece nulo no inventário.
Catálogos de alíquotas,transição,classificação prévia e bundles anteriores preservados.

Readiness adiciona `SP_SOFTWARE_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY` aos120 bloqueios
anteriores. Candidato local não libera produção nem altera controles paraAPPROVED.

30 cenários próprios contemplam USD,BRL,fim de semana,percentuais,ambos os sentidos
de independência RTC/ISS,vínculoBR,etapas mensais,ordenação,ausências,troca entre
documentos,adulteração,origem municipal divergente e antecedência dos gates nacionais.
211 novos testes:140 do componente97,11 do vínculo atual98 e60 pelos dois runners.
Os392 dirigidos incluem181 anteriores; não somar novamente estes181 ao crescimento.

Primeira verificação Pint apontou apenas estilo em `CurrentPartialExportSp98`.
Arquivo anterior e falha preservados no D:,formatação restrita a esse arquivo;
testes/PHPStan/Pint gerais repetidos com labelsv2 após a correção.
Durante a formatação,o corpus CLI já havia concluído seus30 casos próprios e
estava apenas nos históricos de bundles anteriores,que não executam o componente98.

Arquivo anterior do engine,assessor,console e documentos arquivado após conferir
6.121 entradas e75 hashes da aceitação96. Não reescrever essa aceitação para o código98.
Runtime98 possui77 hashes conferidos na aceitação. Durante a regressão,o console
corretamente indicou mudança desde96. Agora acompanha o aceite98 por hash,
sem reescrever o histórico96 ou tratar100 como já instalado.

Evidências em `D:/ProjetoSaasExports/phase4b-sp98-integration-20261016`:
`before-integration-archive.json`,`sp98-bundle-installation.json`,
`sp98-case-manifest.json`,checks direcionados e corpus CLI.
Regressão exclusiva encerrada em `D:/ProjetoSaasExports/phase4b-sp98-full-suite-20261016`,
com `preflight.json`,`process-start.json` e `full-suite.log`. Só duas alterações
entre as6.121 entradas96 foram autorizadas:engine e assessor. Scripts verificadores
também congelados no98. Entradas intactas conferidas antes/depois e na auditoria final.
Scripts de captura e instalação são exclusivos:não repetir destinos ou reiniciar
processo vivo por timeout. Resultado efetivo:3.173 testes/55.383 verificações.
`sp98-regression-validation.json` e `sp98-development-validation.json` são os
relatórios finais. Não reiniciar o processo terminal nem rerodar auditores exclusivos.

## Pendências que continuam

Resultado nominal continua `ACTION_REQUIRED / RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`.
Não calcula imposto ISS por documento,não converte moeda documental,não reescreve
ISS integral,não qualifica identificador fiscal/data material,não gera XML,não
autoriza lançamento,não prova vigência integral e não libera produção.
As duas consultas de importação não se tornaram política cambial de exportação.

Depois dos gates98,qualificar conversão/base por documento e identidades fiscais,
materializar par XML sob contrato de schema/ambiente/data comprovado. Demais famílias,
períodos e auditoria dos80 requisitos continuam no objetivo integral4B.
Fases3/4A encerradas funcionalmente em Sandbox;4B parcial;5-7 planejadas.
