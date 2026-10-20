# Bases negativas51 — bases correntes e aplicação à apuração

Objetivo: calcular as bases correntes financeiras segregadas por natureza e tributo, conectá-las ao estoque anterior ativo e ao planejador BCN, e demonstrar a apuração local resultante com snapshots, replay e conservação. O escopo sucede a integração da declaração ao comando mensal50; não encerra o escopo28 ou a Fase4B.

A Tabela12 do AnexoI1.3.0, página física63, define1005 para margem com ISS e1010 para margem sem ISS. Exige composição de receitas, despesas, captação proporcional e reversões;1010 também inclui o total qualificado de rendimentos de títulos de dívida com oferta pública via D9121. A formação passa pelo rateio geral/transição e pela retirada dos tributos embutidos. IBS recebe base líquida geral e de transição; CBS recebe a base líquida geral. O MOD, página física150, determina a ordem: base após gross-down, dedução BCN anterior, base efetiva e cálculo dos tributos. A qualificação documental existente continua vedando a equiparação automática a títulos governamentais.

Pesquisa reproduzível: `scripts/prepare_phase4b_negative_base51_current_bases.py`. Fontes e páginas extraídas, SHA256 e diagnóstico do snapshot50 ficam em `storage/app/phase4b-negative-base51-current-bases-preparation-20261009/current-bases-research-contract.json`. A publicação do pacote é confirmada pela [Receita Federal](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/outubro/receita-federal-publica-versao-1-3-0-da-documentacao-tecnica-da-dere/); as fórmulas são fundamentadas nos PDFs oficiais preservados, não apenas na notícia.

O exemplo financeiro50 calcula base qualificada900,00 e débitos brutos antes da apuração mensal. Esse número ainda não comprova bases DeRE após gross-down por raiz. Copiá-lo para ambas as raízes omitiria a formação exigida, especialmente quando houver receita de transição. Os dados faltantes devem ser qualificados; ausência de receita de transição precisa de evidência, não de um padrão implícito.

Critérios de conclusão:

1. Classificar o codBC formador e ISS mediante PGCC capturado e tabelas oficiais; manter IBS e CBS separados.
2. Reconciliar receitas, despesas, captação proporcional e reversões ao livro; conservar a origem fiscal dos títulos e impedir duplicação do rendimento já contabilizado com o D9121.
3. Qualificar proporções geral/transição, taxas conhecidas na competência, gross-down e precisão intermediária/final. Cobrir base positiva, negativa e zero, e diferenças entre IBS/CBS.
4. Alimentar o planejador com bases correntes comprovadas e o retorno anterior ativo. Separar nova geração, dedução anterior, base efetiva e saldo remanescente.
5. Calcular os tributos locais sobre a base efetiva, preservando a memória original dos débitos brutos. Não alterar ISS/PIS/Cofins por efeito indireto da dedução BCN.
6. Integrar ao fluxo nativo mediante conhecimento sucessor próprio; produzir positivos e negativos semânticos, snapshot/replay, auditoria de fontes e regressões históricas. Autenticidade, calendário e reprocessamento de competências dependentes continuam requisitos explícitos.

A preparação de fontes está implementada; formação de bases e recálculo tributário permanecem pendentes. Produção permanece bloqueada.

A conferência independente `scripts/verify_phase4b_negative_base51_gross_down_oracle.py` reproduziu o exemplo do MOD, página física47: base IBS125000,00, base CBS112500,00, IBS10000,00 e CBS2250,00; o IS do exemplo não qualifica incidência no regime financeiro. O relatório `gross-down-independent-oracle.json` separa esse exemplo de uma sensibilidade condicional sobre900,00, sem aplicá-la ao snapshot. As fórmulas e variáveis da Tabela12.2 foram preservadas nas páginas físicas95/96 do AnexoI, com registro de origem e hashes.

Avanço local: a [etapa aritmética51](phase4b-negative-base-current-base51-development.md) foi implementada e validada em18 testes/44 asserções, três capturas/replays nativos e comparação independente Decimal. A composição qualificada e a integração tributária continuam abertas. As linhas exatas da Tabela11 para receitas/despesas foram extraídas das páginas físicas23/34; o critério de arredondamento foi preservado da página física10 do AnexoII. Esses registros ainda não ativam uma nova política fiscal.

A [composição local51](phase4b-negative-base-current-composition51-development.md) agora reconstrói as provas mensais, deriva receitas/deduções/captação do livro e exige classificação explícita de ISS. Foram conferidos25 testes/60 asserções, dois exemplos de raízes1005/1010, replay financeiro original e dez estruturas XML/XSD. A classificação ampliada, a política temporal sucessora e o consumo tributário permanecem pendentes.
