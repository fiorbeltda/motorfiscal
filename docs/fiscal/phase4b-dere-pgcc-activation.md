# Candidato19 — PGCC oficial e auxiliares no recorte local

Objetivo do recorte: vincular referências oficiais e a tabela de auxiliares a uma política versionada, exigir provas independentes de aplicação ao contribuinte e ativar D1011 original na reconstrução mensal. Entrega: `CONSUMPTION-PHASE4B-2026.10.05-19`, sucessor do18. A aceitação é local e delimitada; a Fase4B continua parcial e `production_ready=false`.

## Catálogos e temporalidade

`dere-cosif-reference-2026.10.05-19.json` materializa seis referências do PDF oficial BCB preservado: três raízes e três referências para principal, receita de empréstimos e despesa de captação. Páginas226/505/545, códigos formatados, títulos, ESTBAN e hash do PDF permitem reprodução. A coluna de atributos dessas páginas está vazia. Não se inferiu tipo de instituição nem vigência histórica pelo elenco atual.

`dere-tax-codes-2026.10.05-19.json` contém os **756 códigos de nove dígitos** da Tabela11, AnexoI1.3.0, com linha de título extraída e página. Foram conferidos todos os tokens de nove dígitos das páginas da tabela: nenhum ficou sem materialização. O catálogo comprova presença no domínio, não classificação automática de uma conta.

A RN da página12 do PDF AnexoII1.3.0 contém listas completas de obrigatoriedade: D1106 com4 códigos, D1121 com34 e D2101 com2. Elas são materializadas separadamente da presença na Tabela11. Os códigos `111270002`, `111290001` e `411031281` aparecem na RN, mas não na Tabela11 extraída. A divergência está em `cross_catalog_unresolved`; esses códigos são recusados por ausência no domínio, sem substituição por códigos parecidos.

Fontes: [COSIF BCB](https://www3.bcb.gov.br/aplica/cosif/completo), [AnexoI1.3.0](https://www.cgibs.gov.br/upload/arquivos/202610/03141315-03-leiautes-da-dere-anexo-i-tabelas-v-1-3-0.pdf). O AnexoII preservado acompanha o pacote oficial observado em05/10/2026. As páginas selecionadas foram renderizadas e conferidas. O bundle agora inclui o PDF COSIF entre **42 fontes vinculadas por hash**.

Publicação e observação técnica são datas de conhecimento. As fontes observadas não fornecem, por si, vigência fiscal histórica de cada conta e sua aplicação à instituição. Por isso os catálogos usam `fiscal_validity=NOT_PROVEN_BY_OBSERVED_DOCUMENT`, sem atribuir datas artificiais. A política exige prova independente de instituição e prova por conta do vínculo entre contribuinte, instituição, referência, código fiscal e naturezas. Cada prova tem intervalo, observação, identificador, qualificação e hash do conteúdo. Nos positivos essas provas são explicitamente **sintéticas**, incluindo o mapeamento do principal; não constituem homologação contábil ou jurídica.

## Contrato e integração

`DerePgccOfficialCatalogQualifier` confere os hashes canônicos dos dois catálogos contra a política capturada, a disponibilidade temporal dos documentos, presença dos códigos, natureza da referência e natureza do código fiscal. Recusa provas ausentes, adulteradas, futuras, vencidas ou de outro contribuinte. Uma prova não pode ter observação posterior à observação declarada do PGCC. O D1011 não pode ser gerado antes dessa observação.

A declaração original valida cada conta no seu intervalo integral, incluindo históricas inativas, além da hierarquia e dos desdobramentos mistos já implementados. Os auxiliares mensais são derivados das contas ativas no fim da competência. O modo legado dos bundles anteriores permanece disponível para seus replays. A adaptação temporal usa intervalos comprovados externamente; não transforma o catálogo atual em prova de vigência normativa histórica.

O candidato19 ativa `pgcc_qualification_required`, `pgcc_projection_required`, `historical_declaration_required` e `official_catalogs_required`. O fechamento compara fornecedor, competência, hash, frequência, conjunto analítico e mapeamento do livro com o PGCC. Os indicadores de auxiliares fornecidos na entrada devem coincidir com a derivação oficial; D1106/D2101 necessários continuam bloqueando o fechamento porque sua execução está fora deste recorte.

O código `411011201` de despesas de captação não pertence à lista obrigatória D1121. O cenário positivo19 mantém a reconciliação da dedução financeira, mas o comando não publica D1121 nessa competência: produz **D1001, D1011, D1101 e D1199**. A escolha do evento passa pela RN quando a política oficial está ativa. Os bundles17/18 preservam seu comportamento e XMLs. No novo fixture, receitas e despesas usam respectivamente `codNat=4` e `codNat=5`, e as contas do livro seguem a concatenação do PGCC.

O XSD D1011 continua `evtPGCC-v1_0_3.xsd` do pacote preservado. A comparação anterior com o piloto1.3.0 mostrou igualdade após normalização de quebras de linha. D1101/D1121/D1199 mantêm contratos e pacote completo observado1.2.0; não se afirma homologação mensal integral1.3.0. O comando padrão permanece17; o19 requer seleção explícita do manifesto. Sem assinatura, transmissão, recibo ou fechamento governamental.

## Validação e reprodução

Suíte completa Docker PHP8.5.10: **830 testes/2.700 asserções**, PHPStan completo sem erros e Pint356 arquivos aprovado. Trinta testes novos cobrem catálogo, hash, prova, tempo, natureza, história e auxiliares. Corpus documental **12/12**: positivo, instituição ausente, mapeamento ausente/futuro/vencido, referência ou código fiscal ausente, identidade divergente, auxiliares conflitantes, livro divergente, hierarquia inválida e calendário ausente.

Regressões19/18/17: corpus RTC15/15 e integrado3/3, oito replays herdados por candidato; replays dos recortes mensais18/17 preservados. Os quatro corpos XML19 passaram na conferência XSD com somente assinatura ausente. **1.771 artefatos históricos** mantiveram seus hashes. Gate19:42/42 fontes íntegras, elegível para validação local, `ready=false`,119 bloqueios.

Materializar: `python scripts/fork_phase4b_dere_pgcc_bundle.py`. Verificar: `python scripts/verify_phase4b_dere_pgcc_cut.py`. Auditar: `python scripts/audit_phase4b_dere_pgcc_cut.py`. Recursos e evidências diferentes dos existentes não são substituídos pelos scripts. Artefatos finais em `storage/app/phase4b-pgcc-20261005-19/`, incluindo `verification-report.json`, snapshot, relatório mensal, corpus, XMLs, renders e logs. Capturas intermediárias anteriores à vinculação final do COSIF foram preservadas em subdiretório separado.

## Aceitação frente ao planejamento inicial

| Critério | Entrega deste recorte | Limite |
|---|---|---|
|4B.7 — documentação|D1011 original integrado e quatro corpos XSD conferidos|Somente original local; sem recepção|
|4B.8 — bundle, corpus, hash, replay|Candidato19 e12 cenários documentais|Não encerra corpus de todas as famílias4B|
|Temporalidade e referencias|Domínio oficial, provas datadas por instituição/conta e histórico completo|Provas positivas sintéticas; sem vigência normativa universal COSIF|
|5.4 — financeiro|Componente PGCC conectado ao fechamento local|Perfil financeiro integrado completo continua pendente|
|6.5 — obrigações|Obrigatoriedade derivada da RN publicada|D1106/D2101 e governança integral continuam pendentes|

O objetivo deste recorte local está concluído. Não se declara conclusão da Fase4B nem das Fases5–7. Permanecem pendentes o saneamento das três divergências oficiais, prova real de aplicação COSIF/cadastro/calendário, planos referenciais adicionais, alterações/exclusões, D1106/D2101, bases negativas, reabertura e homologação integral.
