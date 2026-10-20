# Integração mensal50 — aceitação local

A declaração BCN foi integrada ao comando nativo `fiscal:dere:project-monthly-closing` mediante pedido explícito vinculado ao snapshot, à entrada mensal e à projeção original reconstruída. O relatório `storage/app/phase4b-negative-base50-closing-draft-20261009/command-development-validation.json` registra a aceitação local dessa integração.

Evidências verificadas:12 testes dirigidos/56 asserções, suíte completa1.938/44.358, PHPStan geral sem erros, Pint463 arquivos, regressões dos candidatos27/26/25,51 fontes oficiais e hashes dos relatórios/implementações anteriores48/49 e protótipo50. Os replays financeiro e mensal reproduziram a captura; cinco XMLs foram conferidos pelo XSD oficial. Os quatro XMLs auxiliares conservam os bytes de48 e o D1199 altera apenas as opções BCN.

O auditor inicialmente comparou o identificador completo do bundle com sua versão curta. A falha foi capturada em `command-audit-initial.json/log` e a fonte anterior preservada em `before-bundle-identity-auditor.py`. A comparação corrigida usa o campo oficial `bundle_id`; `command-audit-final.json/log` registra a aprovação. Nenhum snapshot, relatório de execução ou fonte histórica foi corrigido para contornar essa falha.

A suíte desta entrega foi iniciada antes da introdução do cálculo aritmético51. Seus totais comprovaram o conjunto descoberto nessa execução, incluindo a integração50; não são apresentados como regressão integral das novas classes51. Os resultados dirigidos51 e seus limites ficam no respectivo relatório separado.

A aceitação cobre a declaração de opção no fluxo mensal local. Não comprova consumo aplicado ao tributo, autenticidade do retorno, calendário/fronteira, reprocessamento governamental de dependências, encerramento ou produção. O candidato integral aceito permanece27; os escopos28/49/51 e a Fase4B continuam abertos. Produção permanece bloqueada.
