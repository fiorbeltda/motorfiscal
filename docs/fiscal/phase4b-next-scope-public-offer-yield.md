# Próximo objetivo: rendimento de oferta pública integrado à base e ao D2101

Sequência autorizada pelo usuário: concluir e validar cada recorte e avançar. Estado de partida: candidatos22,23 e24 aceitos localmente; fase4B parcial. O25 deve integrar os rendimentos de uma posição estática qualificada do art.195, usando o cálculo24, com memória reproduzível e uma única contribuição à base mensal. Sem ativação de produção.

A pesquisa e o desenho devem resolver o vínculo entre base financeira, conta patrimonial110113001/110113002, conta de resultado e D2101. O D2101 totaliza uma base própria; acrescentar a mesma receita tributável ao D1101 e ao totalizador auxiliar causaria dupla contagem. O qualificador atual do livro exige que `computed_amount` do lançamento corresponda ao `vApur` da conta vinculada. Esse contrato precisa distinguir o valor contábil, a base atribuída ao auxiliar e o valor apurado no balancete, preservando reproduções anteriores.

Aceitação esperada: oferta pública CVM e emissor comprovados, com devedor no regime regular e fora do regime financeiro específico conforme o caput do art.195; fundo exclusivo95% quando aplicável; cálculo24 vinculado ao lançamento e à mesma instituição/ISIN/conta/período; valor bruto, excedente Selic e PIS/Cofins separados; base positiva e piso zero; saldos iniciais/finais conferidos ao D1101; ausência de duplicação de lançamento ou tributo entre eventos. O recorte deve continuar bloqueando estoque/custo variável, ganho de alienação não coberto e hipóteses sem prova.

Produzir novo bundle/controles e corpus positivo/negativo, snapshots/replays, XML D2101 e conjunto mensal coerente no XSD1.3.0, testes relevantes, auditoria e documentação. O motor financeiro pode manter seu motivo de decisão final pendente enquanto outras obrigações/documentos estiverem incompletos; não afirmar encerramento da4B por concluir este recorte. Manter os defaults históricos e todos os hashes de evidência anteriores.

Fontes a conferir: LC214 arts.192/195 consolidada, MOD1.3.0 D2101 e D1101, tabela11/códigos auxiliares, totalizadorD9121 e regras do fechamentoD1199. As capturas do24 não comprovam posição ou oferta real.

Pesquisa inicial25: texto consolidado oficial da Câmara, capturado01/10 e preservado por hash, confirma art.195 caput/§§1–2. A página ao vivo da Câmara retornou429 e a tentativa Planalto expirou; nenhum arquivo histórico foi substituído. Extrato de trabalho local em `storage/app/phase4b-d2101-research-20261006/art195-camara-preserved-extract.txt`. O requisito do regime do emissor deve ser expresso no próximo contrato; os recortes23/24 não equivalem a qualificação integral do art.195.

## Aceitação25 e sequência

O objetivo25 foi concluído localmente:998 testes/3.315 asserções,39 casos/72 XML/12 replays, regressões25/24/23 e2.593 históricos preservados. Relatório em `storage/app/phase4b-yield-20261006-25/verification-report.json`; contrato em `phase4b-dere-d2101-yield-integration.md`. O25 usa RN DeRE HALF_EVEN8/2 com fonte preservada, mantendo24 inalterado.

Próximo objetivo: construir memória extracontábil diária para custo médio com movimentação, incluindo datas, quantidades, PU, custo de entrada/saída e Selic, antes da integração tributária mensal. Pesquisar a convenção temporal e o tratamento de alienações; regras locais devem ser explícitas e não declaradas homologadas quando a documentação oficial não as define. Posições e curvas comprovadas, reconciliação completa, recusas de ambiguidade e regressão25 são condições de aceitação. Não encerrar a4B por concluir esse componente.
