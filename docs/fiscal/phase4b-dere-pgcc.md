# DeRE — qualificação local do PGCC

Estado corrente — Candidato22 (06/10/2026): migração dos seis eventos mensais suportados ao mesmo pacote oficial XSD1.3.0; D1011v1.0.4 e D1121v0.0.2 com conta e histórico de continuação conferidos. Suíte898/2.901, PHPStan sem erros, Pint361,46 casos e41 corpos XML, regressões22/21 e2.203 históricos preservados. Provas positivas sintéticas; dedução de correspondente em conta única e D2101 sem movimento. Gate43/43 fontes, `ready=false`,119 bloqueios; Fase4B parcial. [Aceitação e limites](phase4b-dere-schema13-migration.md).

Atualização06/10/2026 — Candidato21: D2101 sem movimento para contas zeradas dos códigos110113001/110113002, com prova independente e XSD próprio1.3.0, integrado ao fechamento. Movimentos/saldos não nulos e D1106 permanecem pendentes. Os contratos e medições dos candidatos anteriores abaixo são históricos. [Contrato e aceitação21](phase4b-dere-d2101-no-movement.md).

## Candidato20 — reconciliação com o D1121 obrigatório

Candidato20: D1121 obrigatório411011105 reconciliado ao PGCC, lançamento e documento, com dedução financeira integral separada da parcela declarada. Inclui original, continuação, competência sem apropriação e captação isolada. Provas positivas sintéticas. Suíte860/2.752, PHPStan sem erros, Pint358, corpus19/19, quatro novos replays e18 corpos XML aprovados; regressões20/19/18/17 e1.925 históricos preservados. Gate42/42 fontes, elegível local, `ready=false`,119 bloqueios. Fase4B parcial e defaults preservados. [Aceitação e limites](phase4b-dere-d1121-pgcc-binding.md). O catálogo selecionado20 possui sete referências COSIF; aplicação ao contribuinte continua dependente de prova independente.

## Histórico — Candidato19 — ativação local com catálogos oficiais

D1011 original ativado no candidato19 com seis referências COSIF,756 códigos Tabela11 e listas oficiais de auxiliares4/34/2. A política exige provas datadas independentes de instituição e mapeamento por conta; positivos sintéticos, sem homologação histórica universal. Três divergências RN/Tabela11 registradas e bloqueadas. Corpus12/12, suíte830/2.700, PHPStan sem erros, Pint356 e replays19/18/17 aprovados. Quatro corpos XML conferidos;1.771 históricos preservados. Gate42/42 fontes, `ready=false`,119 bloqueios. [Aceitação do objetivo e limites](phase4b-dere-pgcc-activation.md). As medições seguintes são históricas.

## Histórico da verificação — 05/10/2026

Recuperado Docker Desktop e retomados os serviços existentes com PHP 8.5.10 e PostgreSQL saudável, sem apagar volumes ou reinicializar a base. A execução herdada sem resultado confirmado foi substituída por execução concluída: PGCC e fechamento mensal passaram inicialmente com **61 testes/108 asserções**.

`fullDeclaration=true` no qualificador e `historical_declaration_required=true` na política D1011 incluem contas históricas inativas. Referência, código tributário, pai e origem de desdobramento misto precisam cobrir todo o intervalo da conta até o fim da competência. As obrigações auxiliares mensais continuam derivadas exclusivamente de contas ativas no fim do período, no contrato corrente. Catálogos com múltiplas versões ainda não são resolvidos automaticamente.

Acrescentada cobertura da origem de conta mista com intervalo completo e lacunas de início/fim, e do D1011 histórico junto ao fechamento mensal: sete contas na declaração, seis na qualificação mensal, três no balancete; obrigações obsoletas D1106/D2101 não são transportadas para o mês. Os quatro eventos são conferidos nos XSDs preservados, com somente assinatura ausente.

Qualificação/projeção D1001 e integração opcional acrescentadas no mesmo corte; detalhes em `phase4b-dere-contributor.md`. Validação final das três frentes e do comando mensal: **116 testes/236 asserções**. PHPStan completo sem erros; Pint aprovou **353 arquivos** antes da última ampliação do teste do comando, que também passou no Pint. Suíte ampla executada antes desse último caso: **768 testes/2.514 asserções**. Logs em `storage/app/phase4b-resumption-20261005-1/`; medições abaixo são históricas.

O verificador existente do candidato17 foi executado antes e depois da implementação: corpos D1101/D1121/D1199, decisão, snapshot e relatório reproduzidos; corpus15/15 RTC e3/3 geral, oito replays herdados. Conferência independente de1.642 artefatos em recursos/evidências: nenhuma alteração de SHA-256. Gate17 continua com32/32 fontes íntegras, elegível local, `ready=false`,119 bloqueios. Não houve ativação de PGCC/D1001 em bundle novo; continuam pendentes catálogos oficiais qualificados, tabela de obrigatoriedade, demais eventos e homologação mensal integral.

## Implementação em desenvolvimento

`DerePgccQualifier` confere concatenação de conta interna e desdobramento, unicidade, nomes/descrições, natureza, raiz sintética, pai existente e sintético, níveis crescentes e desdobramento de conta mista. Desdobramentos analíticos conservam referência da conta original, nível subsequente e mesma conta superior conforme o leiaute observado. O caminho mensal padrão exclui contas históricas inativas; a declaração completa opcional inclui essas contas e exige cobertura histórica das referências e códigos tributários.

Exige plano completo declarado, hashes, observação temporal e catálogos referenciais/tributários previamente qualificados. Códigos tributários analíticos possuem nove dígitos; campos tributários em contas sintéticas são rejeitados. Obrigações auxiliares D1106/D1121/D2101 são derivadas da união de indicadores explícitos nas linhas tributárias, nunca por ausência de dados. Catálogo sem esses indicadores fica pendente.

Fontes de regras: leiaute DeRE1.2.0 e AnexoII preservados. Regras consideradas: `CCTA_CONCATENACAO`, `UNICIDADE_CCTA`, `DESCRICAO_CTA`, `PAI_CTA_ANALITICA`, `INDCTA_CONTAS_DESDOBR`, `OBRIGAR_CCTAREF_IGUAL_CONTA_PRINCIPAL`, `OBRIGAR_CODTRIB`, `OBRIGAR_IND_TRIB_ISS`. O leiaute define `codNat=4` como receita e `codNat=5` como despesa; a futura ativação deve usar essa correspondência. Fixtures históricas D1101 trataram4/5 conjuntamente como contas de resultado e ainda precisam de uma nova versão com classificação individual correta, preservando os snapshots anteriores.

## Fonte COSIF observada

Preservado PDF de817 páginas em `resources/fiscal/sources/official/cosif-bcb-completo-observed-20261002.pdf`, obtido em `https://www3.bcb.gov.br/aplica/cosif/completo`; SHA-256 `9bd7f7bd7483e14e9a23ca2b8805c3ad80bbd199fa8cff1a7d167573bd994dd1`,10805510 bytes. Índice independente `cosif-bcb-source-observation-20261002.json`. A página181 foi renderizada e conferida visualmente, incluindo códigos, funções e referência normativa. O endpoint retornou PDF apesar da ausência de extensão na URL; o artefato foi identificado pelos bytes.

O AnexoI informa que os planos COSIF/SPED/SUSEP/PREVIC/ANS são referências externas. O PDF observado não comprova, sozinho, vigência histórica de cada conta, atributos aplicáveis a cada instituição ou aprovação de produção. Não foi incorporado aos32 hashes do bundle17; aguarda novo bundle e qualificação das linhas e atos correspondentes.

## Integração com o fechamento local

`DereMonthlyClosingXmlProjector` aceita a política explícita `pgcc_qualification_required=true`. Nesse caminho, o plano bruto vem da entrada mensal e os catálogos vêm da política, separados do plano declarado. Fornecedor, período e hash do plano devem coincidir com o livro; a frequência de encerramento também precisa coincidir.

O fechamento executa o qualificador do PGCC, compara as contas analíticas ativas com o mapeamento declarado no livro, exige todas essas contas no balancete e confronta os indicadores de D1106/D1121/D2101 com as obrigações derivadas dos códigos tributários. Inclui o resultado da qualificação na projeção. Ainda não ativado no bundle17; a futura versão precisa vincular catálogos oficiais qualificados. O caminho anterior17 foi reproduzido com os mesmos hashes e resultado preservado.

`DereMonthlyClosingXmlProjectorTest.php`:23 testes/53 asserções após a integração, PHPStan passou, Pint sem alterações adicionais. Oito casos adicionados usam plano e linhas de catálogo explicitamente sintéticos: um caso positivo com livro financeiro realmente calculado e sete rejeições por fonte, escopo, frequência, contas, completude, obrigação auxiliar e duplicidade. Na nova fixture, receita recebe `codNat=4` e despesa recebe5; as anteriores permanecem preservadas.

## Verificação e pendências

## Projeção D1011 local

Implementado `DerePgccXmlProjector`, contrato `DERE-PGCC-LOCAL-2026.10.02-1`, para inclusão original em ambiente restrito. Repete a qualificação do plano, exige identidade do contribuinte, datas de validade do cadastro e data de início da DeRE explicitamente qualificadas. Não assume calendário obrigatório pela presença do XSD. Confere sequência, geração e observação temporal, depois projeta validade, plano referencial, frequência, contas sintéticas/analíticas e vigências individuais no namespace `evtPGCC/v1_0_3`.

O caso de teste passou no `evtPGCC-v1_0_3.xsd` oficial preservado com somente assinatura ausente. `DerePgccQualifierTest.php` agora cobre29 testes/38 asserções, incluindo projeção, cadastro, calendário, geração e recusa de excluir silenciosamente contas históricas de uma declaração completa. O calendário e mapeamento desse teste são fixtures qualificadas, sem prova de implantação oficial.

O componente mensal aceita `pgcc_projection_required=true` junto à qualificação obrigatória. Reconstitui também D1011 e devolve sua projeção; a configuração contraditória é recusada. O comando mensal passa a conferir o D1011 no XSD e salvá-lo como `caminho.xml.D1011.xml` quando presente, preservando as mesmas regras de não sobrescrever. `DereMonthlyClosingXmlProjectorTest.php`:24 testes/57 asserções, com D1011 de seis contas no conjunto mensal e rejeição de identidade ausente. PHPStan dos dois projetores e do comando passou; Pint aplicado. Candidato17 anterior reproduzido com todos os hashes preservados.

Esse caminho novo ainda não foi ativado em bundle/corpus documental, pois os catálogos oficiais e a tabela de obrigatoriedade não estão qualificados. A declaração completa com contas inativas está implementada no caminho opcional descrito na atualização de05/10; o projetor continua recusando publicar um subconjunto como se fosse o plano inteiro. Permanecem cadastro D1001 integral com todas as situações cadastrais, homologação de referências por data/atributo e veracidade das provas.

`tests/Fiscal/Unit/DerePgccQualifierTest.php`:17 testes/20 asserções, PHPStan passou, Pint aplicado. Três casos positivos: hierarquia, desdobramento misto e histórico inativo. Quatorze casos negativos cobrem fonte não qualificada, plano distinto, observação futura, identidade, duplicidade, vigências, código incompleto, obrigação desconhecida, descrição e hierarquia. As linhas datadas e as obrigações dos testes são fixtures explícitas, sem homologação jurídica do mapeamento.

Ainda falta ativar a integração em novo bundle com catálogos qualificados, conferir códigos referenciais oficiais por vigência/atributos, obter e qualificar a tabela de obrigatoriedade dos auxiliares, resolver múltiplas versões históricas, completar situações cadastrais D1001 e homologar o conjunto mensal. Não substitui recibos de recepção nem valida externamente a veracidade do plano. O gate de produção permanece false; a Fase4B continua aberta.
