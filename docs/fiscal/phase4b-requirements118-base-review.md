# Base118 — revisão dos14 requisitos originais4B.4

Auditoria delimitada concluída, sem modificar runtime ou ativar regras.34
cenários geraram68 chamadas:66 decisões concluídas e duas exceções de domínio
capturadas no mesmo caso.417 conferências independentes, incluindo180
comparaçõesDecimal, passaram. São observações nativas, não testes permanentes.

## Falhas de contrato de entrada reproduzidas

- `amount=true` em acréscimo, desconto ou reembolso é convertido para1.00;
  bases observadas1001.00,999.00 e999.00, comVALID/VALID_WITH_WARNINGS.
- `amount=false` em acréscimo lançaInvalidArgumentException nos dois executores,
  em vez de produzir recusa fiscal controlada.
- Número binário1.1 é admitido como valor monetário aninhado. Isso demonstra
  ausência de guarda de tipo exato, não uma prova isolada de erro numérico1.1.
- Seis cenários com evidência vazia/em branco ou identificadores vazios de
  terceiro/documento ainda produzem base ajustada e decisão válida.
- Duas linhas de desconto literalmente idênticas com a mesma evidência reduzem
  a base a800.00. Falta desambiguar a identidade da linha/evento; o diagnóstico
  não presume que toda repetição de uma referência documental é ilegal.

Os controles de ajuste negativo/desconhecido, desconto dependente de evento,
documento divergente, reembolso não comprovado e dupla exclusão de tributo
legado continuam recusados. Não remover essas guardas para tratar os novos casos.
Inteiro100 foi observado separadamente: conversão decimal exata de inteiro não
deve ser confundida com coerção de booleano ou aceitação de número binário.

## Revisão requisito por requisito

|ID|Evidência e limite atual|
|---|---|
|4B.4.1 Base|Aritmética decimal existe; contratos de entradas aninhadas incompletos.|
|4B.4.2 Exclusões|Tributos legados possuem guardas; demais exclusões/proveniência parciais.|
|4B.4.3 Descontos|Documento/evento são verificados; evidência vazia e coerção ainda passam.|
|4B.4.4 Acréscimos|Soma decimal observada; tipos/evidência precisam de guarda.|
|4B.4.5 Reembolsos|Nome de terceiro é afirmado, mas seus identificadores podem estar vazios.|
|4B.4.6 Combinadas|Múltiplos ajustes não demonstram múltiplos fornecimentos/operações.|
|4B.4.7 Padrão|Parâmetros2026 segregados; demais períodos dependem de fonte efetiva.|
|4B.4.8 Reduzidas|Contador qualificado localmente:30% de redução de alíquotas, não de base.|
|4B.4.9 Redução de base|Não comprovada genericamente pelo caso de redução de alíquotas.|
|4B.4.10 Específicos|RTC financeiro parou em documento; integrado antes, por identificador.|
|4B.4.11 Arredondamento|Centavos, meios-centavos e valor acima de2^53 conferidos; tipo ainda falha.|
|4B.4.12 IBS segregado|Estado/município e soma separados; jurisdições/períodos restantes pendentes.|
|4B.4.13 CBS|Federal separada; demais taxas/regimes/efeitos ainda parciais.|
|4B.4.14 Memória|Trilha existe, mas referência vazia e identidade duplicada não qualificam prova.|

180 comparaçõesDecimal verificam números das decisões, não a suficiência das
provas. O caso de5.00 confirmou IBS estadual0.01 eCBS0.05 por arredondamento
separado. A redução profissional manteve base1000.00 com alíquotas0.07/0/0.63.
Esses controles não encerram as famílias restantes nem aprovam produção.

## Fontes, tentativas e próximos passos

Reutilizada a captura117 da
[LC214 compilada](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm),
art.12, sem atribuir nova data de observação. O artigo trata valor da operação,
componentes e exclusões, condições documentais e conversão. A captura atual não
comprova vigência histórica ou aplicabilidade de cada benefício ao contribuinte.

Evidências em `D:/ProjetoSaasExports/phase4b-requirements118-base-audit-20261020-v1`:

- `native-probe-v2/execution.json`, `observations.json`, `summary.json`.
- `requirements-review-v1/requirements118-base-review.json`:80 itens preservados,
  14 revistos neste corte, testemunhos, oráculos e limites por requisito.
- v1 preserva saída nativa e falha do caminho de proveniência no wrapper.
  v2 registrou corretamente exit0 do processoPHP e todas as evidências; apenas
  o formatador de impressão falhou ao tratar decisão recusada como objeto.
  O leitor seguro examinou os arquivos prontos, sem repetir o motor por esse erro.

Agora44 IDs foram examinados nos cortes114/115/117/118;36 ainda não revistos.
Nenhum requisito/grupo foi formalmente encerrado.88 hashes116 intactos.

O corpus116 terminal será preservado como evidência intermediária. A regressão
integral116 preparada não foi iniciada. Diante das falhas117/118, o próximo
aceite integral deverá incluir correção versionada dos contratos de entrada,
novos testes/negativos e os históricos, sem reescrever bundles anteriores.
112 continua o último aceite integral. Produção permanece bloqueada.
