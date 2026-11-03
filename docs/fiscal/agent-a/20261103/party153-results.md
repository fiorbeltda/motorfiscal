# A153 — domicílio pessoal e critérios de não cadastrados

Agente A. Entrega validada localmente para os recortes de 4B.2.4, 4B.2.5,
4B.2.6, 4B.2.7, 4B.2.10 e 4B.3.2. Os seis requisitos permanecem parciais;
nenhum grupo ou fase recebeu encerramento a partir destes testes.

## Resultado material

Novo contrato opt-in `RTC-MATERIAL-PARTY-DOMICILE-2026.11.03-A153` em
`party_domicile_qualification`. O trait privado `PartyDomicile153` integra-se
ao despachante próprio A151, reutilizando suas guardas de operação, partes,
data, conhecimento e registros. Não modifica engine/factory ou fatos globais.

Para pessoa física consumidora regularmente cadastrada, o componente confronta
o endereço do cadastro com residência permanente única. Quando não há residência
permanente ou há mais de uma, exige a qualificação expressa do local de relações
econômicas mais relevantes. Não inventa pontuação de renda ou preferência entre
endereços. Conta residências ativas na data, incluindo endereço estrangeiro
identificado, e não usa residência encerrada para provocar uma troca de regra.

A condição da PF no art.105 do regulamento deve estar explicitada no registro.
PF em contexto de atividade econômica ou condição desconhecida continua com
pergunta própria; o código não aplica silenciosamente a regra de habitação ao
estabelecimento de uma atividade. CPF/CNPJ ou cadastro declarado não determinam
essa condição por si. O cadastro e a prova material devem concordar.

Para não cadastrados, o fornecedor deve escolher expressamente ao menos dois
tipos distintos entre os quatro critérios já existentes. Todos os escolhidos
devem ser qualificados e não conflitantes. Mesma UF/município não apaga divergência
de rua, número ou CEP. Componentes são explícitos; o código não corrige grafia,
normaliza números ou faz geocodificação. IP/geolocalização pode ter resolução
municipal declarada, sem rua inventada; detalhes de rua devem vir de outro
registro. Endereço do arranjo de pagamento do adquirente não é prova automática
do domicílio de destinatário distinto.

Um terceiro critério não escolhido pode divergir e permanece identificado como
tal. Registro não escolhido sem qualificação também fica separado da prova
usada. A lei permite escolha de critérios; o componente não exige unanimidade
dos quatro, não escolhe um par automaticamente e não promove registro inválido
a evidência da decisão.

Para usar somente o endereço declarado, exige prova de impossibilidade cobrindo
os quatro tipos, com disponibilidade, referências e justificativas. Enumera os
pares disponíveis e recusa a alternativa se existir par de tipos distintos não
conflitantes. Ausência da prova, omissão de critério disponível, várias declarações
ou IP isolado não acionam essa alternativa. Se os únicos critérios disponíveis
conflitarem, a impossibilidade pode ser qualificada, preservando esses registros.

Todos os métodos mantêm a escolha da parte segundo onerosidade/residência e
exigem registros vinculados à mesma operação, fornecimento, partes, data e
conhecimento. Perfis cadastrais legados ou de centralização não podem contradizer
o perfil novo no mesmo input. Fonte declarada e hash não autenticam cadastro,
residência ou fonte externa; essas limitações permanecem explícitas.

## Ligação e limites de escopo

O serviço pessoal remoto de A149 pode receber relatório A153. A ligação
reproduz a decisão inteira e confere identidades/data/conhecimento. Novo contrato
de saída `RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153`, estado
`LOCAL_SERVICE_PARTY_DOMICILE_QUALIFIED`, com o relatório material original e
o escopo do serviço preservados. Relatório com endereço trocado, mesmo rehashado,
não passa no replay.

`selected_establishment_id=null` para estes perfis: não se fabrica um
estabelecimento a partir do endereço. O resultado não determina incidência,
alíquota, crédito, importação/exportação, autenticidade ou obrigação documental.
`classification_catalog_integration=PENDING_AGENT_B`,
`official_registry_queried=false` e `source_record_authenticity_proven=false`.
Os registros dos ensaios são sintéticos, sem dados pessoais reais consultados.

## Evidência terminal

| Conjunto | Resultado | Abrangência |
|---|---:|---|
| Dirigidos finais | 254 testes / 947 asserções | 69 A153 e 185 A149–A152 |
| Regressão dirigida acumulada | 508 testes / 1.807 asserções | Inclui os 254; não somar |
| Oráculo independente Python | 10 casos | Habitação, critérios, impossibilidade, parte e hashes |
| PHPStan global | Sem erros | Configuração existente, nível 8 |
| Pint | 7 arquivos aprovados | Cinco PHP de produção e fixture/teste próprios |

Casos nativos: uma residência, nenhuma, várias, dois critérios com terceiro
divergente, declaração + geolocalização municipal, declaração por indisponibilidade,
declaração por critérios conflitantes, destinatário de adquirente estrangeiro,
operação não onerosa e PJ não cadastrada. Testes dirigidos incluem também
entidade sem personalidade jurídica, contradições e bordas materiais/temporais.

CLI real exercita os três métodos, a ligação de serviço remoto e a recusa por
falta da prova de impossibilidade, com `ACTION_REQUIRED`, localidade nula e exit1.
Duas execuções da CLI de residência e duas da ligação remota são byte a byte
iguais, com replay. As seis saídas aceitas anteriores de A149–A152 conservaram
seus bytes. O corte intermediário 252/943 está preservado, sem somar versões.

Comandos principais no contêiner exclusivo `motorfiscal-agente-a-20261103`:

```text
php vendor/bin/pest tests/Fiscal/Integration/PartyDomicile153Test.php tests/Fiscal/Integration/CentralizedDomicile152Test.php tests/Fiscal/Integration/EntityDomicile151Test.php tests/Fiscal/Integration/ServiceLocality149Test.php tests/Fiscal/Integration/TerritorialLocality150Test.php --compact --log-junit storage/app/agent-a-A153-directed-v2.junit.xml
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan fiscal:rtc:qualify-entity-domicile storage/app/agent-a-A153/home.json
php artisan fiscal:rtc:qualify-entity-domicile storage/app/agent-a-A153/combined.json
php artisan fiscal:rtc:qualify-entity-domicile storage/app/agent-a-A153/fallback.json
php artisan fiscal:rtc:qualify-service-locality storage/app/agent-a-A153/service.json
```

`python -B scripts/agent_a/verify153.py` confere casas ativas, escolha explícita,
compatibilidade de componentes, pares disponíveis, impossibilidade, papéis,
datas, hashes, CLI e JUnit. Emite `validation-A153.json` sem sobrescrever aceite
anterior. `regression-v1.log` e seu JUnit registram o acumulado, que não equivale
à regressão integral final integrada A+B.

## Fontes e preservação

[LC214 atualizada pela Câmara](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
art.11 X, §3 I a/II/III e §5. Captura HTTP200 em
`2026-11-03T16:47:36.062375+00:00`, 3.095.827 bytes, SHA256
`dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.

[Regulamento CBS, Decreto12955](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html),
art.12 §3 e art.104–107, incluindo o contexto de PF no art.107 I/II.
Captura HTTP200 em `2026-11-03T16:47:36.417371+00:00`, 3.144.950 bytes,
SHA256 `8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235`.
Bytes iguais a A145/A152; fontes e recibos anteriores não foram substituídos.
Não se promove regulamento CBS a fonte autônoma de IBS nem se resolve
unilateralmente o contexto específico da PF em atividade econômica.

Evidências:
`D:/ProjetoSaasExports/agent-a-A153-party-domicile-20261103-v1`.
Incluem fontes, preflight com hashes A152 conferidos, quatro classes antes da
alteração, logs versionados, CLIs, JUnit, oráculos, fechamento e pacote final
de fontes com inventário/hash. Os pacotes A149–A152 permanecem preservados.

## Caminhos e estado dos requisitos

Lista fechada da task, conforme manifesto anterior às edições:

- `app/Modules/Fiscal/ConsumptionReform/PartyDomicile153.php`
- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/party153_fixture.php`
- `tests/Fiscal/Integration/PartyDomicile153Test.php`
- `scripts/agent_a/verify153.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A153-manifest.md`
- `docs/fiscal/agent-a/20261103/party153-results.md`
- `docs/fiscal/agent-a/20261103/validation-A153.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

| ID | Avanço comprovado | Estado |
|---|---|---|
| 4B.2.4 | Local por provas materiais de domicílio | Parcial |
| 4B.2.5 | Destino sem inferência por IP ou primeiro endereço | Parcial |
| 4B.2.6 | PF e entidades não cadastradas nos critérios | Parcial |
| 4B.2.7 | Ligação no serviço pessoal remoto | Parcial |
| 4B.2.10 | Habitação/relevância econômica e vínculos; PF empresarial pendente | Parcial |
| 4B.3.2 | Parte aplicável e vínculos do adquirente/destinatário | Parcial |

Base remota `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, HEAD
`87d22e70787b128a97e5aed4b175a39e619798af`. Alterações locais A144–A153;
sem commit, push, PR, merge, tag ou deploy. Nenhum arquivo de escrita de B,
catálogo, crédito, schema ou consolidação global alterado.

## Handoff e continuação

B recebe por meio do usuário: contrato, parte, método, endereço/local, critérios
escolhidos e não escolhidos, memória de impossibilidade e hashes. O recibo contém
proposta estruturada para matriz/console, mantendo parcialidade. Recebimento e
integração por B não comprovados; A não atualizou o painel global.

Próximo foco: qualificar PF em atividade econômica/estabelecimento e revisar a
aceitação dos 44 itens, distinguindo lacunas de código, prova, integração e
parâmetro externo. Não converter entregas dirigidas em encerramento integral.
Fase4B continua em andamento, com oito grupos parciais e produção não liberada;
fases3/4A preservam aceites históricos e fases5–7 seguem planejadas.

Fechamento em `closure-v1.json`: os 70 arquivos locais A144–A153 passaram na
conferência de escopo; o checkout original está limpo e `git diff --check`
passou. Console aprovado nos 28 controles, endpoint HTTP200 e estados das
fases/oito grupos conferidos. Os hashes do recibo A153 correspondem aos arquivos
atuais. O painel global permanece no corte histórico, sem aplicar as propostas
desta retomada; isso continua como dependência explícita do consolidante B.
