# Exportação parcial com câmbio — preparação 73

A preparação 73 reproduziu a lacuna do motor atual e verificou protótipos separados de conversão, prova da transação e integração antes da alocação. As fontes primárias preservadas incluem o regulamento da CBS e as páginas físicas 15, 16 e 46 do regulamento do IBS: art. 13, §4, para conversão e art. 93 para a parcela executada ou consumida no exterior.

Evidência congelada: `storage/app/phase4b-partial-export-fx73-preparation-20261014/partial-export-fx-preparation-validation.json`.

- 23 casos numéricos com contexto explícito de exportação, separados do conversor de importação.
- 21 casos de transação: uma vinculação local válida e 20 recusas por origem, partes, alocação, documentos, valores, hash, datas ou ausência de prova.
- Sete casos do protótipo de motor: conversão/alocação, recusas antes do cálculo, comportamento anterior sem política e compatibilidade exata em BRL.
- Análise estática, estilo e replays finais registrados; valores conferidos independentemente com Decimal em Python.

No caso USD de 1000.00 a 5.1795, o bruto convertido é 5179.50; a parcela estrangeira de 40% é 2071.80; a parcela brasileira é 3107.70. O cálculo nominal de 2026 produz IBS estadual 3.11, municipal 0.00 e CBS 27.97. O resultado continua `ACTION_REQUIRED` pela obrigação documental não comprovada. Esses valores não autorizam lançamento, crédito, emissão autenticada ou produção.

Os protótipos não foram instalados no motor. A geração 71 segue sob suíte completa. O instalador 73 exige aceitação terminal da geração anterior e confere os hashes da preparação. O construtor de bundle preparado criará cinco facts/perguntas próprios e manterá seleção material qualificada somente para USD. A publicação do bundle, o corpus nativo com o motor instalado, os testes permanentes e a regressão integral ainda precisam ser executados após essa migração.

Esta aceitação é da preparação técnica, não do escopo 73 inteiro nem da Fase 4B. Produção permanece bloqueada. O plano original continua preservado no inventário 72, sem transformar os corpora DeRE ou estes protótipos em evidência de conclusão das outras rotas.

## Atualização de desenvolvimento instalado - 14/10/2026

Os parágrafos anteriores registram o momento histórico de preparação. A
geração 73 foi posteriormente instalada no bundle
`CONSUMPTION-PHASE4B-2026.10.14-73-draft1`, preservando essa evidência.
A retomada confirmou os dez hashes finais do checkpoint, o corte dirigido
de 50 testes/213 assertions e a suíte terminal de **2.266 testes/45.742
assertions**, exit 0. O Pint final passou em 504 arquivos. Doze decisões nativas
de exportação e 18 replays históricos foram conferidos pelo novo auditor.

Aceitação de desenvolvimento:
`storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integration-development-validation.json`.
Ela aceita a conversão e a alocação, mantendo a obrigação documental da
exportação parcial e a Fase 4B abertas. Produção permanece bloqueada.
Pesquisa documental e restrições observadas:
`docs/fiscal/phase4b-export73-resumption-and-schema74.md`.
