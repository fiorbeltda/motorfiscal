# Handoff do Agente B para o Agente A (03/11/2026)

O trabalho do Agente B está publicado no commit `74a4195` (sobre `6c0f81b`, do Agente A),
branch `codex/fiscal-engine-bootstrap`. Não é preciso aplicar patch: basta atualizar a branch.

Comece por:
1. `02-HANDOFFS-PARA-O-AGENTE-A.md` — H1 a H5, com prioridade, patch sugerido e testes.
2. `06-ESTADO-DOS-36-REQUISITOS.md` — estado individual dos itens do B.
3. `05-DEPENDENCIAS-HUMANAS-E-EXTERNAS.md` — o que depende de pessoa ou de fonte externa.

Especificações detalhadas:
- `B02-especificacao-integracao-para-A.md`
- `B11-B13-B14-especificacao-integracao-para-A.md`
- `../phase4b-b07-annex-standard-qualification-spec.md`

Pedido de relatório do A: `pedido-relatorio-integracao-A.md`.

Arquivos exclusivos do A (não editados pelo B): `app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php`
e `app/Modules/Fiscal/ConsumptionReform/TemporalRateCatalog.php`.

Testes para verificar o lado B (PHP 8.5):
- `vendor/bin/pest tests/Fiscal/Unit/*B0*Test.php tests/Fiscal/Unit/*B1*Test.php tests/Fiscal/Unit/*B20Test.php tests/Fiscal/Integration/AnnexStandardQualificationB07Test.php`
- Esperado antes dos patches do A: tudo aprovado, exceto os pendentes (skip) — `SyntheticRateAcceptanceB19Test` (H4) e os 320 casos B07 (H2).

Os 44 itens do A (4B.1–4B.4) estão na matriz como `PENDENTE_DE_INTEGRACAO_A`, porque o relatório do A ainda não foi recebido pelo B.
Matriz atual: `storage/app/phase4b-b22-development-20261103/requirements80-current-matrix.json`.
