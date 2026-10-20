# Prompt de contexto e continuidade — Motor Fiscal SaaS

Assuma o contexto do projeto abaixo. O retrato foi conferido localmente em
16/10/2026, com última aceitação de desenvolvimento em 15/10/2026. Antes de
propor ou executar trabalho novo, confira os documentos e o estado real do
checkout: o texto é um ponto de partida, não substitui evidências posteriores.
Não recomece escopos aceitos nem trate preparações históricas como pendências
atuais se já houver integração sucessora comprovada.

## 1. Objetivo do produto

Construir um motor fiscal brasileiro determinístico, temporal e auditável que
receba as informações conhecidas pelo usuário, derive fatos e enquadramentos
fundamentados, selecione os tratamentos aplicáveis e entregue decisão fiscal,
memória de cálculo, justificativas, perguntas pendentes e projeções de obrigações.

O usuário não deve precisar conhecer previamente a regra tributária aplicável.
O motor deve aproveitar os fatos disponíveis, classificá-los e derivar o que
puder por regras explícitas. Diferenciar informação declarada, derivada e obtida
de catálogo, mantendo a proveniência. Perguntar apenas por fatos materiais que
não possam ser derivados com fundamento. Não inventar dados, alíquotas, provas,
vigência ou enquadramentos para obter uma resposta positiva.

Os três pilares são ISS, retenções federais e IBS/CBS. São análises tributárias
especializadas, não apenas filtros de sanitização. A orquestração resolve fatos,
seleciona os pilares pertinentes e consolida as decisões respeitando suas
dependências. Não pressupor que todos sejam independentes ou paralelizáveis.

## 2. Ambiente e localização

- Checkout real: `C:/xampp/htdocs/projeto-saas-motor`.
- A pasta do chat `C:/Users/gabriel.oliveira/Documents/ChatGPT/Projeto - Saas`
  não contém o checkout fiscal utilizado.
- Branch conferida: `codex/fiscal-engine-bootstrap`.
- PHP requerido: 8.5; Laravel 13; PostgreSQL 18; Nginx; Docker Compose.
- Docker: `D:/Docker/DockerDesktop/resources/bin/docker.exe`.
- PHP do XAMPP/Windows 8.2 não é o runtime válido para Artisan e dependências.
- Pesquisas originais: `K:/GERAL/TI/Projeto - SAAS/Motor Fiscal/Pesquisas Definitivas`.
- Evidências locais: `storage/app/`; pesquisas, staging e exports também em
  `D:/ProjetoSaasExports/`. Esses caminhos externos podem não existir em outro host.
- Usar PowerShell e executar os comandos do projeto no checkout real.

## 3. Arquitetura atual

É um monólito modular. Laravel fornece bootstrap, CLI, infraestrutura de banco
e rotas do console. A avaliação fiscal é organizada em componentes de domínio.
Não há arquitetura de microsserviços nem conceito de tenant no núcleo atual.

Módulos em `app/Modules/Fiscal/`:

1. `Core`: fatos tipados e imutáveis, resolução de fatos, condições
   TRUE/FALSE/UNKNOWN/ERROR, compilação/avaliação de regras, fórmulas, plano de
   dependências e cursor, precedência, temporalidade, perguntas, trace,
   invariantes, estados de decisão e serialização determinística.
2. `Classification`: serviços canônicos e vínculos com códigos fiscais;
   classificação e correspondências confirmadas, ambíguas ou não suportadas.
3. `ISS`: incidência, local competente, parâmetros municipais,
   responsabilidade/retenção, alíquota, tratamentos, cálculo e transição.
4. `FederalWithholding`: IRRF, PIS, Cofins, CSLL, INSS, bases próprias,
   perfis privados/públicos/PF/exterior/cooperativas/construção e obrigações.
5. `ConsumptionReform`: IBS/CBS, temporalidade/transição, incidência/destino,
   sujeitos, base, reduções, créditos, recolhimento, financeiro, câmbio,
   antecipações, reconciliações e qualificação documental em escopos delimitados.
6. `NFSe`: projeções DPS e DeRE, identidades, campos e corpos XML locais.
7. `Infrastructure`: arquivos/schemas, importação, persistência, publicação,
   parâmetros municipais, cenários, corpus, replay/diff e prontidão de produção.

Classes de coordenação importantes:

- `Core/Engine/DefaultFiscalDecisionEngine.php`: fluxo fiscal integrado.
- `ConsumptionReform/ConsumptionReformEngine.php`: coordenação RTC.
- `Infrastructure/Scenario/FiscalEngineFactory.php`: captura conhecimento,
  confere documentos/versões/hashes e constrói os motores.
- `Infrastructure/Scenario/ScenarioRunService.php`: execução integrada.
- `Infrastructure/Scenario/RtcScenarioRunService.php`: execução/replay RTC isolado.
- `Infrastructure/Production/Phase4bReadinessAssessor.php`: controles de prontidão.

O conhecimento combina JSON versionado e lógica especializada em PHP. Os bundles
fixam fatos, perguntas, regras, catálogos, invariantes e schemas por versões e
hashes. Regras declarativas são compiladas em objetos PHP; qualificações e
cálculos complexos usam classes específicas. Nem toda mudança fiscal é feita
somente por JSON.

Recursos: `resources/fiscal/{bundles,catalogs,facts,questions,rules,schemas,
scenarios,invariants,production}` e demais subdiretórios de fontes/resultados.

Precisão: `brick/math`, `DecimalValue` e tipos monetários; evitar float em
aritmética monetária. JSON Schema: `opis/json-schema`.

Temporalidade distingue vigência fiscal de disponibilidade/conhecimento.
Não usar apenas ano ou uma única data global para todos os tributos. O motor
deve respeitar as datas relevantes de prestação, emissão, pagamento e demais
eventos, dentro da política efetivamente qualificada.

Persistência contempla fontes e versões, importações, catálogos e entradas,
regras/versionamentos/relações/bundles, cenários, execuções, fatos,
decisões, perguntas e traces. Fontes externas são materializadas em snapshots
locais versionados. Não depender de consulta governamental ao vivo na decisão.

Fluxo conceitual: cenário JSON + bundle explícito → validações → fatos e
classificação → avaliações tributárias aplicáveis → projeção → invariantes →
resultado/snapshot. O caminho municipal pode capturar respostas do banco e
reavaliar sobre esse conhecimento capturado para permitir replay independente.

Resultados: `VALID`, `VALID_WITH_WARNINGS`, `ACTION_REQUIRED`, `INVALID`.
Há motivos, fatos ausentes, perguntas, trace e hash determinístico. Falta de
informação não deve ser convertida em decisão fiscal final artificial.

Pontos arquiteturais para acompanhar: orquestradores grandes, muitos contratos
em arrays, manutenção coerente dos dois runners e seleção explícita do bundle.
Alguns comandos têm defaults históricos; conferir a versão em cada execução.
Não dividir em serviços sem necessidade medida e sem preservar determinismo.

## 4. Fundação anterior à Fase 3

Implementados: kernel declarativo, fatos/resolvers, fórmulas, dependências,
precedência, temporalidade, invariantes, planejamento de perguntas, trace,
hashes, snapshots, replay/diff, persistência, importadores e bundles.
Há importação municipal em streaming e catálogos LC116/cTribNac.
Não inventar uma divisão histórica detalhada das fases 1/2: o roadmap atual
as apresenta agregadas como fundação, sem termo individual de fechamento.

## 5. Fase 3 — ISS encerrada funcionalmente no Sandbox

Bundle de fechamento: `ISS-PHASE3@2026.09.17-1`.
Fonte: `docs/fiscal/phase3-closure.md`.

Cobertura declarada:

- 338 códigos cTribNac, sendo 337 tributáveis confirmados; 990101 explicitamente
  não suportado como ISS tributável.
- Localização por estabelecimento, prestação, evento, obra, tomador e intermediário.
- Importação de serviços, regra marítima e responsabilidade nacional/municipal.
- Tratamentos de MEI/SIMEI, Simples com valor fixo e alíquota de retenção;
  seleção temporal e hipótese de benefício ilegal abaixo da carga mínima.
- Base, deduções documentadas de construção, alíquota e cálculo.
- Projeção parcial DPS, parâmetros municipais versionados, trace/snapshot/replay.
- Subitem 15.01 tratado com cautelas próprias, sem generalizar participantes.

Limite: encerramento funcional não é cobertura jurídica irrestrita ou produção.
Parâmetros municipais sintéticos geram aviso; regras municipais e atos aplicáveis
precisam de fonte/versão/prova. Ausência de regra não significa não retenção.

## 6. Fase 4A — retenções federais encerradas funcionalmente no Sandbox

Bundle de fechamento: `FEDERAL-PHASE4A-2026.09.24-4`.
Fonte: `docs/fiscal/phase4a-status.md`, seção inicial de fechamento.

- IRRF privado PJ, categorias e códigos, dispensas, pagamento/crédito,
  prestador PF e tabela progressiva de 2026, remessas ao exterior.
- PCC segregado por tributo, limites e exceções documentadas; transição
  para tratamento CSLL-only no perfil coberto em 2027.
- INSS: cessão/empreitada, base da nota, materiais/equipamentos,
  deduções, CPRB/transição, dispensas e construção previdenciária.
- Administração Pública: nove linhas do Anexo I, pagamentos parciais,
  glosas/acréscimos, atividades mistas, natureza do ente, convênios e IR local.
- Cooperativas de trabalho/médicas: papéis, componentes, beneficiários,
  documentos e segregação entre serviço, terceiros, materiais e repasses.
- Projeções locais R-2010/R-2020/R-4010/R-4020/R-4080, S-1210 e DCTFWeb;
  períodos, responsáveis e calendários cobertos, sem transmissão.

Limites: aprovação legal/temporal e calendários/atos locais permanecem gates
de produção; não estender tabelas/vencimentos para períodos não cobertos.
O fechamento da 4A não encerra os orquestradores transversais da Fase 5.

## 7. Fase 4B — IBS/CBS em desenvolvimento, com oito grupos parciais

Referência atual: `resources/monitoring/objective-review.json` e
`docs/fiscal/phase4b-status.md`. Os textos abaixo resumem cobertura delimitada,
não aprovação universal de cada hipótese.

### 4B.1 Temporalidade

Implementado: seleção temporal, bloqueios de versões indisponíveis,
transição e componentes tributários segregados. Integrações 86/88/91
qualificam datas relevantes, parcelas continuadas, origem das antecipações,
vínculo local de registros capturados e reconciliação nominal do definitivo.
Não recalcular antecipações históricas pela alíquota do fornecimento final.

Pendente: qualificação material completa por operação, consumo efetivo/atômico
dos registros, liquidação documental, demais espécies/contextos, cancelamento,
pagamento a maior e transição 2032/2033 integral. Cálculo local não comprova
débito legal, extinção, lançamento ou crédito apropriável.

### 4B.2 Incidência e local

Implementado: rotas locais de incidência/destino/papéis, doméstico,
importação e exportação parcial; câmbio de importação e conversão anterior
à alocação brasileira/estrangeira da exportação parcial73.

Pendente: documentação completa de importação/exportação parcial, demais
locais especiais, operações não onerosas restantes e qualificação material
das outras moedas/conversões. Não classificar exportação só pelo comprador
estrangeiro. Origem de cotação deve ser qualificada; número isolado não basta.

### 4B.3 Sujeitos e responsabilidade

Implementado: fornecedor/adquirente/destinatário separados, qualificação de
compras públicas, responsabilidade/recolhimento e contextos de plataforma.
Simples78: eleição regular local H1/2027 integrada com fontes próprias.

Pendente: Simples integral, MEI/contextos restantes, documentação, parâmetros
futuros, redutor/destinação pública e operacionalização de recolhimento/split.
Eleição local não equivale a autenticação da opção junto ao governo.

### 4B.4 Base e cálculo

Implementado: memória decimal, IBS estadual/municipal e CBS independentes;
ajustes/reduções em escopos específicos, profissional, educação/saúde,
glosa médica e qualificações de imunidade/cooperativas. Financeiro inclui
componentes de base/débito/crédito, custo constante/médio, memória diária,
realizações e transportes históricos com reconciliação e arredondamento.

Pendente: benefícios/regimes restantes, operações combinadas e bases
específicas, liquidação/documentação financeira e aceitação integral dos
perfis. Ensaios sintéticos não homologam método ou carteira real.

### 4B.5 Créditos

Implementado: tratamento de aquisição condicionado, componentes IBS/CBS,
anulação/manutenção, apuração sobre livro previamente qualificado, escopos de
crédito financeiro e propostas de transferência cooperativa.

Pendente: créditos presumidos/específicos restantes e integração contábil.
Proposta ou memória local não é transferência, extinção ou apropriação efetiva.

### 4B.6 Catálogos oficiais

Implementado: artefatos versionados e vinculados por hash; distinguir
`OFFICIAL_CORRELATION` de `ENGINE_DECISION_RULE`; regras interpretativas
candidatas e respectivas fontes preservadas.

Pendente: mapeamentos restantes, revisão formal e auditoria individual de
cobertura. Presença de catálogo não comprova todas as decisões possíveis.

### 4B.7 NFS-e/DPS RTC e DeRE

Implementado: projeção doméstica local e candidatos DeRE em recortes
delimitados, vínculo a snapshots/livros, hashes, replays e XSDs. Há trabalhos
de cadastro/PGCC, balancete, fechamento, D2101 e eventos financeiros, com
migrações e cadeias históricas descritas nos documentos específicos.
Schema restrito74 e comprador estrangeiro76 são validações estruturais locais.

Pendente: documento definitivo de importação; dois documentos vinculados na
exportação parcial; seleção temporal de leiaute, mapper, identidades, tabelas,
regras de negócio e bases ISS/RTC independentes. Demais perfis e dependência
D1021 ainda aparecem na revisão atual. Não declarar todo DeRE implementado.

Pesquisa92 reobservou portais, PDFs e ZIPs em15/10. Os quatro binários
comparados coincidem com74; os fechamentos XSD examinados não possuem seis
campos novos da NT009. A conclusão vale para os artefatos examinados, não para
todo o universo oficial. Não ativou schema nem comprovou implantação remota.
A tentativa de imagem do cronograma retornou HTML de login e não foi validada.

Exportação parcial ainda para em `RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`.
Não copiar conversão RTC para ISS, repartir seu imposto automaticamente,
alterar modos de snapshots antigos ou inserir domínio novo no XSD antigo.

### 4B.8 Aceitação

Implementado: bundles, fatos/perguntas, decisões, traces, invariantes,
corpora, snapshots/replays e regressões nos escopos aceitos.
Revisão93 comprovou quatro entregas pontuais:4B.8.2/3/8/9 — bundle próprio,
344 fatos/339 perguntas sem duplicidade, trace e decisão positiva com replay.

Pendente: auditoria individual integral dos80 requisitos originais, corpus
positivo suficiente para todas as famílias/períodos e aceitação da fase.
Quatro entregas verificadas não são quatro grupos encerrados; os outros76
requisitos não foram auditados pela revisão93.

## 8. Último corte aceito: integração91

Bundle: `CONSUMPTION-PHASE4B-2026.10.15-91-draft1`.
Manifesto: `resources/fiscal/bundles/consumption-phase4b-2026.10.15-91-draft1.json`.
Aceitação em15/10/2026 às15:01 de Brasília:
`storage/app/phase4b-advance91-development-20261015/advance91-development-validation.json`.
SHA-256: `0f530497e45b469ac0d7b2fa33b3965e645c3eb4e26bb1651d2f3e2f8e49db11`.

- Suíte completa terminal:2.735 testes/48.789 verificações, exit0.
- Dirigidos:295 testes/2.182 verificações.
- PHPStan geral e Pint542 aprovados, inclusive após a suíte.
-30 cenários nos dois runners,60 decisões nativas/60 replays e53 históricos.
-59 fontes conferidas;119 impedimentos de produção; `production_ready=false`.
-5.353 entradas congeladas intactas,71 arquivos runtime e11 cópias de evidência.
- Em16/10 os71 hashes runtime foram reconferidos e coincidiram. A suíte não
  foi reexecutada para elaborar este prompt; os números são do registro aceito.

89/90 foram componentes externos, depois integrados em91. Não apresentá-los
como ainda exclusivamente em staging. A integração usa registros anteriores
capturados, evidencia o fornecimento final e calcula diferenças separadas por
tributo, sem recalcular a antecipação pela taxa final. Snapshot RTC confere
também integridade do corpo salvo antes do replay.

Limites explícitos do relatório91: `registration_proven=false`,
`prior_advance_debits_proven=false`, `actual_registration_consumed=false`,
`document_settlement_complete=false`, `credit_appropriation_authorized=false`,
`phase4b_complete=false`, `production_ready=false`.

As gerações78/86/88 são predecessoras aceitas preservadas. Não comparar seus
hashes diretamente ao código sucessor e concluir adulteração por mudança
deliberada; não reescrever evidências antigas para fazê-las coincidir.

## 9. Fases restantes do plano

Fase5 — orquestradores verticais, reutilizando regras básicas:

1. Construção civil: obra/serviço, CNO, materiais/equipamentos, empreitadas,
   subempreitada, solidariedade e integração dos tributos.
2. Administração Pública completa: ISS/INSS/RTC, compras/serviços,
   natureza/esfera, convênios, construção, pagamentos, documentos/obrigações.
3. Cooperativas: atos, papéis, perfis médicos, cooperados, terceiros,
   reembolsos, tributos e reporting.
4. Serviços financeiros: classificações e tratamentos próprios por operação.
5. B2C/tomador PF: qualificação e integração do perfil entre os tributos.
6. Prestador PF: contribuinte individual, ISS/cadastro, autonomia/vínculo,
   deduções/limites, documentos e eSocial/R-4010.
7. Exportação: resultado/consumo exterior, partes, divisas quando relevantes,
   câmbio/evidências e integração de ISS/legados/RTC.
8. Importação: ISS, IRRF, CIDE quando cabível, contribuições de importação,
   RTC, bases/câmbio/gross-up/tratados/responsáveis.

Componentes desses temas já existem na3/4A/4B. Isso não encerra a Fase5;
reaproveitar e integrar, sem duplicar regras nem recomeçar do zero.

Fase6 — governança transversal:

- Datas relevantes por decisão/tributo e seleção temporal geral.
- Precedência: geral/específica, exceções, regimes, decisões judiciais,
  modulação, vinculação administrativa e conflitos.
- Benefícios distintos: não incidência, imunidade, isenção, alíquota zero,
  suspensão, reduções, diferimento e regime especial; manter fundamento,
  requisitos, evidência, vigência e efeitos sobre crédito/obrigações.
- Motor transversal de obrigações posterior à decisão, sem recalcular tributos.
- Projeções completas das obrigações cobertas, sem transmissão automática.

Fase7 — validação cruzada e gates finais:

- Compatibilidade entre classificação, incidência, local, responsável,
  regime, base, taxa, benefício, crédito, documento, obrigação, período/código.
- Corpus transversal de famílias, perfis e transições; falta de fatos,
  conflitos, recusas e revisão manual, além dos casos positivos.
- Decisão/trace/hash/snapshot/replay por cenário.
- Gates gerais, importadores/migrations PostgreSQL, persistência/replays,
  integridade, fontes e revisão de aceitação final.

Fases5–7 continuam planejadas, apesar dos componentes preparatórios existentes.

## 10. Console e limites do produto atual

O motor permanece orientado à CLI/JSON. Existe interface de acompanhamento
somente leitura em `http://localhost:8080/console-fiscal` e status em
`/console-fiscal/status`. Acesso LAN depende do IP atual/porta8080/firewall.

View: `resources/views/fiscal-console.blade.php`.
Rotas: `routes/fiscal-console.php`, incluídas em `routes/web.php`.
Observador: `resources/monitoring/FiscalConsoleSnapshot.php`.
Registros: `roadmap.json`, `task-progress.json`, `objective-review.json` no
mesmo diretório. Console sem sidebar, cantos retos, cores neutras e detalhes
expansíveis. Não executar cálculos fiscais, publicar regras ou alterar banco
ao consultar a tela. O console não prova saúde do banco/processos em execução.

Percentual de entrega = marcos concluídos / marcos definidos da entrega.
Não confundir com conclusão de grupo, fase ou produção. Não há percentual
funcional global auditado da4B.0/8 encerramentos formais não é0% de código.

Não estão implementados como produto SaaS: interface de operação fiscal para
clientes, API fiscal HTTP de negócio, autenticação, billing, multitenancy,
assinatura/transmissão governamental ao vivo. O console de monitoramento não
representa implementação desses recursos. Produção segue bloqueada.

## 11. Evidências e leitura inicial obrigatória

Ler `AGENTS.md`; depois:

- `docs/adr/README.md` e ADRs001–006.
- `docs/fiscal/implementation-status.md` e `phase4b-status.md`.
- `docs/fiscal/phase3-closure.md` e `phase4a-status.md`.
- `docs/fiscal/phase4b-advance91-local-integration.md`.
- `docs/fiscal/phase4b-document92-fresh-research.md`.
- `docs/fiscal/phase4b-acceptance93-artifact-review.md`.
- Relatório de aceitação91 e manifest/bundle correspondente.
- `resources/monitoring/{roadmap,task-progress,objective-review}.json`.
- Inventário original:
  `storage/app/phase4b-original-requirements72-audit-v3-20261014/original-requirements-inventory.json`.
- Plano original:
  `C:/Users/gabriel.oliveira/.codex/attachments/e85edde7-ac3a-4a4a-b380-9cf54627bd84/Texto colado.txt`.

Documentos contêm cronologia: priorizar aceitações terminais mais recentes,
sem interpretar parágrafos de preparações antigas como situação atual.
Ao trabalhar com regra fiscal, revalidar as fontes oficiais pertinentes;
este prompt relata implementação, não certifica legislação vigente em16/10.

## 12. Regras para continuidade

- Preservar alterações do usuário; não resetar/limpar/trocar branch, apagar
  histórico, sobrescrever evidência ou criar commit/push sem pedido.
- Ler estado real antes de editar; não promover Sandbox a produção.
- Não reiniciar suíte viva por timeout de observação; verificar processo/saída.
- Desenvolvimento sequencial com testes dirigidos por escopo; gates amplos
  no fechamento ou quando houver motivo concreto, sem repetir suíte inteira
  por rotina. Não aceitar suíte ativa/incompleta como aprovada.
- Mudanças de conhecimento exigem versão própria, fonte, vigência,
  disponibilidade, fatos/perguntas, cenários, trace, invariantes e replay.
- Preservar compatibilidade com bundles e snapshots históricos.
- Conclusão técnica, encerramento funcional de fase e produção são gates distintos.
- Seguir `AGENTS.md`: atualizar console, histórico, objetivos e fases ao
  finalizar tarefa; preservar histórico e remover pendência superada apenas
  dos campos atuais. Conferir status e executar check isolado do console.

Exemplos de execução no checkout, com o caminho do Docker no PATH:

```powershell
docker compose exec -T app php -d memory_limit=512M artisan fiscal:scenario:run CAMINHO --bundle-manifest=bundles/consumption-phase4b-2026.10.15-91-draft1.json
docker compose exec -T app php -d memory_limit=512M vendor/bin/pest --filter=ESCOPO
docker compose exec -T app php scripts/check_fiscal_console.php
```

## 13. Próxima direção sugerida

A frente indicada no checkpoint91 é a obrigação documental da exportação
parcial: ligar documentos separados às parcelas da mesma operação, fonte,
alocação e decisão, com bases ISS/RTC qualificadas independentemente,
respeitando os limites dos schemas observados na pesquisa92.

Em paralelo ao planejamento dessa frente, manter o inventário de80 requisitos
como referência de aceitação. Não substituir requisitos obrigatórios da4B
por aprofundamento indefinido de um regime vertical da Fase5.

Primeiro apresente um diagnóstico do estado encontrado, eventuais diferenças
em relação a este checkpoint e a próxima entrega delimitada. Só execute uma
nova implementação quando ela fizer parte do pedido recebido. Nunca declare
o motor completo apenas pela contagem de testes ou número da geração.
