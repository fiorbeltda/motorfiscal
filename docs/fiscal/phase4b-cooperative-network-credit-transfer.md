# Cooperativas: operações entre sociedades e créditos do associado

Bundle candidato `CONSUMPTION-PHASE4B-2026.10.02-10`. Amplia o contrato anterior de serviços não financeiros com operações entre cooperativas singulares, centrais, federações e confederações e operações originárias de banco cooperativo para cooperativa participante, LC214 art.271 §1 I. Exige provas de cadastro, participação/origem, opção eficaz, período e vínculo com partes/documento. Não qualifica serviços financeiros por esta ampliação.

## Transferência documentada do art.272

`rtc.assess_cooperative_credit_transfer=true` solicita a avaliação. O array singleton `rtc.cooperative_credit_transfer` identifica associado fornecedor regular, cooperativa adquirente, documento e observação. O fornecimento deve ter sido qualificado pelo art.271 I. Não se aplica automaticamente às outras rotas.

Exige conjunto completo de créditos previamente apropriados e qualificados, líquidos de anulações, sem transferência já registrada e com evidência de validação regulamentar. Cada entrada identifica tributo, titular, aquisição, data original, vínculo produtivo com este fornecimento e prova de saldo disponível. Crédito ordinário exige prova de extinção; presumido exige prova da regra e apropriação já qualificada. Este contrato não concede elegibilidade a um crédito presumido.

Saldo disponível = apropriado − utilizado/transferido/restituído − anulado/reservado para ressarcimento. A proposta não pode superar esse saldo. IDs e documento/componente duplicados, titular ou documento conflitante, datas futuras e créditos sem vínculo produtivo invalidam o pedido. Provas ausentes exigem ação. A expiração continua sendo cinco anos desde o primeiro dia do mês posterior à apropriação original, art.54; a proposta não reinicia o prazo. Uma aquisição/componente deve ser apresentada como entrada reconciliada única.

Exemplo: IBS apropriado100, usado10, indisponível20 resulta em disponível70; pedido50 deixa20. CBS900−100−200 resulta em600; pedido500 deixa100. Apropriação10/09/2026 expira01/10/2031. `transfer_performed=false` e `posting_performed=false`: a memória calcula elegibilidade e valores propostos; a efetivação depende de fluxo próprio e validação regulamentar formal.

## Integração e documentos

Base da operação1000 preservada, CST200/classe200020 e IBS/CBS nominais0. ISS fixture50 é qualificado independentemente. Os seis positivos deste corte usam4A `NOT_REQUESTED`; o caso real de avaliação4A do associado pessoa física do predecessor9 foi novamente verificado, sem mudar o hash. Nenhuma destas provas fixture é aprovação jurídica ou ato regulamentar real.

O contrato DPS permanece `DPS-LOCAL-2026.10.02-1`; a proposta de transferência fica na memória fiscal, sem criar elemento XML não previsto. Quatro candidatos XML resultam em `ACTION_REQUIRED`, exclusivamente pelo conflito de série do XSD oficial preservado. Não são XMLs homologados.

## Fonte e evidências

Fonte primária preservada: [LC214 consolidada, arts.271,272 e54](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. Nenhuma nova fonte foi presumida. Materializador `fork_phase4b_cooperative_network_transfer_bundle.py`; verificador `verify_phase4b_cooperative_network_transfer_cut.py`.

Validação: 21/21 cenários integrados (6 positivos,15 negativos), quatro snapshots com replay idêntico e quatro XMLs candidatos. Testes dirigidos novos22/75 e regressão cooperativa16/60, total38/135; incluem a fronteira de expiração. PHPStan sem erros; Pint sete arquivos e `git diff --check` sem erros. Corpus predecessor9 novamente16/16 e mensal7 novamente20/20, seus seis hashes preservados. O comando padrão com alvo local também passou, mantendo produção bloqueada. Última suíte completa385/1554 é histórica do7; não foi repetida neste corte.

| Snapshot | Hash da decisão |
|---|---|
| Singular para central | `ff65b9dc4e066c59705b6679441d0c0c4ad96b711ec8e7f8c795a0c4ace30aa8` |
| Banco para participante | `767f715d1d6c50f18f3113c8e01348648fdb401b9c970fca8a14ac09d8cdbcf9` |
| Transferência parcial | `5145ce469b6c8eacfeaa71b377be370c2dd99d62abed7e7e6ee6b39dd50fc22a` |
| Transferência integral | `b838cb1a6178f81d79a0fe1c3104330ee2fc23d6099461da42cf99f1e9d83f2b` |

Artefatos `storage/app/phase4b-cooperative-network-transfer-*-20261002-10-*`; corpus `phase4b-cooperative-network-transfer-corpus-20261002-10.json`; gate `phase4b-production-assessment-20261002-10.json`:20/20 fontes, elegibilidade local verdadeira, `ready=false`,119 bloqueios. Os12 controles continuam parciais/abertos e as98 regras interpretativas aguardam revisão. Financeiro, agropecuário, opções fora do recorte existente e outros requisitos da auditoria continuam pendentes. A Fase4B permanece em desenvolvimento.
