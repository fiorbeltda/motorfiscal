# A152 — local da aquisição centralizada pelo estabelecimento matriz

Agente A. Incremento validado localmente para 4B.2.4, 4B.2.5, 4B.2.7,
4B.2.10, 4B.3.2 e 4B.3.9. Os requisitos permanecem parciais; a entrega
comprova os comportamentos abaixo, sem encerramento formal de grupo.

## Comportamento entregue

O contrato opt-in `RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152`
estende o componente próprio `EntityDomicile151`. Qualifica a escolha da matriz
para aquisição centralizada de comunicação fixa, locação de bem móvel material,
serviço residual e serviço pessoal remoto, com escopo material explícito.

Exige adquirente nacional identificado, aquisição onerosa centralizada,
regime regular de IBS e CBS na data, mais de um estabelecimento ativo e uma
matriz ativa única vinculada ao documento de centralização. A cobertura da
aquisição deve identificar estabelecimentos da mesma entidade e constar do
cadastro completo fornecido. Datas cadastrais são explícitas e inclusivas;
estabelecimento fechado não conta para a pluralidade e não substitui a matriz.

No ensaio principal, a filial/terminal do RJ não muda o destino da aquisição
centralizada: a matriz registrada em SP é selecionada. Um segundo ensaio pela
CLI troca expressamente o papel de matriz para a unidade do RJ e o resultado
acompanha essa prova. Não há município fixo, seleção por ordem ou transferência
automática para a unidade faturadora.

Cada registro tem identidade da operação, fornecimento, partes, data aplicável,
observação compatível com o conhecimento, origem declarada e hash. Matriz
ausente/duplicada, proprietário diferente, regime vencido, cobertura incompleta,
estabelecimento desconhecido e divergência geográfica retornam perguntas.
O relatório inteiro é reproduzido; recalcular o hash de uma escolha adulterada
não a torna válida.

## Limite com créditos e integração de B

O predicado de vedação é recebido em `credit_restriction_evidence`, envelope de
consumo de A com referência opaca à qualificação externa, vínculo à aquisição e
declarações explícitas para IBS e CBS. Falta, incompletude, outro sujeito,
valor sem tipo booleano, observação futura ou vedação de qualquer tributo
impedem a qualificação da exceção. Não há dedução a partir de saldo de crédito,
estado ALLOWED/CONDITIONAL, ausência de extinção ou crédito de valor zero.

A não implementa a qualificação do direito de crédito, não chama o motor de
crédito para inferir esse predicado e não modifica arquivos de B. Os ensaios
usam registros sintéticos identificados, sem alegar entrega ou autenticação de
B. A integração da qualificação real permanece pendente. Também não houve
consulta oficial ao cadastro nem prova autônoma de validade jurídica histórica.

`credit_right_determined=false`,
`credit_restriction_assessment_authenticated=false` e
`classification_catalog_integration=PENDING_AGENT_B` permanecem explícitos.
O resultado trata localidade, não incidência, alíquota, crédito ou documento.

## Ligação com os serviços de A149

Comunicação fixa centralizada pode receber `centralized_purchase_qualification`;
serviço pessoal remoto usa a opção própria `entity_domicile_qualification`.
O componente reproduz o relatório A152 e confere operação, fornecimento,
fornecedor, destinatário, data e conhecimento. O escopo do serviço deve concordar
com a centralização. A contradição é recusada nos dois sentidos, inclusive
quando um relatório A151 não centralizado é anexado a escopo centralizado.

Saída ligada: `RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152`, estado
`LOCAL_SERVICE_CENTRALIZED_DOMICILE_QUALIFIED`. O relatório cadastral original e
o escopo do serviço permanecem rastreáveis. Entradas antigas sem a opção nova
conservam suas perguntas e saídas. Nenhuma factory, enum ou contrato global
foi alterado. A integração com classificação e projeção continua como handoff.

## Validação terminal

| Conjunto | Resultado | Limite |
|---|---:|---|
| Dirigidos finais | 185 testes / 722 asserções | 65 novos A152 e 120 A149–A151 |
| Regressão dirigida acumulada | 439 testes / 1.582 asserções | Inclui os 185; não somar |
| Oráculos independentes Python | 5 casos | 4 famílias e matriz movida para o RJ |
| PHPStan global | Sem erros | Configuração existente, nível 8 |
| Pint | 6 PHP aprovados | Quatro componentes/comandos e fixture/teste A152 |

Duas execuções da CLI cadastral e duas da ligação de comunicação fixa são
idênticas, com replay completo. Serviço pessoal remoto também foi qualificado
pela CLI. Falta da qualificação externa de vedação retorna `ACTION_REQUIRED`,
localidade nula e exit 1, sem exceção inesperada. As quatro saídas aceitas
anteriores — A149, A150, A151 cadastral e A151 serviço — permanecem byte a byte
iguais. O primeiro corte 183/718 permanece arquivado, sem somar versões.

Comandos principais no contêiner exclusivo `motorfiscal-agente-a-20261103`:

```text
php vendor/bin/pest tests/Fiscal/Integration/CentralizedDomicile152Test.php tests/Fiscal/Integration/EntityDomicile151Test.php tests/Fiscal/Integration/ServiceLocality149Test.php tests/Fiscal/Integration/TerritorialLocality150Test.php --compact --log-junit storage/app/agent-a-A152-directed-v2.junit.xml
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan fiscal:rtc:qualify-entity-domicile storage/app/agent-a-A152/centralized.json
php artisan fiscal:rtc:qualify-service-locality storage/app/agent-a-A152/fixed-service.json
```

`python -B scripts/agent_a/verify152.py` confere escolhas, datas, predicados,
hashes canônicos, CLIs, fontes, JUnit e compatibilidade, preservando o recibo
`validation-A152.json`. O log `regression-v1.log` e o JUnit correspondente
registram o corte acumulado; não equivalem à regressão integral final A+B.

## Fontes primárias e evidências

[LC214 atualizada pela Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
art.11 §4 I/II. Captura HTTP 200 em `2026-11-03T12:34:52.664411+00:00`,
3.095.827 bytes, SHA256
`dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.

[Decreto12955, regulamento CBS](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html),
art.12 §4. Captura HTTP 200 em `2026-11-03T12:34:52.990838+00:00`,
3.144.950 bytes, SHA256
`8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235`.
Ambas coincidem com as fontes preservadas em A145; capturas anteriores intactas.
O regulamento CBS não é promovido a fonte autônoma de IBS. Não há alíquota futura
inventada ou alteração de norma, fonte, catálogo ou schema oficial.

Pasta externa:
`D:/ProjetoSaasExports/agent-a-A152-centralized-domicile-20261103-v1`.
Contém fonte, preflight e cópias das quatro classes antes da alteração, logs,
CLIs, entrada do ensaio de mudança de matriz, JUnit, oráculos, fechamento e
pacote final de fontes com inventário/hash. Os arquivos aceitos de A151 foram
conferidos antes da mudança; seu pacote histórico permanece disponível.

## Caminhos escritos e handoff

Lista fechada, declarada antes das edições:

- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/centralized152_fixture.php`
- `tests/Fiscal/Integration/CentralizedDomicile152Test.php`
- `scripts/agent_a/verify152.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A152-manifest.md`
- `docs/fiscal/agent-a/20261103/centralized152-results.md`
- `docs/fiscal/agent-a/20261103/validation-A152.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Repositório remoto `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, base
`87d22e70787b128a97e5aed4b175a39e619798af`. Somente alterações locais A144–A152;
nenhum commit, push, PR, merge, tag ou deploy. Checkout original preservado.

Interface para B: contrato/versionamento, parte e matriz selecionadas, local,
lista de estabelecimentos ativos e abrangidos, memória dos predicados externos,
origens e hashes. O recibo contém proposta estruturada de matriz/console para
os seis IDs, mantendo-os parciais. Handoff disponibilizado ao usuário como
ponte; recebimento, qualificação real e integração por B não comprovados.
A não editou matriz, status ou console globais.

Próximo incremento independente de A: domicílio da pessoa física cadastrada e
critérios de localização de não cadastrados. Manter a escolha de endereço
condicionada aos fatos exigidos, sem inferir cadastro ou residência pelo IP.
Fase4B continua em andamento, com os oito grupos parciais e produção não
liberada; fases3/4A preservam aceites históricos e fases5–7 seguem planejadas.

Fechamento observado em `closure-v1.json`: escopo dos 63 arquivos locais
conferido como exclusivo de A144–A152, checkout original limpo e `git diff
--check` aprovado. Console passou 28 controles; endpoint HTTP200 confirmou
fases e os oito grupos parciais. O conteúdo global permanece no corte histórico;
as propostas de A ainda não foram aplicadas por B.
