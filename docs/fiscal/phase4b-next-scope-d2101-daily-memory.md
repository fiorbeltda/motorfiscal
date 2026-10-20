# Próximo objetivo — memória diária D2101 com movimentação

Ponto de partida: candidato25 aceito localmente, sem encerrar a Fase4B. A posição estática já qualifica emissor/oferta, base financeira, origem fiscal única e XML mensal. O próximo recorte deve retirar a dependência de uma posição constante durante todo o mês, preservando25 e os candidatos anteriores.

Objetivo verificável: reconstruir e conferir a memória extracontábil de um título/conta/período a partir de posição inicial comprovada, compras com quantidade e PU, saídas com custo e curvas contratuais, mantendo série diária Selic vinculada por hash. Produzir memória reproduzível, saldos e movimentos mensais reconciliados, provas e recusas. A ativação no cálculo tributário e XML mensal exige método temporal e valor do teto plenamente qualificados; um método local não equivale a homologação.

A pesquisa do MOD1.3.0 preservado, páginas físicas86–88, confirma: chave ISIN/conta; saldo inicial igual ao saldo anterior salvo primeira aquisição; compras/subscrições em entradas; vendas/amortizações em saídas; cupons recebidos separados da variação apropriada; fórmula de saldo final; teto Selic com custo médio ponderado; memória de datas, PU, curvas contratuais e aplicação diária conservada por cinco anos. A Selic SGS11 é percentual por dia útil, conforme metadados oficiais novamente consultados em06/10/2026. O PDF preservado tem SHA-256 `b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d`.

A documentação consultada não define detalhadamente a ordem intradiária de compra/saída e aplicação da Selic, a alocação do acumulado em alienação parcial ou todas as hipóteses de curva contábil versus custo de aquisição. Esses pontos exigem convenção explícita e prova independente do método; não devem ser deduzidos de um saldo final. O mecanismo deve recusar método ausente/contraditório em vez de assumir a aplicação da taxa à posição errada.

Condições de aceitação:

- Posição inicial e anterior, identidade, período e método de custo comprovados; custo de aquisição separado do valor contábil e rendimentos.
- Ordenação determinística de operações por data e sequência, PU e quantidade positivos, identificação sem duplicação e saídas sem estoque negativo.
- Custo médio ponderado reconstruído com precisão intermediária8/HALF_EVEN; diferenças de arredondamento ou resíduos integralmente explicados.
- Cobertura dos dias da série oficial, curvas contratuais e posição diária, sem lacunas ou observação posterior ao conhecimento capturado.
- Entradas, saídas, cupons, variação e saldos reconciliados com o livro; método do teto e seu valor vinculados à mesma memória.
- Casos de primeira compra, compra adicional, saída parcial/total, amortização, mudança de PU, cupom, feriado e inconsistências; oráculo independente para aritmética, snapshots/replays e regressão25.
- Bundle e controles próprios, documentação dos limites e hashes históricos preservados; produção permanece bloqueada até aceitação integral.

Fontes: [MOD e leiautes — CGIBS](https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere) e [taxa diária SGS11 — BCB](https://dadosabertos.bcb.gov.br/pt_BR/dataset/11-taxa-de-juros---selic). A reconsulta online da página/PDF CGIBS falhou neste turno; a pesquisa do texto utilizou a captura oficial já verificada no bundle25, sem substituí-la ou apresentar nova versão como confirmada.

## Aceitação26 e continuidade

O componente diário foi concluído e validado localmente:1.044 testes/7.937 asserções,44 casos/16 replays/seis guardas, regressões26/25 e2.849 históricos preservados. Relatório em `storage/app/phase4b-daily-memory-20261006-26/verification-report.json`; contrato em `phase4b-dere-d2101-daily-memory.md`. Nesta etapa a página e o PDF oficial CGIBS voltaram a responder, confirmando a versão1.3.0, sem substituir as capturas congeladas.

A integração de posições variáveis à base e ao XML mensal permanece pendente. Próximo objetivo: `phase4b-next-scope-d2101-variable-integration.md`. As convenções locais não equivalem a homologação; ganho de alienação permanece sem tratamento tributário integrado. O objetivo maior da4B continua ativo.
