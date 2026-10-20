# Retorno D9121 e composição financeira de títulos com oferta pública — 54

Objetivo: ler e reconciliar o XML D9121 com o D2101 mensal efetivamente projetado, para permitir que sua receita integre uma composição financeira mensal com gross-down e BCN, sem duplicar o valor no D1101. O avanço deve alcançar a composição e o comando mensal, além do leitor isolado.

A composição51 atualmente bloqueia `D2101_required=true` com `D9121_CURRENT_RETURN_BINDING_REQUIRED`. A evidência primária em `storage/app/phase4b-dere9121-54-preparation-20261013` contém páginas físicas dos manuais e o XSD extraído do pacote oficial preservado. A Tabela12, página física63 do AnexoI, acrescenta `vApurTot` do D9121 ao codBC1010. O XSD `evtRetornoTitPub-v0_0_2.xsd` devolve um total agregado em `infoTotTitOfPub/vApurTot`; não devolve uma lista de títulos. A conferência por título precisa partir do D2101 correspondente.

Critérios de conclusão:

1. Conferir fontes, XSD e temporalidade; manter a versão e os arquivos anteriores. Não reinterpretar a oferta pública de dívida como qualquer título público ou dívida privada.
2. Implementar leitor do XML bruto com validação do XSD, namespace, atributo obrigatório `id` correspondente ao D2101 de origem, raiz do contribuinte, competência, sucesso, recibo, sequência, hash do D2101 e recibo PGCC contextualizado. Recusar totalizador ausente, erro, período/fornecedor/tipo/hash incompatíveis e XML não qualificado.
3. Reproduzir o D2101 original e somar exatamente os valores vApur dos títulos cobertos. Vincular os bytes projetados e o retorno sem afirmar autenticidade governamental a partir de hashes, recibos sintéticos ou XSD.
4. Integrar o total reconciliado à composição do codBC1010. Conservar receitas fiscais roteadas ao D2101 fora da parcela D1101 e provar que o valor foi adicionado uma única vez. Exigir classificação explícita dos demais valores e negar misturas ou rateios sem prova.
5. Capturar cenário, solicitação, retorno sintético explícito, composição, proposta tributária e relatório mensal próprios; reproduzir os resultados e preservar a memória financeira, os XMLs anteriores e suas evidências históricas. Usar política/bundle sucessor quando necessário para a nova seleção temporal.
6. Cobrir cenários com e sem movimento, totais alterados, retorno antigo/excluído/inativo e inconsistências de documentos. Rodar testes dirigidos, análise estática, formatação, regressões históricas relevantes e suíte completa antes da aceitação local.

O leitor pode reconciliar apenas retornos sintéticos explicitamente identificados enquanto a autenticação não estiver implementada. Produção, assinatura, transmissão, autenticação, contabilização, calendário de expiração, dependências, venda isolada sem ponte documental e aceitação integral da Fase4B permanecem pendentes.

A publicação oficial da documentação1.3.0 foi reconfirmada na [Receita Federal](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/outubro/receita-federal-publica-versao-1-3-0-da-documentacao-tecnica-da-dere/); a notícia não substitui os campos e fórmulas dos manuais e XSDs vinculados pelos hashes do contrato de pesquisa.

Preparação concluída em13/10: um D9121 sintético foi produzido a partir de um D2101 preservado do candidato27, com total independente calculado pela soma dos títulos. A primeira tentativa sem atributo `id` falhou e permanece preservada; a versão sucessora usa o identificador exato do evento de origem e passou no XSD como candidato local sem assinatura. A validação foi reproduzida exatamente. Hash em base64 e recibos são explicitamente sintéticos; esse resultado ainda não implementa o leitor nem integra a composição mensal.
