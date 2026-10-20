# D2101 — memória diária de custo e movimentações

Candidato `2026.10.06-26`, aceito localmente em06/10/2026. Reconstrói custo médio de aquisição, estoque, curvas contábeis e benchmark Selic com convenções locais explícitas. A Fase4B continua parcial. O candidato25 mantém a integração tributária e mensal de posição constante; este componente26 ainda não ativa posições variáveis na base financeira nem no XML mensal.

O comando `fiscal:dere:calculate-debt-daily-memory` exige posição inicial, operações ordenadas, uma curva por dia do calendário e declarações mensais. Conferem-se identidade/período/conta, prova do mês anterior ou primeira aquisição, prova integral da memória e prova independente do método. Toda prova tem validade, observação e hash próprios. A série oficial SGS11 de setembro2026, com21 taxas em percentual por dia útil, é preservada e conferida contra a resposta bruta do BCB. Os demais dias do calendário não recebem uma taxa inventada.

Compras adicionam quantidade e custo determinado por PU. Vendas retiram custo médio proporcional; uma saída total aloca todo o saldo, evitando resíduo. Amortizações reduzem o custo e, quando totais, encerram a quantidade. Cupons reduzem o saldo contábil e ficam separados de aquisição/principal. Custo de aquisição, saldo contábil e rendimento apropriado são campos distintos. Cada operação conserva custo e benchmark retirados, PU, quantidade posterior e ganho realizado da venda. O ganho de venda é registrado, com tratamento tributário explicitamente pendente.

O benchmark local capitaliza diariamente a posição remanescente. A saída retira a parcela proporcional dessa curva; o rendimento já acumulado no mês permanece na memória. As convenções aceitas aplicam as operações antes ou depois da taxa diária e conservam cupons somente na curva contábil. Esses métodos são declarações locais comprovadas pelo contrato, não regras intradiárias homologadas pela Administração Tributária. O componente recusa convenção ausente, contraditória ou uma alegação de homologação. O MOD1.3.0 exige custo médio e memória analítica, mas não explicita todas essas convenções; a fonte oficial foi reconsultada neste turno.

Exemplos do oráculo independente de100 dígitos:

| Operação | Teto Selic local | Base do título após PIS/Cofins10 | Saldo contábil final |
|---|---:|---:|---:|
| Custo10.000 mantido todo o mês, variação150 | 108,17 | 98,17 | 10.150,00 |
| Primeira compra10.000 em14/09, antes da taxa | 66,49 | 56,49 | 10.150,00 |
| Mesma compra, depois da taxa | 61,29 | 51,29 | 10.150,00 |
| Compra adicional500 unidades por PU12 em14/09 | 148,06 | 138,06 | 16.150,00 |
| Venda parcial400 unidades, valor contábil4.000 | 81,46 | 71,46 | 6.150,00 |
| Amortização2.000 em14/09 | 94,81 | 84,81 | 8.150,00 |

A compra adicional produz custo médio unitário10,66666667 para1.500 unidades. A venda parcial por4.200 evidencia ganho realizado200, separado da variação150. Na saída total, o saldo final fica zero e o teto já acumulado41,40 não desaparece; não há nova apropriação contratual após encerrar o estoque. Variações negativas resultam em base zero sem compensação de outros títulos. Todos os intermediários usam oito casas/HALF_EVEN e valores mensais finais usam duas, preservando os contratos anteriores.

Validação:1.044 testes/7.937 asserções, PHPStan sem erros, Pint372 arquivos. Corpus44 casos, sendo16 positivos e28 recusas;16 snapshots/replays, seis guardas do comando e30 linhas diárias para cada positivo. Candidato26:15 RTC e três gerais integradas. Candidato25 reproduzido em24 casos mensais/15 recusas do motor/72 corpos XML/12 replays. Os2.849 artefatos históricos preservaram seus hashes. Gate47 fontes verificadas, `ready=false`,119 bloqueios.

Evidência: `storage/app/phase4b-daily-memory-20261006-26/verification-report.json`. Scripts: `fork_phase4b_dere_daily_memory_bundle.py`, `verify_phase4b_dere_daily_memory_cut.py`, `verify_phase4b_dere_daily_memory_regressions.py` e `audit_phase4b_dere_daily_memory_cut.py`. Os44 rascunhos iniciais do oráculo com representação científica de zero foram arquivados antes da correção para decimal canônico. Os recursos aceitos, snapshots e resultados não são sobrescritos pelos verificadores.

Limites: provas e curvas sintéticas; apenas período com série oficial capturada; convenções locais sem homologação; ganho de alienação sem regra tributária integrada; posições variáveis sem contribuição à base financeira ou D2101 mensal. Não houve assinatura, transmissão, lançamento ou encerramento de período. O campo de retenção registra a exigência de cinco anos do MOD; não comprova implantação de infraestrutura de arquivo por esse prazo. O objetivo seguinte é integrar a memória ao vínculo único D2101/livro, cobrindo os movimentos e recusando o que continuar sem enquadramento.

Fontes: [MOD1.3.0 — CGIBS](https://www.cgibs.gov.br/upload/arquivos/202610/03141320-01-manual-usuario-dere-declaracao-de-regimes-especificos-v-1-3-0.pdf), páginas físicas86–88, e [SGS11 — BCB](https://dadosabertos.bcb.gov.br/pt_BR/dataset/11-taxa-de-juros---selic). PDFs, arredondamento e taxas estão vinculados por hash no bundle26. A reconsulta confirmou os links/versão; não substituiu as capturas usadas na reprodução.
