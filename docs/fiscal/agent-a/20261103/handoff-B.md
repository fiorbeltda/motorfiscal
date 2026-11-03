# Handoff A → B — 03/11/2026

Disponibilizado ao usuário como ponte; recebimento e integração pelo B não comprovados.

## Corte atual de A — A153

Estado: desenvolvimento local dos 44 requisitos; aceites escopados A145–A153,
sem grupo formalmente encerrado. Regressão dirigida acumulada: 508 testes e
1.807 asserções; não equivale à regressão integral final integrada.

Entregas atuais: composição de fornecimentos; correções temporais/recolhimento;
local material, áreas/extensões, estabelecimento/matriz e domicílio pessoal/não cadastrado.
Pendências: cobertura integral dos 44; classificação/projeção e validação
cruzada com B; regressão integral integrada. Próximo foco: PF empresarial e revisão dos44.
Propostas ao console são locais neste handoff/recibos; A não editou o painel.

## Retomada e reconciliação A144

- Base comum observada: `87d22e70787b128a97e5aed4b175a39e619798af`.
- A trabalha no clone isolado `D:/ProjetoSaasWorkspaces/agente-a-fase4b-20261103`.
- Escopo A: 44 requisitos de 4B.1–4B.4. Nenhum grupo encerrado nesta retomada.
- Manifesto de escrita A144: `task-A144-manifest.md` neste diretório.
- Relatório: `requirements44-reconciliation.json`, incluindo hashes, caminhos,
  âncoras, testes localizados e limites por ID. Vínculos não são aceite funcional.
- Proposta para a matriz global: corrigir a descrição atual de 4B.2.8 para
  considerar a montagem local completa do perfil143, vinculada a 4B.7.1.
  Preservar histórico e a recusa oficial observada em 27/10; não afirmar que é
  a situação oficial atual sem consulta. Restantes perfis continuam parciais.
- Não tratar o defeito de ciclo114, a coerência geográfica116 ou as entradas
  monetárias119 como falhas abertas: as remediações posteriores estão preservadas.
- Operações combinadas4B.4.6 e redução de base4B.4.9 exigem aceites próprios;
  ajustes múltiplos e redução de alíquota não substituem esses requisitos.

## Proposta de console ao consolidante B

Estado: desenvolvimento local A retomado; reconciliação dos 44 IDs em andamento.
Entregas: clone exclusivo; integridade141; vínculos atuais dos44 em relatório próprio.
Pendências: prova funcional integral dos44; integração A+B; regressão final integrada.
Próximo passo: completar evidência dirigida e atacar a menor lacuna temporal real.
Não substituir o histórico de parada143; registrar a retomada como novo evento.

Preparação validou PHP8.5.10/Laravel13.32/Pest4.7.8; dependências alinhadas ao lock;
24 testes143/131 asserções; console28; endpoint interno HTTP200.
Esses resultados comprovam preparação, não regressão integral nem aceite dos44.
Não houve edição da matriz80, status global, console, catálogos, DPS ou arquivos B.
Sem commit/push/PR/merge. Integração/publicação aguardam ordem específica do usuário.

## A145 — composição de fornecimentos independentes

Contrato: `RTC-COMBINED-INDEPENDENT-SUPPLIES-2026.11.03-A145`.
Entrada própria via `fiscal:rtc:assess-combined-supplies`; nenhum contrato
compartilhado existente alterado. Novo componente/comando pertencem a A4B.2/4.4.

Interface para B: `report.supplies[*]` contém ID textual, valor especificado,
evidência de alocação, snapshotRTC e snapshot integrado completos, incluindo
classificação, projeção individual e proveniência dos bundles. `tax_groups`
preserva data/UF/município. `totals` só existe se todos os itens passarem.
Não calcular crédito ou gerar XML combinado a partir apenas do total.

Teste positivo: SaaS1000,00 + educação1000,00; base2000,00; IBS estadual1,40,
municipal0,00,CBS12,60; provas e tratamentos distintos.24 testes/96 asserções,
duasCLI idênticas/replay completo e6 oráculosDecimal. Ver `validation-A145.json`.
O resultado preserva `document_obligation_complete=false` e projeção combinada
pendente de B; não implica encerramento4B.4.6,4B.7 ou produção.

Proposta de matriz/console: registrar avanço escopado4B.4.6 e memórias por item;
manter pendências de exceções principal/acessório, moedas/perfis e integração.
Não editaraceite143 nem alegar regressão integral desta versão.

## A146 — hipótese principal/acessórios

Novo modo explícito `PRINCIPAL_ACCESSORIES`, contratoA146, com único fornecimento
fiscal. `report.accessories` preserva IDs,papel de condição/meio e evidências de
vínculo; `report.supplies` tem apenas o principal avaliado sobre o preço total.
Não criar linhas fiscais adicionais nem deduzir preços para os acessórios.
40 testes/165 asserções incluemA145;3 oráculosDecimal,CLI repetida e compatibilidade
byte a byte da CLIA145. Recibo `validation-A146.json`.

Proposta para4B.4.6: acrescentar o tratamento local comprovado do art7,II/§2,
preservando parcialidade de outros perfis e integração documental. Console e
matriz globais continuam sem edição porA. O material está disponibilizado ao
usuário; integração e recebimento pelo B continuam sem comprovação.

## A147 — precisão de revogação/substituição temporal

Arquivo A alterado: `TaxPointContext86.php`; assinatura e resposta preservadas.
O parser passa a tratar microssegundos e UTCZ sem truncar; instantes inválidos
são recusados sem exceção. Fonte,hashes,vigências,catálogos e políticas instaladas
não mudaram. Novos testes usam somente ciclos sintéticos em memória.

40 testes dirigidos/125 asserções; regressão dirigida221/735 incluindo esses40;
8 comparações de instantes por oráculoPython;CLI sem ciclo e replay do snapshot
anterior preservados. PHPStan0/PintPASS. Ver `validation-A147.json`.
Proposta de matriz: registrar correção escopada em4B.1.6/4B.1.7/4B.1.8; manter
os requisitos parciais e as demais dependências. Não há revogação oficial nova,
implantação de split, regressão integral final ou grupo encerrado.

## A148 — integridade das referências de recolhimento

Arquivo A alterado: `CollectionDecisionEngine.php`; assinatura e respostas
válidas mantidas. Referências em branco/padded/com controles e identidade da
parte usada na qualificação retornamACTION_REQUIRED/fato exato. IDs textuais
permanecem intactos; nenhuma identificação local autentica usuário ou pagamento.

33 testes/125 asserções; regressão dirigida254/860 incluindo os33;
quatro provas nos dois runners e CLI/snapshot válido anterior preservados.
PHPStan0/PintPASS; recibo `validation-A148.json`.
Proposta de matriz4B.3.1/3.2/3.7/3.8/3.10: registrar correção material escopada;
manter as pendências de ciclo de pagamento e integração. Elegibilidade continua
distinta de extinção; não há novo crédito ou implantação oficial de split.

## A149 — contrato de local material para serviços especiais

Novo componenteA `ServiceLocality149` e CLI `fiscal:rtc:qualify-service-locality`.
Contrato `RTC-SERVICE-MATERIAL-LOCALITY-2026.11.03-A149`; saída inclui artigo,
papel do local,UF/município,operação/fornecimento/partes/data e hashes dos registros.
Oito famílias locais qualificadas no escopo de prova;30 testes/144 asserções,
CLI repetida,replay e hashes independentesPython. Recibo `validation-A149.json`.

Para integraçãoB4B.6/4B.7: consumir a qualificação material antes da seleção de
códigos/documento, preservando a origem da regra e os dados das partes.
A não escreveu mapeamento cIndOp,cClassTrib,NBS,enum global ou schema.
`classification_catalog_integration=PENDING_AGENT_B`; nenhuma alíquota ou
direito de crédito é determinado por esse contrato de localidade.

Proposta de matriz4B.2.3/2.4/2.5/2.7: avanço escopado nas oito famílias;
manter pendências de áreas/extensões,contratação centralizada,domicílios e
integração. Dados geográficos observados não provam cadastro/história jurídica.

## A150 — imóvel multiterritorial e extensão de via

Contrato opt-in `RTC-MEASURED-TERRITORIAL-LOCALITY-2026.11.03-A150` no qualificador
próprioA149. Novo campo de entrada é exclusivo desse contrato; nenhum enum/fato
global deB foi alterado. Registros de cobertura/medida exigem vínculo completo.

Imóvel:agregar área por município e selecionar destino único de maior área;
participação1/1 do fornecimento nesse destino. Empate exige qualificação, sem
fallback. Via: `jurisdiction_allocations` e `state_allocations` com frações
exatas por extensão. Não converter esses territórios em fornecimentos adicionais
ou somar os dois níveis para duplicar a base. Não há cálculo de imposto/crédito.

55 testes/246 asserções incluemA149; regressão dirigida309/1.106 inclui os55;
3 oráculosFractionPython,CLI repetida/replays e saída149 preservada byte a byte.
PHPStan global0/PintPASS; recibo `validation-A150.json`.
Proposta de matriz4B.2.4/2.5 e efeitos4B.4.11/4.12/4.14: registrar precisão e
destinos/participações comprovados no escopo. Integração de códigos/documento
seguePENDING_AGENT_B. Nenhum grupo/fase ou produção foi encerrado.

## A151 — domicílio de entidade e vínculo opcional do serviço remoto

Contrato próprio `RTC-REGISTERED-ENTITY-DOMICILE-2026.11.03-A151`, componente
`EntityDomicile151`, CLI `fiscal:rtc:qualify-entity-domicile`. Escolhe a parte
aplicável por onerosidade/residência e vincula um estabelecimento atendido ao
cadastro fornecido, sem substituir filial pela sede ou endereço de faturamento.
Para PJ/entidade sem personalidade jurídica regularmente cadastrada; pessoa
física, não cadastrado, centralização e vários estabelecimentos continuam
dependências explícitas. Não houve consulta oficial ou prova de autenticidade.

Ligação opcional nos próprios arquivos A149: `entity_domicile_qualification`
deve reproduzir todo o relatório A151 e coincidir em operação, fornecimento,
partes, data e conhecimento. Saída
`RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151`, para serviço pessoal remoto.
Entradas antigas mantêm o comportamento anterior. CLI A149/A150 preservadas
byte a byte. Nenhuma factory, enum, catálogo, crédito ou schema global editado.

120 testes dirigidos / 483 asserções incluem 55 A149/A150. Regressão acumulada
374 / 1.343 inclui os 120; cinco oráculos Python independentes, CLIs repetidas,
recusa e replay. PHPStan global sem erros e Pint dos seis PHP aprovado.
Ver `validation-A151.json` e `domicile151-results.md`.

Interface: `destination_basis`, `selected_party_id`, `selected_establishment_id`,
`locality`, registros e hashes. Classificação/projeção continuam pendentes de B;
o contrato não determina incidência, alíquota, importação/exportação ou crédito.
Proposta estruturada de matriz/console no recibo para 4B.2.4/2.5/2.7/2.10 e
4B.3.2: registrar avanço escopado; manter parcialidade e demais dependências.
Aplicação e recebimento por B não comprovados. Sem publicação ou encerramento.

## A152 — exceção de localização da contratação centralizada

Contrato opt-in `RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152` no componente
próprio A151. Exige aquisição centralizada, adquirente nacional, regime regular
IBS/CBS, mais de um estabelecimento ativo e matriz única vinculada. Famílias:
comunicação fixa, locação de bem móvel material, serviço residual e pessoal
remoto. Registro da matriz decide o destino; filial/terminal ou ordem do cadastro
não o substituem. Matriz alterada explicitamente para RJ foi confirmada por CLI.

Fronteira de crédito: A recebe `credit_restriction_evidence` com referência
opaca e predicados externos separados IBS/CBS. Falta/vedação/incompletude impede
a exceção. Não deduzir vedação de saldo, status de crédito ou pagamento. Os
predicados dos ensaios são sintéticos; não alegam origem real de B. A não criou
contrato/enum de B nem implementou direito de crédito. Integração real pendente.

Ligação opcional A149 com comunicação fixa e pessoal remoto, contrato
`RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152`, com replay completo e
igualdade de identidades/data/conhecimento/centralização. Inputs anteriores e
quatro CLI aceitas A149–A151 conservados byte a byte.

185 testes/722 asserções incluem 120 A149–A151; regressão dirigida acumulada
439/1.582 inclui os 185. Cinco oráculos independentes, CLIs repetidas, recusa
por falta de qualificação externa, PHPStan global sem erros e Pint6 aprovados.
Ver `validation-A152.json` e `centralized152-results.md`.

Interface para B: `destination_basis`, `selected_party_id`,
`selected_establishment_id`, `locality`, `active_establishment_ids`,
`covered_establishment_ids`, registros e hashes. Proposta estruturada no recibo
para 4B.2.4/2.5/2.7/2.10 e 4B.3.2/3.9: avanço escopado, classificação PARCIAL.
Preservar dependência da qualificação real de crédito, catálogos/projeções e
regressão integral integrada. Recebimento/aplicação por B não comprovados.

## A153 — domicílio pessoal e não cadastrado

Contrato próprio `RTC-MATERIAL-PARTY-DOMICILE-2026.11.03-A153`, opt-in
`party_domicile_qualification` no despachante A151. PF consumidora cadastrada:
habitação permanente única ou relevância econômica qualificada se nenhuma/várias,
confrontadas com cadastro. Contexto empresarial de PF permanece pergunta específica,
sem inferência pela inscrição. Não houve consulta oficial ou autenticação externa.

Não cadastrado: escolha explícita do fornecedor de ao menos dois tipos distintos,
vinculados e não conflitantes. Divergência de rua/número na mesma cidade é recusada.
Critério não escolhido divergente ou não qualificado é preservado sem ser usado.
IP municipal não gera rua. Endereço do arranjo do adquirente não prova o domicílio
de destinatário distinto. Alternativa declarada exige impossibilidade cobrindo os
quatro tipos e é vedada se existe par disponível não conflitante. Sem primeiro
endereço, IP isolado ou omissão convertidos automaticamente em alternativa.

Ligação opcional do serviço pessoal remoto, contrato
`RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153`, com replay completo e identidade
de operação/partes/data/conhecimento. `selected_establishment_id=null`; não
inventar estabelecimento para PF/entidade não cadastrada. Receber método,
`domicile_address`, `locality`, critérios escolhidos/não escolhidos, registros
e hashes antes de classificação/projeção. Integração de B continua pendente.

254 testes/947 asserções incluem 185 A149–A152; regressão dirigida acumulada
508/1.807 inclui os254. Dez oráculos independentes, três métodos na CLI,
recusa por falta de impossibilidade, replay e seis saídas anteriores preservadas
byte a byte. PHPStan global sem erros e Pint7 aprovados. Ver
`validation-A153.json` e `party153-results.md`.

Proposta estruturada para 4B.2.4/2.5/2.6/2.7/2.10 e 4B.3.2: avanço escopado,
classificação PARCIAL. Manter contexto de PF empresarial, integrações e aceites
restantes. Nenhuma alteração de fatos/engine/factory global, catálogo, crédito,
DPS ou console por A. Recebimento/aplicação por B não comprovados.
