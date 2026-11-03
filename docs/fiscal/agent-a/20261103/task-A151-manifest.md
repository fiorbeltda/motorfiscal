# A151 — domicílio de entidade cadastrada e estabelecimento atendido

Agente A. Base verificada: `https://github.com/fiorbeltda/motorfiscal.git`,
branch `codex/fiscal-engine-bootstrap`, HEAD
`87d22e70787b128a97e5aed4b175a39e619798af`. Alterações locais A144–A150
preservadas e arquivadas; checkout original sem alterações. Nenhuma publicação.

Requisitos: 4B.2.4, 4B.2.5, 4B.2.7, 4B.2.10; vínculo com a identidade do
adquirente em 4B.3.2. Estado existente: o runner já distingue domicílio do
adquirente/destinatário, mas não foi identificado um contrato que confronte o
estabelecimento efetivamente atendido com o cadastro único. A149 exige esse
complemento para prestação pessoal parcial ou totalmente remota.

Aceite deste incremento: nas hipóteses residuais do art. 11, X e §5, selecionar
a parte aplicável segundo onerosidade/residência; para PJ ou entidade sem
personalidade jurídica regularmente cadastrada, vincular um único estabelecimento
atendido ao registro cadastral correspondente, com local, identidade e vigência.
Não usar sede, endereço de faturamento ou primeiro estabelecimento por padrão.
Recusar cadastro divergente, incompleto, futuro, desatualizado, de outra parte
ou sem prova da destinação efetiva. Contratação centralizada, pessoa física,
não cadastrado e atendimento a vários estabelecimentos continuam dependências
explícitas, sem escolha presumida de local.

## Caminhos exatos de escrita exclusiva A

- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `scripts/agent_a/domicile151_fixture.php`
- `tests/Fiscal/Integration/EntityDomicile151Test.php`
- `scripts/agent_a/verify151.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A151-manifest.md`
- `docs/fiscal/agent-a/20261103/domicile151-results.md`
- `docs/fiscal/agent-a/20261103/validation-A151.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Contratos somente leitura: `ServiceLocality149`, `ObservedGeographicReference115`,
`CanonicalJson`, `LocalDate`, catálogo 141 e sua política geográfica, engine
central e testes históricos. Nenhum arquivo de escrita compartilhada.
Contrato novo: `RTC-REGISTERED-ENTITY-DOMICILE-2026.11.03-A151`; CLI própria.
Não modificar categorias, classificações, créditos, DPS, matriz ou console global.

Fonte primária: LC214, art. 11, X, §3, I, b e §5, versão atualizada da Câmara,
reconsultada nesta sessão. Capturar bytes e metadados, conferir hash contra a
captura A145 sem substituir a anterior. A regra não determina incidência ou
benefício; fonte cadastral fornecida no ensaio não significa consulta real à RFB.

Validação: PJ/entidade sem personalidade, onerosa nacional, adquirente exterior,
não onerosa, serviço parcialmente remoto; sede distinta da filial; contradições,
identidades textuais, datas/vigências, hash/replay adulterado; CLI repetida e
recusa; oráculo independente da escolha e dos hashes; Pint, PHPStan e regressão
dirigida acumulada. Não apresentar o conjunto como regressão integral final.

Evidências exclusivas em
`D:/ProjetoSaasExports/agent-a-A151-entity-domicile-20261103-v1`:
`LC214.html`, `source.json`, `preflight.json`, `native-cases-v1.json`,
`cli-first.json`, `cli-second.json`, `cli-invalid.json`, `cli-exits.json`,
logs e recibos de saída `directed-v1`, `regression-v1`, `pint-v1`, `phpstan-v1`,
JUnit próprios, pacote de fontes e inventário com hashes. Novas tentativas
recebem versão nova; recibos aceitos nunca são sobrescritos.

A primeira execução dirigida preservada terminou com 52 testes aprovados e uma
expectativa incorreta do identificador de erro geográfico. Corrigir a expectativa
para o identificador real do contrato 115. Acrescentar recusa de residência
contraditória para a mesma parte e observação anterior ao dia da operação,
alinhada ao contrato 149. Próximos artefatos: `directed-v2.log`,
`directed-v2-exit.json`, `agent-a-A151-directed-v2.junit.xml`,
`pint-v2.log`, `pint-v2-exit.json`, `phpstan-v2.log`, `phpstan-v2-exit.json`.

Handoff ao usuário para B: local material e estabelecimento selecionado,
provenientes do mesmo fornecimento; integração de códigos/documento pendente.
Risco de colisão não observado nos novos caminhos exclusivos A.

## Ligação opcional entre os contratos próprios A149 e A151

Acrescentar, antes de editar, os caminhos exclusivos A:

- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`

No ramo pessoal remoto de A149, aceitar opcionalmente um relatório A151
requalificável e estritamente vinculado à mesma operação, fornecimento, partes,
data e conhecimento. Entrada sem essa opção conserva a pergunta e a saída
anteriores. Contrato da ligação: `RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151`.
Nenhuma factory, enum ou contrato global é alterado. Validar incompatibilidade
de identidades, categoria e replay; preservar as CLI aceitas A149 e A150.

Preservar `ServiceLocality149-before.php` e
`QualifyServiceLocality149Command-before.php` no diretório externo desta task.
Acrescentar `cli-service-first.json`, `cli-service-second.json`,
`cli-A149-after.json`, `cli-A150-after.json`. Novas validações completas usam
`directed-v3.log`, `directed-v3-exit.json`, `agent-a-A151-directed-v3.junit.xml`,
`regression-v2.log`, `regression-v2-exit.json`,
`agent-a-A151-regression-v2.junit.xml`, `pint-v3.log`, `pint-v3-exit.json`,
`phpstan-v3.log`, `phpstan-v3-exit.json`. Preservar o primeiro corte executado.
Após a ligação, gerar `native-cases-v2.json`, sem sobrescrever o corte v1.
Registrar as saídas dos processos adicionais em `cli-exits-v2.json`.
Fechamento: `closure-v1.json` registra verificações atuais de escopo, Git,
console e estados do endpoint; `source-package-final/manifest.json` inventaria
as cópias dos caminhos A modificados/não rastreados, com verificação SHA256.
