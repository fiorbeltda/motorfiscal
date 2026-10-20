# Candidato 21 — D2101 sem movimento no fechamento local

Objetivo concluído no recorte local: quando o PGCC qualificado contém os códigos `110113001` ou `110113002`, produzir o D2101 original com `semTitulos=1` somente para contas analíticas zeradas, sem movimentos e com prova independente de ausência de títulos a detalhar. O candidato `CONSUMPTION-PHASE4B-2026.10.06-21` sucede o20. A Fase4B permanece parcial, com produção bloqueada.

## Planejamento e limite do objetivo

Este recorte avança4B.7 (projeção documental),4B.8 (bundle, corpus, trace, hash e replay),5.4 (componente financeiro) e6.5 (componente de obrigações). Não encerra essas fases. A obrigação D2101 já era derivada da RN oficial pelo PGCC; agora a orquestração mensal executa o caso sem movimento comprovado.

O D2101 trata de títulos de dívida objeto de oferta pública; não é uma classificação genérica de dívida pública governamental. A Tabela11, página3 do PDF AnexoI1.3.0, identifica os códigos de títulos diretos e de fundo exclusivo cuja carteira atende ao requisito legal. O mapeamento real da conta e os requisitos de enquadramento continuam dependentes das provas independentes do PGCC, sintéticas nos positivos.

As páginas37–38 do PDF de eventos1.3.0 especificam `semTitulos`, detalhes de títulos e apuração. Neste objetivo não há cálculo por título, Selic, PIS/Cofins, saldo anterior individualizado, crédito do adquirente, alteração ou exclusão. Contas com saldo não nulo também são recusadas neste contrato conservador, mesmo que a entrada alegue ausência de movimento. Elas exigem o futuro tratamento individualizado de títulos. A ausência de movimento não é inferida de uma lista vazia ou de `vApur=0`.

## Prova e reconciliação

`DereDebtNoMovementXmlProjector` recebe o PGCC e o balancete já qualificados. Seleciona todas as contas analíticas dos dois códigos oficiais. Cada conta precisa estar no livro completo, ter saldos inicial/final, movimentos de débito/crédito, ajustes e apropriação iguais a `0.00`, e não ter vínculos financeiros. Movimentos compensados que resultam em saldo zero também bloqueiam o caminho.

A entrada `debt_no_movement` declara contribuinte, competência, `semTitulos=1`, lista de títulos vazia e conjunto exato de contas. A prova é qualificada e identificada, vinculada por hash ao contribuinte, período, contas, declaração, hash canônico do livro e hash do PGCC. Sua vigência cobre o mês inteiro; observação e geração não podem antecipar a prova nem superar o conhecimento capturado. Prova ausente, adulterada, futura, vencida ou de outra identidade/competência impede a emissão local.

O livro e a operação financeira não são modificados. O exemplo herdado mantém receita1000, principal excluído10000, captação200, correspondente100 e base700, com IBS nominal0,70/CBS6,30. O D1121 continua detalhando apenas100 do correspondente. As novas contas de títulos têm saldo e movimento zero; não geram cálculo adicional ou crédito financeiro.

Se o PGCC não contém os códigos obrigatórios, o comando não cria D2101 e recusa uma declaração indevida. D1106 permanece pendente. Os bundles anteriores mantêm seu bloqueio histórico de D2101. O comando mensal mantém seu default17; o21 é selecionado explicitamente.

## Fonte XSD observada em06/10/2026

O [portal oficial CGIBS](https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere) passou a disponibilizar o [pacote completo XSD1.3.0](https://www.cgibs.gov.br/upload/arquivos/202610/05144621-06-arquivos-xsd-v-1-3-0.zip). O arquivo foi preservado como `dere-observed-20261006-cgibs-xsd-v1-3-0.zip`,118.673 bytes, SHA256 `3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd`. Há31 XSDs e um arquivo de orientação textual. A observação técnica não define vigência fiscal ou calendário do contribuinte.

O D2101 utiliza `evtDebOpOfPublic-v0_0_3.xsd` sem alteração. O validador ignora entradas textuais de nome simples, sem extrair ou interpretar seu conteúdo, depois de conferir o hash do arquivo oficial. Caminhos e entradas de outros formatos continuam recusados. A dependência XMLDSig preservada também é conferida por hash.

O21 possui contratos XSD por componente: somente D2101 utiliza o novo pacote; D1001/D1011/D1101/D1121/D1199 mantêm o pacote capturado nos recortes anteriores. D1011 e D1121 possuem novas versões no pacote1.3.0 e ainda precisam de migração própria. Logo, os seis corpos aprovados individualmente não comprovam homologação integral mensal1.3.0 ou compatibilidade de recepção de um lote misto.

O catálogo COSIF contém dez referências selecionadas, acrescentando a raiz TVM, saldo contábil bruto de títulos privados de entidades não financeiras e cotas de fundos da página190 do PDF BCB preservado. A página foi renderizada e conferida. O elenco observado não aprova automaticamente a aplicação institucional, classificação fiscal ou histórico temporal da conta.

## Aceitação e reprodução

Suíte completa em PHP8.5.10: **892 testes/2.877 asserções**, incluindo32 testes novos. PHPStan sem erros, Pint360 arquivos aprovado e validação das regras do bundle aprovada. Corpus documental pelo comando: **24/24**, quatro positivos e20 bloqueios. Três positivos obrigatórios produzem seis eventos cada; o positivo sem obrigação produz cinco, totalizando **23 corpos XML**, com somente assinatura ausente.

Snapshot financeiro com replay determinístico; relatórios repetidos com os mesmos hashes de decisão, snapshot, entrada mensal e validações. Corpus geral RTC15/15 e integrado3/3 nos candidatos21/20/19/18/17; corpus anterior D1121 preservado. **2.053 artefatos históricos sem alteração**. Gate21:43/43 fontes íntegras, elegível para validação local, `ready=false`,119 bloqueios.

A auditoria de dependências detectou dois avisos em `league/commonmark`2.10.1. A atualização pontual para2.10.2 corrigiu ambos, conforme os avisos [GHSA-97jj-33gv-5xf9](https://github.com/advisories/GHSA-97jj-33gv-5xf9) e [GHSA-3q6v-r5mr-hxv8](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8). Só esse pacote foi alterado no lock; a auditoria final ficou limpa e a suíte foi repetida. O PHPStan precisou de limite de memória512M na execução, sem alteração de configuração do projeto.

Materializar: `python scripts/fork_phase4b_dere_d2101_no_movement_bundle.py`. Verificar: `python scripts/verify_phase4b_dere_d2101_no_movement_cut.py`. Auditar: `python scripts/audit_phase4b_dere_d2101_no_movement_cut.py`. Recursos e evidências diferentes não são sobrescritos. Evidências em `storage/app/phase4b-d2101-no-movement-20261006-21/`, com baseline, snapshot, relatórios, XMLs, corpus, logs, renders, gate e `verification-report.json`. Logs de tentativas iniciais foram preservados; a aceitação usa os logs finais indicados no relatório.

## Continuidade do planejamento

O próximo objetivo documental pode tratar movimentos D2101, com prova de oferta pública/fundo, identificação do título/devedor, saldo anterior, Selic pelo custo médio, variação mensal, dedução PIS/Cofins, apuração e reconciliação ao livro. Antes de recepção ou homologação integral, migrar D1011/D1121 e conferir o conjunto mensal no pacote completo1.3.0. Também seguem pendentes D1106, demais códigos/atividades D1121, bases negativas, reabertura, alterações/exclusões e verificação real de cadastro/documentos/calendário. O desenvolvimento parou no objetivo sem movimento; não houve assinatura, transmissão, fechamento governamental ou recolhimento.
