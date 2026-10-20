# Catálogo101 — referência1.70 integrada e aceita localmente

O candidato `CONSUMPTION-PHASE4B-2026.10.16-101-draft1` incorpora as173
classes/18CST da preparação99 e o verificador100. Aceitação local101 publicada:
6.296 testes/94.755 verificações,exit0;9.272 entradas intactas,78 hashes e duas
cópias conferidos. PHPStan/Pint558 finais e auditoria independente aprovados.
98 e predecessores preservados. Não encerra a Fase4B ou libera produção.

Aceitação:`storage/app/phase4b-catalog101-development-20261016/catalog101-development-validation.json`.
SHA256:`900bd64cfa7a0eb7775974f936a9d12369929b12028c59fe50e3932a2ed11360`.
Regressão começou18:02:35UTC e o relatório terminal foi registrado18:35:34UTC;
tempoPest1.918,68s. Sem alteração,adição ou remoção de entradas congeladas.

O motor consulta a disponibilidade da referênciaNFSE depois da seleção de uma
regra decisória,antes do cálculo,nas operações padrão e na parcela doméstica
da exportação parcial. Trace `CCLASSTRIB_REFERENCE` registra o contexto.
Política ausente/adulterada,origem diferente,tabela/CST modificados,conhecimento
anterior à observação e modelo não permitido recusam a referência. A remoção
isolada da política não desliga o gate no catálogo1.70. Bundles históricos
sem os marcadores novos não recebem esse estágio retroativamente.

A referência disponível não é autoridade de decisão fiscal. Alíquotas,
transição,regras decisórias e correlações anteriores foram preservadas. Nenhuma
regra nova para autônomos ou doação foi deduzida dos nove códigos adicionais
ou de `tpDoacao`. Não há emissão de indicadores,XML,lançamento ou produção.

Bundle101 acrescenta quatro fontes preservadas:planilha1.70,informe técnico1.70
e os dois índices oficiais observados. Publicação anunciada não prova implantação
remota nem a identidade do conteúdo em instante anterior à captura. Os fatos e
perguntas têm arquivos próprios de versão,mas conteúdo idêntico98.

## Evidências verificadas neste corte

- 2.981 testes permanentes do verificador passaram na primeira execução;
  a execução combinada também revelou57 falhas no preparo dos cenários.
- 20 testes do motor/assessor passaram após corrigir caminhos do ambiente de
  testes:109 verificações,66 fontes válidas e122 bloqueios de produção.
- 122 testes de integração corrigidos passaram:1.396 verificações,comparando
  status e motivos98/101 com os mesmos fatos e instante de conhecimento.
- PHPStan geral inicial aprovado;estilo de dois testes corrigido com cópias
  anteriores preservadas. Checks finais:3.186 testes/40.482 verificações dirigidas,
  PHPStan geral e Pint558 aprovados. CorpusCLI terminal:61 cenários/122 decisões
  e replays próprios,215 históricos exatos,66 fontes válidas e122 bloqueios.

Esses conjuntos dirigidos não substituem a regressão integral,agora encerrada
e auditada nos números acima. Primeiras falhas e tentativas permanecem no D:.

## Correção da preparação,sem enfraquecer os gates

A primeira cópia de61 cenários usou o instante atual indiscriminadamente.
Fixtures de antecipação têm provas sintéticas observadas em datas futuras,
posteriores a16/10/2026. Recuar seu conhecimento provocou corretamente recusas
de prova temporal. A versão2 usa o maior entre o instante original e a captura;
os fatos,provas e snapshots internos são idênticos aos originais. Não se atribui
autenticidade atual a provas futuras sintéticas.

O casoSaaS no runner integrado já retornava `VALID_WITH_WARNINGS` no98,
enquanto o runnerRTC retorna `VALID`. Os testes agora verificam explicitamente
essa diferença e comparam com98,não exigem um status integrado inventado.

## Próximos gates

CorpusCLI,regressão,checks finais e auditoria concluídos.9.272 entradas
intactas e78 hashes conferidos. Não reiniciar a execução terminal nem rerodar
auditores exclusivos. A primeira checagem de espaço
recusou antes de iniciar testes ou criar o diretório exclusivo. Nova conferência
permitiu disparar o preflight;não é reinício de suíte viva.
Depois continuar identidade fiscal,data material,conversão/base por documento e
projeção documental parcial conforme as fontes/contratos adequados. Auditoria dos
80 requisitos e oito grupos4B permanece pendente;produção bloqueada.

Evidências exclusivas:
`D:/ProjetoSaasExports/phase4b-catalog101-integration-20261016`.
Preparações99/100 e aceite98 permanecem imutáveis.

## Execução da regressão e auditoria

Scripts preparados,sem aceitação implícita por sua existência:
`scripts/capture_phase4b_catalog101_full_suite.py` e
`scripts/finish_phase4b_catalog101_development.py`.
O primeiro exige corpusCLI terminal e gates aprovados antes de criar
`D:/ProjetoSaasExports/phase4b-catalog101-full-suite-20261016`.
Confere as entradas98 e permite somente as duas mudanças antigas autorizadas
(engine e assessor),incluindo arquivos novos no congelamento101. Runtime esperado:
78 hashes. Contagem esperada de testes:6.296=3.173 anteriores+3.123 novos;
resultado real e verificações devem vir do log terminal,não dessa previsão.

A auditoria independente exige regressão terminal,logs com hashes,entradas
intactas,corpus próprio e215 históricos,política/tabelas iguais à preparação100,
componente instalado idêntico salvo namespace,regras/correlações/alíquotas
preservadas e produção bloqueada. Somente depois publica duas cópias pequenas
conferidas em `storage/app/phase4b-catalog101-development-20261016`;evidências
volumosas permanecem no D:. Não executar novamente tentativas exclusivas.
