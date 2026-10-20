# Simples 78 — integração local aceita em desenvolvimento

## Estado em 15/10/2026

O qualificador H1 da CGSN194 foi ligado ao motor por política opt-in no bundle
`consumption-phase4b-2026.10.15-78-draft1.json`. O status anterior de staging
está preservado; agora há código, catálogo, seis fatos/perguntas e testes
permanentes no checkout. A integração H1 desta geração foi aceita tecnicamente
após suíte completa e auditoria final. Não representa aceitação do Simples inteiro.
Geração76 permanece preservada como base histórica (2.370/46.214).

Arquivo de preparação:
`storage/app/phase4b-simples78-integration-preparation-20261015/`.
Seis entradas da base76 foram arquivadas por hash antes da integração, incluindo
engine, seletor temporal e recursos originais. Os artefatos anteriores e suas
aceitações não foram reescritos. A preparação verifica também a integridade do
componente externo 48 casos/96 avaliações/240 assertions.

## Mudança funcional

O novo caminho só é selecionado quando a janela contém `qualification_policy`.
Janelas antigas seguem pelo código anterior. A política cobre janeiro–junho/2027
e separa a opção ordinária da opção no momento de inscrição no CNPJ. Regime,
rota, inscrição, início de atividade, protocolo, situação e observação da
situação precisam de fatos/evidências próprios. Não inferir inscrição CNPJ a
partir de início de atividade. SIMEI continua recusado para eleição regular.

O catálogo agora considera a disponibilidade da fonte da janela e a data de
confirmação da íntegra do ato. Isso impede usar a revisão nova retroativamente.
A linha do segundo semestre ficou idêntica por hash; catálogos e bundles
históricos permanecem intactos. As duas novas fontes JSON oficiais são
vinculadas por URL, identidade, publicação, observação e SHA-256.

Os seis fatos/perguntas novos são inscrição CNPJ, prova dessa inscrição,
contexto de início de atividade, rota da eleição, prova da rota e data de
observação do status. A qualificação é local: protocolo não é autenticado
remotamente e nenhuma assinatura ou transmissão governamental foi realizada.

## Evidência terminada

- Primeira regressão direcionada do runtime: 84 testes/407 assertions.
- Corte com integração ISS/RTC: 100 testes/550 assertions. Os cortes se
  sobrepõem; não somá-los como testes distintos.
- PHPStan geral sem erros; Pint geral em 518 arquivos.
- 15 cenários, 30 decisões nativas nos dois runners, 30 replays exatos e dois
  replays de gerações anteriores (exportação73 e comprador estrangeiro76).
- Nos seis cenários de eleição H1 qualificada, o runner integrado preserva
  ISS 20,00 no exemplo sintético. O cálculo RTC continua `ACTION_REQUIRED`
  por `RTC_RATE_NOT_PROVEN:CBS`; nenhuma alíquota CBS futura foi inventada.
- 55/55 fontes conferidas, elegibilidade apenas local, `ready=false` e 118
  bloqueios. A diferença para76 é exclusivamente a resolução da pendência
  de leitura da CGSN194 (`RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED`). Os
  demais bloqueios, inclusive schema restrito somente local, foram preservados.

Corpus aceito de forma direcionada:
`storage/app/phase4b-simples78-cli-v2-20261015/simples78-cli-corpus-validation.json`.
Pré-auditoria:
`simples78-integration-preflight.json`, vinculando runtime, gates, catálogo,
fatos/perguntas, fontes, cenários, snapshots e compatibilidade.

A primeira tentativa de captura foi preservada no diretório sem `v2`. O script
esperou indevidamente exit1 do comando integrado para uma decisão INVALID.
Esse comando retorna exit0 e status INVALID no JSON; o RTC parcial retorna
exit1. A recusa fiscal de MEI estava correta. A v2 confere comandos e hashes e
reutiliza os resultados capturados, sem reescrever ou repetir os já verificados.
O livro dessa reutilização é `reused-executions.json`.

## Suíte completa terminal e auditoria

Wrapper: `D:/ProjetoSaasExports/capture_simples78_full_suite.py`.
Diretório: `D:/ProjetoSaasExports/phase4b-simples78-full-suite-20261015/`.
Handle29779 terminou com exit0, início12:08:20Z e término12:35:47Z de15/10/2026.
Resultado observado: **2.440 testes/46.607 assertions**, duração nativa1621,07s.
Log SHA256 `d0d4e119e6e251c40d95080ba015819f314c5d60040e63f4dc4242a38eb9320b`.
Inventário de3.836 entradas intacto, sem adições, remoções ou alterações.
PHPStan geral, Pint518 arquivos e avaliação de produção foram novamente
executados após a suíte, todos exit0. Avaliador:55 fontes válidas,118 bloqueios,
elegibilidade local verdadeira e produção falsa. Comparação com76 removeu
exclusivamente `RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED`.

Aceitação exclusiva:
`storage/app/phase4b-simples78-development-20261015/simples78-development-validation.json`.
SHA256 `65d910b865820894059e06a7be57c3a84d10fbf80aad9f813b965629b5e0665f`.
Os12 arquivos foram copiados do D: por hash. `evidence-copy-receipt.json`
registra hashes e backup dos documentos antes desta atualização. Nenhuma
aceitação histórica foi reescrita. Auditor final:
`D:/ProjetoSaasExports/finish_simples78_development.py`.

Continuam abertos: alíquotas futuras sem fonte efetiva, emissão documental,
autenticidade de recibos, exportação integral/parcial, demais moedas, matriz
dos 80 requisitos e aceitação de produção. Não declarar o Simples inteiro ou
a Fase4B concluídos a partir deste corte.
