# DeRE — balancete mensal local D1101

## Estado em 2026-10-02

Implementados `DereTrialBalanceQualifier` e `DereTrialBalanceXmlProjector`, contrato `DERE-TRIAL-BALANCE-LOCAL-2026.10.02-1`. A projeção recebe a operação financeira calculada, o livro analítico qualificado, a identificação local e a regra; repete a qualificação antes de produzir o candidato XML.

O qualificador confere competência encerrada, vínculo fornecedor/documento, vigência das contas analíticas no PGCC, continuidade patrimonial, reinício de contas de resultado conforme frequência, saldos com natureza devedora/credora e ajustes limitados aos movimentos. Os ajustes fiscais não alteram o saldo contábil. Todos os lançamentos financeiros precisam estar vinculados uma única vez; principal excluído da base tributável continua no livro.

A projeção usa o namespace oficial `evtBalancete/v1_0_1`, inclusão original, ambiente restrito e sequência explícita. Não cria recibos de recepção. A validação do candidato de teste contra os bytes preservados de `evtBalancete-v1_0_1.xsd` encontra somente a assinatura obrigatória ausente, com `RTC_DERE_UNSIGNED_LOCAL_CANDIDATE` e `production_ready=false`.

## Evidência

`tests/Fiscal/Unit/DereTrialBalanceQualifierTest.php`: 17 testes, 34 asserções. O caso positivo executa o cenário financeiro de empréstimo do bundle `2026.10.02-14`, vincula receita, principal e captação e valida o XML no XSD oficial. Casos negativos cobrem saldos, movimentos, ajustes, duplicidade, vínculos ausentes, PGCC, período anterior, datas e identidade do evento. PHPStan passou para os dois componentes; Pint aplicado.

Fontes preservadas: leiaute DeRE 1.2.0 (SHA-256 `7adbc82a55f0d60dfc2a63771a4c65c67d479e6a50a7821079e73f38b21873c7`), regras de validação (SHA-256 `b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb`) e arquivo XSD (SHA-256 `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818`). Esses hashes identificam os arquivos observados; não comprovam aprovação formal nem vigência de produção.

## Captura e replay do candidato 15

O bundle específico `CONSUMPTION-PHASE4B-2026.10.02-15` vincula política, fontes e controles do D1101. O comando `fiscal:dere:project-balancete {snapshot} {book}` reproduz o resultado completo com o conhecimento capturado, confere o hash do bundle selecionado, qualifica novamente o livro e valida o XML. Arquivos de candidato e relatório não são sobrescritos. O padrão geral dos demais comandos permanece14 enquanto a regressão integral do15 está pendente.

`scripts/fork_phase4b_dere_balance_bundle.py` materializa os recursos sem alterar predecessores. `scripts/verify_phase4b_dere_balance_cut.py` preserva o snapshot, candidato e relatório em `storage/app/phase4b-dere-balancete-20261002-15-*`, verifica replay e repete a projeção comparando hashes e corpo XML. XML observado: SHA-256 `ae99d818eafb9ec05afd6a9ec008b625ade236e1a4ee8a6bb293b5223b96b0db`. Testes do comando:4/15; qualificador/projetor:17/34; total dirigido21 testes/49 asserções. PHPStan do comando passou.

Gate15 com bundle e controles15 explícitos: elegível para validação local, `ready=false`, sem bloqueios de infraestrutura de desenvolvimento. Isso não declara conclusão funcional da fase. A primeira chamada usou por engano controles padrão14 e recusou o vínculo; a chamada corrigida, com controles15, passou. Não houve relaxamento do gate.

## Trabalho restante nesta frente

Ainda não promovido ao bundle padrão geral. A escrituração é uma fixture, não livro real homologado. O PGCC e seu mapeamento são entradas qualificadas; falta conferir integralmente plano referencial e códigos tributários, inclusive contas sintéticas/históricas e completude do plano. A vinculação atual exige soma não negativa igual ao movimento de apuração: lançamentos financeiros negativos e estornos cooperativos precisam de alocação própria. Não há fechamento D1199, relação de deduções D1121 ou homologação do conjunto mensal. A Fase 4B permanece em desenvolvimento, com produção bloqueada e sem assinatura, transmissão ou recolhimento.
