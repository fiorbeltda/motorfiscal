# Candidato20 — D1121 obrigatório reconciliado ao PGCC

Objetivo: concluir o caminho local do D1121 obrigatório para despesa de correspondente bancário, código fiscal `411011105`, integrando conta PGCC, lançamento financeiro, documento de dedução e fechamento. O candidato `CONSUMPTION-PHASE4B-2026.10.05-20` sucede o19 e exige essa reconciliação. O objetivo deste recorte está concluído; a Fase4B permanece parcial e produção bloqueada.

## Escopo e fontes

A Tabela11 do AnexoI1.3.0, página34 do PDF, descreve `411011105` como despesa com correspondente vinculada a crédito, câmbio e TVM, comprovada por documento fiscal idôneo; a RN do AnexoII, página12, inclui esse código no D1121 obrigatório. A política específica associa a conta ao lançamento `ADVISOR_EXPENSE`, categoria `BCB_REGISTERED_CORRESPONDENT`, cujo enquadramento e autorização já são qualificados pelo motor financeiro. Outros códigos obrigatórios D1121 permanecem sem esse mapeamento e bloqueiam o caminho novo.

O catálogo COSIF20 acrescenta a referência `8.1.9.99.00.00-6`, outras despesas operacionais, observada na página551 do PDF BCB preservado. São sete membros selecionados. A aplicação dessa referência ao contribuinte e sua classificação como correspondente dependem da prova independente já exigida pelo PGCC; o elenco oficial não fornece automaticamente esse mapeamento. O positivo é sintético. O PDF foi renderizado e a linha conferida. As42 fontes oficiais permanecem vinculadas por hash.

A seleção técnica dos catálogos continua distinta das datas fiscais da aplicação ao contribuinte. Não foram inventadas vigências COSIF. As três divergências RN/Tabela11 do candidato19 continuam registradas e bloqueadas. O pacote completo XSD mensal permanece o1.2.0 preservado; esta entrega não afirma homologação mensal integral1.3.0.

## Reconciliação

`DerePgccDeductionBindingQualifier` utiliza o PGCC previamente qualificado e o livro analítico reconstruído. Identifica as contas com códigos da lista oficial D1121, seleciona seus lançamentos dedutíveis e confronta espécie do lançamento, categoria do prestador, valor apropriado e natureza da apuração. Conta com apropriação positiva sem vínculo financeiro é recusada.

Cada documento declara as contas PGCC correspondentes. Uma prova independente vincula contribuinte, competência, chave/tipo/data/atividade do documento, valor deduzido, lançamentos e contas. Ela precisa ser qualificada, identificada, íntegra por hash, conhecida até a observação da entrada documental e cobrir a competência. Provas futuras, vencidas, adulteradas ou de outro contribuinte são recusadas. Essa prova de vínculo é declarada externamente; não equivale a consulta, autorização ou autenticação do DF-e.

Todos os lançamentos declarados no D1121 precisam pertencer às contas aplicáveis e ser utilizados uma única vez. O relatório documental cobre a parcela selecionada. A reconciliação financeira integral permanece no motor de base e no balancete: a operação original não é alterada. São registrados os hashes da operação original e do subconjunto documental, os lançamentos incluídos/excluídos e seus valores.

Exemplo positivo: receita1000, principal excluído10000, captação200 e correspondente100. A base financeira é700, com IBS nominal0,70/CBS6,30 conforme a fixture2026. O D1121 declara somente100 do correspondente; os200 de captação permanecem na base e no balancete. Isso evita que a simples presença de uma conta obrigatória transforme todas as deduções financeiras em registros do evento.

O qualificador documental existente continua conferindo chave/tipo, emissão, limites por documento/item, histórico de apropriação e soma dos vínculos. O positivo de continuação usa documento de agosto, limite150, acumulado50 e apropriação de setembro100; não redeclara `vDedTotal`. Histórico incompleto ou teto excedido continuam bloqueados.

Quando a conta obriga D1121, mas não há apropriação documental no mês, não se cria D1121 e o D1199 declara `indInexistDedu=1`, mesmo existindo captação dedutível. Se o PGCC só contém captação, D1121 não é obrigatório e o fechamento não inclui esse indicador. Os quatro positivos são inclusão, continuação, conta obrigatória sem apropriação e captação isolada.

## Ativação e reprodução

O20 ativa `pgcc_deduction_binding_required` no fechamento e vincula o mapeamento de `411011105`. O comando mensal selecionado com o manifesto20 reconstrói D1001/D1011/D1101/D1121/D1199 nos positivos documentais; os positivos sem apropriação produzem quatro eventos. Os contratos de versões anteriores e o default17 são preservados. O comando isolado D1121 mantém seu default16 e contrato histórico; a nova reconciliação é exigida na orquestração mensal20.

Materializar: `python scripts/fork_phase4b_dere_d1121_binding_bundle.py`. Verificar: `python scripts/verify_phase4b_dere_d1121_binding_cut.py`. Auditar: `python scripts/audit_phase4b_dere_d1121_binding_cut.py`. Os scripts recusam substituir recursos/evidências diferentes. Artefatos em `storage/app/phase4b-d1121-binding-20261005-20/`, incluindo quatro snapshots financeiros, quatro relatórios mensais,18 XMLs, corpus, logs, render e `verification-report.json`.

## Aceitação

Suíte completa PHP8.5.10: **860 testes/2.752 asserções**, PHPStan sem erros e Pint358 arquivos aprovado. Trinta testes novos validam seleção, classificação, vínculo, valor, natureza, prova, tempo, continuação e inexistência de deduções documentais. Corpus pelo comando **19/19**: quatro positivos e15 bloqueios. Quatro snapshots financeiros reproduzidos;18 corpos XML conferidos no XSD com somente assinatura ausente.

Regressões20/19/18/17: corpus RTC15/15 e integrado3/3, oito replays herdados por candidato e os recortes documentais anteriores preservados. **1.925 artefatos históricos sem alteração**. Gate20:42/42 fontes íntegras, elegível para validação local, `ready=false`,119 bloqueios.

| Planejamento inicial | Entrega do recorte | Limite |
|---|---|---|
|4B.7 — documentação|D1121 obrigatório411011105 ligado a PGCC e fechamento|Original local, atividade01; sem recepção|
|4B.8 — bundle, corpus, trace, hash e replay|Candidato20,19 cenários e hashes de reconciliação|Não encerra todas as famílias/temporais de4B|
|5.4 — perfil financeiro|Distingue dedução financeira integral da parcela documental|Perfil integrado completo continua pendente|
|6.5 — obrigações|Reconcilia obrigação, apropriação e documento|Sem governança ou execução completa de auxiliares|

Continuam pendentes os demais códigos/atividades D1121, verificação real de documentos e cadastro/calendário, histórico normativo COSIF, saneamento das divergências oficiais, D1106/D2101, alterações/exclusões, bases negativas, reabertura e homologação integral. As provas positivas são sintéticas. Não houve assinatura, transmissão, recibo, fechamento governamental ou recolhimento; as Fases4B/5/6/7 não são declaradas concluídas.
