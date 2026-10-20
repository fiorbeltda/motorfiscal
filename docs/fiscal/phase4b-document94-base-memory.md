# Documental94 — comparação da memória declarada da base

## Estado em 16/10/2026

Este registro descreve a aceitação94 preservada. A integração posterior96 também foi aceita em desenvolvimento local; consultar `phase4b-export-iss96-live-integration.md` para o estado atual e seus limites.

Utilitário aceito em desenvolvimento local após regressão completa terminal:2.793 testes/49.475 verificações,exit0. As5.360 entradas congeladas permaneceram intactas; PHPStan e Pint544 repetidos após a suíte e aprovados. O engine e o bundle91 não foram alterados. Não há novo opt-in do motor nem geração do par documental de exportação.

Componente: `app/Modules/Fiscal/NFSe/DocumentBaseReconciliation.php`.
Testes: `tests/Fiscal/Unit/DocumentBaseReconciliation94Test.php` e fixture própria `tests/Fixtures/document-base94-cases.json`.

## Evidência observada

- Preparação nativa:56 casos,596 verificações e12 resultados independentes com Python Decimal. Auditoria refez o PHP nativo, PHPStan e Pint dos arquivos arquivados; vinculou por SHA256 os arquivos executados no contêiner às cópias finais no D:.
- Antes da instalação:5.353 entradas congeladas91 e71 hashes runtime anteriores conferidos sem alteração, inclusive o inventário de arquivos, preservando a aceitação91.
- Testes permanentes:58 testes/686 verificações, incluindo imutabilidade das entradas, hashes independentes de memória/política e limites de qualificação. PHPStan geral e Pint544 aprovados antes da regressão.
- Regressão terminal observada:2.793 testes/49.475 verificações; duração nativa1715,50s, host1743,88s. Sem entradas adicionadas, removidas ou alteradas. PHPStan e Pint544 finais aprovados; avaliação de produção91 mantém59 fontes válidas e119 bloqueios.
- Auditoria independente final vinculou a suíte, logs, fontes e os72 hashes monitorados (71 anteriores mais o comparador NFS-e). Duas cópias pequenas foram conferidas no C: para o console; logs e inventário volumosos ficaram no D:. A aceitação91 e seu corpus histórico não foram reescritos nem apresentados como novas execuções94.

Evidências em `D:/ProjetoSaasExports/phase4b-document94-base-reconciliation-20261016/`:
`document94-preparation-validation.json`, `exact-source-cells.json`, `cases.json`, logs `audit94-*` e `integrated94-*`.
Regressão/integração: `D:/ProjetoSaasExports/phase4b-document94-component-integration-20261016/`.
Aceitação local: `storage/app/phase4b-document94-development-20261016/document94-development-validation.json`.
SHA256:`ac7e8926f3d2ec9c4c93b975f63a240186b57879fca53bc7261591fd3b1d69ce`.
O bundle fiscal continua91. O corpus CLI novo94 é o diagnóstico abaixo:seis cenários,12 decisões e12 replays,com a obrigação documental ainda bloqueada; não substitui o corpus histórico60/53 da integração91.

## Contrato e limites

Contrato explícito `NT009-LOCAL-DOCUMENT-BASE-2026.10.16-94`, com hashes da NT009v1.01 e AnexoVI1.04.01 observados em16/10. A observação é apenas conhecimento local: **não demonstra vigência ou implantação ambiental**. Nenhuma fonte nova foi ativada no bundle91.

A comparação reproduz a memória declarada de H53 do leiaute: parte de vServ, desconto incondicionado, uma das duas deduções alternativas, ISS e ajuste de comércio exterior. PIS/Cofins são subtraídos até2026; a memória2027–2032 os exclui. Não extrapola para2033.

Valores são strings decimais canônicas; floats, expoentes, negativos fora do campo permitido, base negativa e estouro são recusados. Omissão explícita (`null`) dos três ajustes opcionais não se confunde com dado ausente. A presença do ajuste de comércio exterior, mesmo zero, é recusada na condição de D601. O código oficial do anexo continua `EXXX`; o motivo94 é interno, não uma rejeição oficial inventada. Regras riscadas D602/D603 não foram ativadas.

Um resultado `LOCAL_DOCUMENT_BASE_MATCH` compara apenas a memória fornecida. Não qualifica materialmente ISS/RTC, não recalcula impostos, não prova documento, não gera campo do emissor ou XML, não valida XSD, não autoriza lançamento e não libera produção. Casos sintéticos exercitam a aritmética; não são exemplos de documento legalmente validado.

## Próximo escopo necessário

Vincular memórias independentes às decisões/snapshots imutáveis e às parcelas de exportação73; qualificar conversão documental de ISS sem deduzi-la do câmbio RTC; estruturar notas estrangeira e doméstica distintas com indicadores de omissão corretos. Selecionar contrato/schema comprovado por ambiente e data antes de afirmar validação documental. Manter a lacunaNT009 dos fechamentosXSD observados em92 e a obrigação documental pendentes enquanto não provadas.

Fases3/4A continuam encerradas funcionalmente em Sandbox; todos os oito grupos4B permanecem parciais, sem percentual funcional global auditado. Fases5–7 planejadas; produção bloqueada.

## Ponte ISS/RTC: lacuna reproduzida nos dois runners

Os seis casos nominais73 foram executados novamente nos runners RTC e integrado, com12 decisões e12 replays nativos exatos e snapshots próprios no D:. Os cenários originais, bundle e snapshots históricos permaneceram intactos. As alocações RTC dos dois runners coincidem e conservam o total.

No caso USD direto, o trace ISS registra base integral de5.179,50 e imposto103,59. O trace RTC registra parcela estrangeira2.071,80 e doméstica3.107,70. O resultado integrado permanece `ACTION_REQUIRED` e não entrega decisão/documento final. Esses dados não demonstram duas memórias ISS documentais independentes. Não foi inferido ISS zero para a exportação nem rateado103,59 pelo percentual RTC.

Evidência: `D:/ProjetoSaasExports/phase4b-document94-pair-binding-gap-20261016/document94-native-pair-binding-gap.json`. Não acrescenta12 testes Pest: são12 execuções CLI e12 replays, separados da regressão.

Fontes primárias adicionais foram capturadas integralmente, com SHA256 e artigos selecionados, sem ativação de regras: [LC116, arts.2º e7º](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm) e [CTN, art.143](https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm). A primeira exige tratar o resultado do serviço e a base ISS; a segunda ressalva disposição legal contrária na conversão cambial. A inferência de implementação é exigir qualificação própria de resultado e conversão ISS, sem transportar automaticamente a seleção cambial RTC. Não se considera provada a aplicabilidade municipal, histórica ou transacional apenas pela captura atual.

Fontes: `D:/ProjetoSaasExports/phase4b-document94-iss-bridge-sources-20261016/iss-bridge-primary-observation.json`. Próxima implementação deve ligar documentos, resultado ISS, moeda/conversão e memórias correspondentes, mantendo distintas as bases e evidências de cada imposto.
