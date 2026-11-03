# A151 — domicílio da entidade pelo estabelecimento atendido

Agente A; requisitos 4B.2.4, 4B.2.5, 4B.2.7, 4B.2.10 e 4B.3.2.
Entrega validada localmente no escopo abaixo. Nenhum desses requisitos recebeu
aceite integral e nenhum grupo foi formalmente encerrado.

## Resultado e limites

Novo contrato `RTC-REGISTERED-ENTITY-DOMICILE-2026.11.03-A151`, componente
`EntityDomicile151` e CLI `fiscal:rtc:qualify-entity-domicile`.
Nas hipóteses residuais qualificadas, a escolha da parte considera onerosidade
e residência. Operação onerosa com adquirente nacional usa seu domicílio;
adquirente no exterior e operação não onerosa usam o destinatário nacional,
nos limites do contrato. Isso não determina incidência, importação ou exportação.

Para pessoa jurídica ou entidade sem personalidade jurídica regularmente
cadastrada, o resultado exige vínculo entre o estabelecimento efetivamente
atendido e o registro correspondente do cadastro único. No ensaio, a sede em SP
não substitui a filial atendida no RJ. A sede só é escolhida quando o próprio
registro de atendimento a identifica. Ordem do cadastro não decide o destino.

Os três registros — escopo, cadastro e estabelecimento atendido — possuem hash,
origem declarada, identidade da operação/fornecimento/partes, data aplicável e
observação compatível com o conhecimento. A mesma parte não pode ser residente
e não residente no País por ocupar papéis diferentes. Cadastro de outra parte,
endereço apenas de cobrança, cobertura incompleta, estabelecimento ausente,
duplicado ou fora das datas informadas exigem qualificação adicional. O contrato
usa intervalos cadastrais explícitos com datas inclusivas, sem extrapolação.

Pessoa física, cadastro irregular, contratação centralizada e vários
estabelecimentos atendidos permanecem perguntas explícitas. O contrato recebe
registros fornecidos; não realiza consulta oficial nem comprova autenticidade
externa ou validade jurídica histórica por si só. Não escolhe códigos, alíquotas,
créditos ou obrigação documental.

## Integração entre componentes próprios de A

No ramo pessoal remoto de A149, a opção `entity_domicile_qualification` recebe
um relatório A151. O qualificador reproduz a decisão inteira e exige igualdade
de operação, fornecimento, fornecedor, destinatário, data e conhecimento.
Relatório adulterado, mesmo com hash recalculado, de outra operação ou categoria
é recusado. Não basta confiar em um campo `status` recebido.

A saída vinculada tem contrato
`RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151` e estado
`LOCAL_SERVICE_ENTITY_DOMICILE_QUALIFIED`. Preserva o relatório cadastral
original e o registro de escopo do serviço. Sem a opção nova, as perguntas
e saídas anteriores permanecem iguais. A ligação não altera o runner central,
factories ou contratos globais de B.

## Validação terminal

| Conjunto | Resultado | Abrangência |
|---|---:|---|
| Dirigidos finais | 120 testes / 483 asserções | 65 A151, mais 55 A149/A150 |
| Regressão dirigida acumulada | 374 testes / 1.343 asserções | Inclui os 120; não somar os conjuntos |
| Oráculo independente Python | 5 perfis conferidos | Parte, filial, datas e hashes canônicos |
| PHPStan global | Sem erros | Configuração existente, nível 8 |
| Pint | 6 arquivos aprovados | PHP exclusivo deste incremento |

Os cinco perfis nativos cobrem adquirente nacional, adquirente no exterior,
não onerosa, entidade sem personalidade jurídica e serviço pessoal remoto.
Duas execuções da CLI cadastral são idênticas e reproduzíveis; o cadastro sem
o estabelecimento indicado retorna `ACTION_REQUIRED` e exit 1. Duas execuções
da ligação de serviço também são idênticas e reproduzíveis. As CLI aceitas A149
e A150 conservaram seus bytes. Recibos anteriores permanecem preservados.

A primeira execução dirigida teve uma expectativa incorreta para o identificador
do erro geográfico já definido no contrato 115. O teste foi corrigido para o
identificador real; a recusa material funcionava. Esse log não foi apagado.
O corte intermediário 58/212 e a regressão 367/1.318 também permanecem arquivados;
os números finais acima incluem a ligação adicional, sem somar versões.

Comandos principais no contêiner exclusivo `motorfiscal-agente-a-20261103`:

```text
php vendor/bin/pest tests/Fiscal/Integration/EntityDomicile151Test.php tests/Fiscal/Integration/ServiceLocality149Test.php tests/Fiscal/Integration/TerritorialLocality150Test.php --compact --log-junit storage/app/agent-a-A151-directed-v3.junit.xml
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan fiscal:rtc:qualify-entity-domicile storage/app/agent-a-A151/domestic.json
php artisan fiscal:rtc:qualify-service-locality storage/app/agent-a-A151/service.json
```

No host, `python -B scripts/agent_a/verify151.py` confere os artefatos e cria
`validation-A151.json` com guarda contra sobrescrita. O recibo referencia
JUnit, fontes, hashes das implementações e proposta estruturada para B.
O log `regression-v2.log` e o JUnit correspondente contêm o corte acumulado;
isso não equivale à regressão integral da versão final integrada A+B.

## Fonte e rastreabilidade

[LC214 atualizada pela Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
art. 11, X, §3, I, b e §5. Nova captura HTTP 200 em
`2026-11-03T12:22:14.9193747+00:00`, 3.095.827 bytes,
SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.
Bytes iguais à fonte preservada em A145; nenhuma captura anterior substituída.
Disponibilidade de conhecimento usa a observação anterior já preservada,
não uma data de publicação inventada. Cadastro e operações dos ensaios são
sintéticos e estão identificados como tais.

Evidências:
`D:/ProjetoSaasExports/agent-a-A151-entity-domicile-20261103-v1`.
Incluem fonte, preflight, classes A149 antes da ligação, logs de todas as
tentativas, CLIs, JUnit, oráculos e pacote final de fontes com inventário/hash.

## Caminhos escritos e estado de Git

Lista fechada desta task, conforme manifesto anterior às edições:

- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/domicile151_fixture.php`
- `tests/Fiscal/Integration/EntityDomicile151Test.php`
- `scripts/agent_a/verify151.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A151-manifest.md`
- `docs/fiscal/agent-a/20261103/domicile151-results.md`
- `docs/fiscal/agent-a/20261103/validation-A151.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Base remota `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, HEAD
`87d22e70787b128a97e5aed4b175a39e619798af`. A144–A150 locais preservados;
checkout original sem alterações. Sem commit, push, PR, merge, tag ou deploy.

## Status, handoff e próximo trabalho

Os cinco IDs permanecem parciais, com aceites limitados ao comportamento acima.
O índice próprio dos 44 requisitos inclui recibo e testes de A151. Nenhum arquivo
de B, catálogo, schema, crédito ou consolidação global foi editado.

Interface para B: `destination_basis`, `selected_party_id`,
`selected_establishment_id`, `locality`, registros e hashes. Consumir o resultado
antes de classificar/projetar o documento, preservando o mesmo fornecimento.
`classification_catalog_integration=PENDING_AGENT_B`. Handoff disponibilizado
ao usuário como ponte; recebimento e integração por B não comprovados.

A proposta de matriz/console está no recibo e no handoff; aplicação global
permanece pendente. Fases 3 e 4A conservam os aceites históricos em Sandbox;
4B continua em andamento, sem grupo formalmente encerrado; fases 5–7 planejadas
e produção não liberada. A conferência atual do console passou nos 28 controles;
endpoint HTTP 200 confirmou esses estados e os oito grupos como parciais.
Recibo `closure-v1.json` na pasta externa. O conteúdo global continua no corte
histórico; A não aplicou nele as propostas desta retomada.

Próximo incremento independente de A: exceção material de contratação
centralizada, com prova de estabelecimento matriz e das condições pertinentes,
recebendo qualificação de restrição de crédito como dependência, sem implementar
direitos de crédito ou classificações de B.
