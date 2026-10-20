# Preparação97 - resultado municipal de software em São Paulo

Atualização98:componente agora instalado em integração opt-in versionada;
392 testes/5.989 verificações dirigidas aprovados,sem aceitação final98 ainda.
Ver `phase4b-sp98-current-integration.md`. O relatório97 abaixo preserva seu corte
de preparação e a integridade96 anterior à instalação,não o runtime atual98.

Em16/10/2026, preparação isolada verificada:140 casos nativos/2.314 verificações,
PHPStan e Pint de dois arquivos aprovados.22 candidatos locais,2 exceções à
exportação e116 recusas. Não são testes Pest adicionais nem aceitação de integração.
O runtime e bundle96 continuam aceitos em desenvolvimento local:2.962 testes/
51.784 verificações. A auditoria97 conferiu novamente6.121 entradas e75 hashes
runtime96,sem alteração. Nenhuma regra97 instalada no engine.

## Fontes e alcance

Sete fontes primárias capturadas,com URL inicial/final,hash e observação no D:.
A primeira tentativa Python falhou na cadeia de certificados de seis fontes.
Foi preservada. A segunda usou a validação TLS padrão do Windows,sem bypass.
As quatro páginas dos dois PDFs foram renderizadas e inspecionadas visualmente.
Intermediários foram mantidos como evidência no D:,sem carga volumosa no C:.

- [PN SF4/2016,arts1-4](https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/consolidado):
  critério municipal de resultado para estabelecimento prestador em São Paulo;
  para item1, vínculo do software/sistema/base/equipamento a pessoa no Brasil
  impede a exportação. A ausência do impedimento exige prova documental própria,
  não mera declaração de consumo RTC estrangeiro. Etapas mensais são consideradas
  em serviços continuados; entrega final do produto não é requisito geral do resultado.
- [Detalhes oficiais do PN](https://legislacao.prefeitura.sp.gov.br/parecer-normativo-secretaria-municipal-da-fazenda-4-de-10-de-novembro-de-2016/detalhe/5dd7ea491411926001a56893):
  assinatura09/11/2016,publicação10/11/2016,situação sem revogação expressa no portal.
  Isso não comprova toda aplicabilidade histórica,legislação superveniente ou decisões judiciais.
  Art4 alcança consultas anteriores conflitantes,não revoga indistintamente todas.
- [SC51/2012,pp1-2,itens8-8.1](https://www.prefeitura.sp.gov.br/cidade/secretarias/upload/arquivos/secretarias/financas/consultas/SC051-2012.pdf):
  importação continuada de engenharia7.03/7.17,com câmbio BCB de fechamento/venda
  no último dia útil do mês da efetiva prestação,no contexto examinado.
- [SC8/2015,pp1-2,itens7-8.2](https://drive.prefeitura.sp.gov.br/cidade/upload/SC008-2015_1456839085.pdf):
  consultoria importada17.01,conclusão com relatório final e câmbio BCB de
  fechamento/venda no dia da conclusão,distinto do pagamento,no contexto examinado.
- [CTN143](https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm):
  conversão no lançamento ligada ao fato gerador,ressalvada lei contrária.
  O artigo isolado não prova cotação de venda,pagamento ou política genérica de exportação.
- [Lei13701,art2](https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado)
  e [Decreto53151,arts16-17 e117](https://legislacao.prefeitura.sp.gov.br/decreto-53151-de-17-de-maio-de-2012/consolidado):
  cláusulas selecionadas confrontadas; não foi feita auditoria integral de toda legislação municipal.

Não transpor as duas consultas de importação para exportação de software,entre
municípios ou para escolher automaticamente uma única data cambial.

## Contrato preparado

`SpSoftwareExportResult97-formatted.php`,namespace `Phase4bPrepared`,está somente
no diretório de evidências e em `/tmp/phase4b-sp97` do contêiner.
Reutiliza o qualificador nacional95 aceito,sem modificá-lo. Exige prova municipal
com hash,período,observação e ciclo de vida próprios,vinculada ao hash da prova95,
à operação,documento,partes e data. Não autentica fonte ou realidade econômica.

Exige estabelecimento3550308,item1,sujeito do resultado identificado/localizado,
ativo de software identificado e avaliação documental de vínculo com pessoaBR.
Ausente/desconhecido não equivale a `NO_BR_LINK`. Sujeito estrangeiro com vínculoBR
resulta em exceção à exportação,não candidato à não incidência.
ResultadoBR afasta apenas a exportação: não gera automaticamente tributação1,
base,alíquota ou imposto. A projeção candidata3 fica restrita ao resultado
estrangeiro sem vínculoBR documentado,e continua sem autorização documental.

Serviço continuado exige etapa mensal,período e conclusão vinculados; não exige
entrega final do produto. Isso não qualifica materialmente a data do fato gerador
ISS nem o calendário/câmbio aplicável. Política com observação16:28:22.913454Z
é limite de conhecimento da captura,não prova da vigência legal desde essa data.

SHA do PN capturado:`066427dc1da3c25fddd1628bc3c168d1805665e113b2d7c5bbb894825e20a274`.
SHA do componente formatado:`24c5434e2ef5e86dc7b11d90662e1ccaac14eba5133fee80c018985fdff0aa24`.

## Verificação e limites

Corpus contempla quatro espécies de sujeito e quatro de ativo,provas trocadas/
alteradas/resignadas,documentos/partes divergentes,ausência de vínculo documentado,
escopo misto,incompatibilidade nacional/municipal,etapas mensais e temporalidade
da evidência. Invariância de ordem dos mapas conferida.
A primeira execução preserva uma expectativa de teste incorreta para hash ausente:
o contrato exato é exigido antes da comparação do hash. Apenas essa razão foi
corrigida no corpusv2,sem modificar o caso ou autorizar resultado positivo.
Falha inicial de anotação PHPStan e formatação preservadas;gates finais aprovados.

Evidência principal:
`D:/ProjetoSaasExports/phase4b-document97-sp-primary-research-20261016-v2/sp97-preparation-validation.json`.
Fontes,revisão visual,código,casesv1/v2 e saídas nativas no mesmo diretório.
Reprodução:os scripts de captura,preparação e auditoria são exclusivos;não rerodar
contra os mesmos destinos. O auditor compara arquivos executados/formatações,
hashes das fontes,corpus e a aceitação96 sem sobrescrevê-la.

Próximo escopo: integrar97 aos fatos atuais e aos dois resultados96,com vínculo
do município do prestador,duas provas independentes,novo bundle,tests permanentes,
corpus nativo e replays;depois regressão completa e auditoria da nova integração.
Conversão/base ISS por documento,identidade fiscal,par XML,demais80 requisitos e
aprovação de produção continuam pendentes. Todos os oito grupos4B permanecem parciais.
