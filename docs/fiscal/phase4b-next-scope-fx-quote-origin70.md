# Qualificação da origem da cotação — 70

Objetivo: qualificar a origem material da taxa antes de admitir a aplicação jurídica de conversões não USD ou cruzadas, conservando as capturas numéricas e suas limitações.

O art.13, §4º, III, dos regulamentos exige taxa de venda calculada pela autoridade monetária do país de origem e divulgada pelo BCB. A [descrição primária do conjunto de dados do BCB](https://dadosabertos.bcb.gov.br/en/dataset/taxas-de-cambio-todos-os-boletins-diarios) informa fonte Refinitiv, exceto USD/BRL, e explica as relações de paridade dos tipos A/B. A presença de EUR/AUD na API comprova divulgação e permite reproduzir numericamente suas cotações, mas não prova automaticamente a origem material exigida nesse inciso.

Os caminhos numéricos EUR/AUD capturados68 permanecem evidência de cálculo local e comparação; não devem ser apresentados como prova completa de qualificação jurídica. O controle de produção, apropriação e autenticação continuam bloqueados. Nenhuma captura histórica é reescrita para esconder essa limitação descoberta depois.

Critérios seguintes:

1. Preservar a descrição BCB e conferir a cadeia de origem de cada moeda não USD. Buscar documentação oficial da autoridade monetária de origem e a relação com a divulgação BCB, sem substituir por fonte comercial ou inferência.
2. Acrescentar qualificação explícita da origem à política de seleção e recusar caminho tributário não USD sem essa prova, mantendo saída de pesquisa numérica separada e os bundles históricos com suas limitações documentadas.
3. Para o incisoIV, exigir prova suficiente de ausência de cotação no país e taxa documentada para converter a moeda original em USD. Ausência no recurso API de dez moedas não prova a condição legal. Depois, selecionar a PTAX de USD nos termos dos incisosI/II.
4. Qualificar datas relevantes, unidades, lado de venda, conhecimento, hashes, arredondamento e vínculos transacionais dos dois passos. Capturar positivos sintéticos explicitamente delimitados e recusas, integrar trace e preservar crédito/documento sem autorização implícita.
5. Promover a nova seleção por bundle próprio, testar e auditar a geração aplicável. A geração69 em execução segue restrita à prova funcional de conversão e das datas; sua aprovação não equivale a qualificação jurídica das origens não USD.

Também permanece aberta a prova da obrigação documental específica de importação. O art.74/CBS prevê ato conjunto e não autoriza inferir implantação apenas do XSD. A pesquisa deve identificar ato, vigência, ambiente e emissão exigível antes de remover o `ACTION_REQUIRED` documental. O escopo68 amplo e a Fase4B continuam abertos.
