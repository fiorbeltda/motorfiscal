# ISS95 — resultado do serviço por documento

## Estado em16/10/2026

Atualização de continuidade: o qualificador95 foi incorporado e aceito no96, com testes permanentes e política/fatos/perguntas próprios; regressão terminal2.962/51.784 aprovada. O wrapper de snapshots95 continua apenas como preparação de referência. O96 vincula os fatos da avaliação corrente, sem replay recursivo. Ver `phase4b-export-iss96-live-integration.md`. O texto de preparação abaixo descreve seu checkpoint anterior à instalação.

Preparação isolada auditada, **não instalada no runtime**. O componente `IssExportResultQualifier95` está em `D:/ProjetoSaasExports/phase4b-iss-export95-preparation-20261016/`. Nenhum fato, pergunta, bundle ou ramo do engine foi ativado por esta preparação.

116 casos nativos e1.553 verificações aprovados; PHPStan e Pint dos dois arquivos finais aprovados. A auditoria vinculou os arquivos executados às cópias finais por SHA256, refez os checks e conferiu as5.360 entradas congeladas da regressão94 sem alteração antes e depois. Isso é verificação do protótipo, não resultado terminal da regressão94.

Relatório: `D:/ProjetoSaasExports/phase4b-iss-export95-preparation-20261016/iss-export95-preparation-validation.json`.
Fonte: `D:/ProjetoSaasExports/phase4b-document94-iss-bridge-sources-20261016/iss-bridge-primary-observation.json`.

## Motivo e escopo

A reprodução94 dos seis casos73 mostrou que a memória ISS do runner integrado cobre a operação inteira, enquanto o RTC possui alocações estrangeira e doméstica. Não se pode transformar aquela memória em duas decisões ISS apenas copiando o percentual RTC. O ramo ISS comum do engine atual suporta incidência tributável; a imunidade institucional tem ramo próprio, mas não é exportação.

O protótipo95 trata separadamente a prova de resultado para um documento de serviço de software, prestador e execução no Brasil, sem contexto especial. A fonte primária é a [LC116, art.2º,I e parágrafo único](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm), capturada com hash e observação precisos. A leitura sustenta distinguir o resultado do serviço de pagamento, residência ou consumo RTC; a qualificação operacional continua candidata, não aprovação legal ou autenticação da prova.

## Comportamento verificado

- Prova própria liga operação, documento, partes, transação, serviço e data. Hash canônico, cobertura temporal, conhecimento, revogação e supersessão são conferidos. Campos ausentes, adicionais, tipos incorretos, datas normalizadas e offsets além de14 horas são recusados; identificador literal `0` é preservado.
- Provas de pagamento, domicílio e consumo RTC não substituem prova material do resultado. Prova de um documento não serve para seu irmão por mera troca de identificador.
- Resultado declarado e localmente vinculado no exterior gera somente `LOCAL_ISS_EXPORT_NON_INCIDENCE_CANDIDATE`, com código documental candidato3. Não retorna imposto ou vISSQN zero.
- Resultado no Brasil gera `LOCAL_ISS_EXPORT_EXCEPTION_BOUND`: afasta esta alegação de não incidência por exportação, mas não impõe tributação normal ou código1 automaticamente, pois outros tratamentos ISS podem existir.
- Resultado misto ou desconhecido exige decomposição/prova individual; não aplica uma fração ao imposto. A janela2026–2032 é limite do contrato local, não nova data legal. Nada é extrapolado para2033.
- Os flags de aprovação legal, conversão documental, imposto calculado, alteração do ISS original, XML, obrigação documental, lançamento e produção continuam falsos em todos os116 casos.

## Próxima integração e guardas

A regressão94 e sua auditoria já terminaram aprovadas.95 foi incorporado e aceito ao96 no modo de avaliação corrente. Seu uso é **por documento**, com referência da operação e identidades das duas parcelas. Não aplicar a qualificação estrangeira à operação inteira de uma exportação parcial. Manter memória original preservada e decisões documentais próprias.

A conversão ISS/documental ainda precisa de contrato independente, confrontando CTN143 e legislação aplicável; a seleção cambial RTC não é transportada automaticamente. Continuam pendentes cálculo/base ISS por documento, reconciliação com bases RTC, par XML, schema/ambiente/data, demais perfis e a auditoria dos80 requisitos. Fase4B parcial; nenhuma produção liberada.

## Vínculo suplementar95 ao snapshot RTC

Preparação complementar auditada em `D:/ProjetoSaasExports/phase4b-iss-export95-pair-binding-20261016/iss-export95-pair-binding-validation.json`:58 casos nativos/856 verificações;10 vínculos locais e48 recusas. Não são58 testes Pest adicionais. PHPStan e Pint finais aprovados; primeira falha estática e cópias anteriores preservadas. As5.360 entradas94 permaneceram intactas.

`PartialExportIssResultPair95` recebe conhecimento confiável carregado do bundle do projeto, compara-o integralmente ao conhecimento do snapshot e exige replay nativo. Confere operação-referência, competência, documentos, partes, transação e papéis das duas parcelas, requalificando a prova ISS de cada documento. Os seis snapshots originais foram copiados e vinculados por hash; quatro variantes de origem foram recalculadas nativamente e replayadas, não construídas com uma decisão inventada.

Cobertura importante:

- Corpo alterado é recusado mesmo após recalcular seu hash; conhecimento forjado diverge do bundle configurado. Provas trocadas, revogadas ou sem vínculo à origem também são recusadas.
- A referência genérica da operação pode coincidir com uma nota; não exige três documentos. Os dois documentos das parcelas é que devem ser distintos.
- As parcelas são nomeadas `RTC_FOREIGN`/`RTC_DOMESTIC`, não categorias ISS. Os testes preservam resultado ISS no Brasil para parcela RTC estrangeira e resultado ISS exterior para parcela RTC doméstica, sem impor equivalência entre critérios legais ou aprovar tais dados como fatos reais.
- O BRL original73 continua recusado por referências documentais ausentes. Um novo caso sintético fornece explicitamente essas referências e é recalculado pelo engine; não modifica nem qualifica retroativamente o corpus antigo.
- A referência da operação é o identificador do cenário capturado, não uma identidade econômica autenticada. Identidades fiscais, data material ISS, conversão, valores por documento, XML, obrigação, lançamento e produção permanecem não qualificados. Não retorna imposto ou documento.

Este vínculo ainda está fora do runtime. A próxima integração deve consumi-lo com autoridade de conhecimento fornecida pelo projeto, jamais copiada do snapshot submetido, e completar conversão/base/identidade documental antes da projeção do par.
