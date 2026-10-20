# Identidade104 - integracao versionada em verificacao

Bundle `CONSUMPTION-PHASE4B-2026.10.19-104-draft1`,derivado do101.
Nao e aceitacao fiscal nova.101 permanece ultimo aceite local;seus hashes
descrevem o runtime anterior,arquivado antes das alteracoes104.

## O que mudou

Componente103 auditado instalado com alteracao exclusiva do namespace.
Politica de fontes historicas102/103 preserva capturas em16/10/2026,
publicacao/vigencia material nao demonstradas e implantacao remota falsa.
Cinco entradas oficiais adicionais com copias byte-identicas e hashes:
manual municipal,manual contribuinte,dois anexos distintos e ZIP restrito.
O anexo de producao e somente comparativo,nao habilita emissao.

Dois fatos/perguntas novos: `rtc.assess_export_dps_identity` e
`rtc.export_dps_identity_records`. Opt-in exige as duas avaliacoesISS
nacional96 e municipal98. O componente so e chamado apos os gates atuais
de incidencia,cambio/alocacao,classificacao,aliquotas e os dois resultadosISS.
Nao executa snapshots historicos recursivamente.

IDs compostos sao mapeados aos papeis e referencias originais;provas antigas
nao sao renomeadas. Vinculo local positivo ainda termina em
`ACTION_REQUIRED / RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`.
Identidade fiscal integral,cadastro/autorizacao,unicidade remota,data material,
conversao/bases/imposto por documento,XML,lancamento e producao continuam
nao qualificados. Assessor104 inclui bloqueio proprio de mapeamento local.

## Checks dirigidos terminais

3.378 testes/42.846 verificacoes,exit0;PHPStan geral e Pint562 aprovados.
233 testes novos:129 do componente,14 gates do engine e90 casos dos dois
executores. Corpus104 tem45 cenarios:15 casos de identidade e30 rotas de
exportacao101 com fatos/provas anteriores preservados,acrescidos somente dos
dois fatos104. Inclui USD/BRL,percentuais,resultado e recusas anteriores.
Relogio de conhecimento nao e recuado;provas novas sao mapeamentos sinteticos.
Caminhos positivos upstream comparam o calculo nominal com controle101.

Nenhuma aliquota,transicao,regra decisoria,correlacao,cIndOp ou tabela
cClassTrib anterior alterada. Facts/perguntas101 preservados como prefixo.
O inventario pre-integracao verificou9.272 entradas/78 hashes101 e arquivou
engine/assessor antes das duas mudancas autorizadas.

## Evidencias e gates restantes

`D:/ProjetoSaasExports/phase4b-identity104-integration-20261019/`:
`before-integration-archive.json`,`identity104-bundle-installation.json`,
`identity104-case-manifest-v2.json`,`directed-v2.json`,`phpstan-v2.json`,
`pint-v2.json` e logs imutaveis. Auditoria dirigida terminal aprovada:
`identity104-directed-validation.json`,9.270 entradas101 intactas e somente
duas alteracoes runtime autorizadas,com predecessores arquivados.
O primeiro coletorCLI exigia indevidamente exit0 para RTC INVALID;comando
oficial local retorna exit1 para essa decisao. Fonte/logs e revisao da falha
preservados. ColetorV2 confere exit1 somente nesse caso;demais comandos e
replays continuam exigindo exit0. Nenhum caso ou codigo do motor alterado.
CorpusCLI V2 terminal aprovado em19/10/2026:45 cenarios/90 decisoes e
replays proprios,337 historicos exatos,71 fontes validas e123 bloqueios
de producao. Relatorio `cli-v2/identity104-cli-validation.json`.
Confronto adicional de52 memorias nominais com snapshots101 capturados
permaneceu identico. O aceite local104 foi posteriormente publicado,conforme
o encerramento terminal abaixo.

Os gates de regressao e auditoria foram concluidos no encerramento abaixo.
Evidencias volumosas no D:. Todos os oito grupos4B continuam parciais;producao bloqueada.

## Preflight e capacidade do C:

Primeira tentativa recusada antes de qualquer teste/diretorio de regressao:
C: abaixo de256MiB. `regression-first-space-refusal.json` preserva a recusa.
O arquivo de paginacao do Windows atingiu6.381.668.352 bytes;nao foi apagado
ou reconfigurado. Somente temporarios/caches recuperaveis foram removidos.
Recibos no D: `cleanup-c-docker-update-20261019-v1/cleanup-report.json` e
`cleanup-c-download-caches-20261019-v1/cleanup-report.json`.
91 arquivos/401.603.065 bytes logicos adicionais removidos com copiasSHA256;
isso nao equivale ao ganho livre,afetado pelo sistema e pela alocacao fisica.
C:297.324.544 bytes livres ao fim da segunda limpeza,com pouca folga.

Novo preflight104 concluido:logs,codigos de saida esperados,snapshots,fontes,
historicos e predecessores conferidos;11.133 entradas congeladas e79 hashes
runtime. Piso de espaco reconferido antes do inicio. Regressao completa104
iniciada uma unica vez e confirmada ativa no conteiner em19/10/2026.
Naquele preflight,a suite esperada era6.529 testes;resultado terminal abaixo.
Nao houve reinicio por timeout. Diretorio exclusivo:
`D:/ProjetoSaasExports/phase4b-identity104-full-suite-20261019/`,com
`preflight.json`,`process-start.json` e `full-suite.log` terminal preservados.

Coleta inicial105 de cinco fontesHTML feita separadamente no D:,sem politica,
componente ou regra nova;ver `phase4b-iss-date105-primary-intake.md`.

## Encerramento104 aceito localmente

Regressao terminal:6.529 testes/98.175 assertions,exit0,em19/10/2026.
11.133 entradas sem alteracao,adicao ou remocao;79 hashes runtime conferidos.
PHPStan geral,Pint562 final e assessor71 fontes/123 bloqueios aprovados.
Auditoria independente confrontou corpus90/337,52 memorias nominais101,
fontes,politica,dois fatos/perguntas,originais arquivados e todos os inputs.
Aceitacao publicada em:
`storage/app/phase4b-identity104-development-20261019/identity104-development-validation.json`.
SHA256:`8f3d57986e7fea2f87ca317914bc1ecac90e7cfd719607839e88fa5128262bd1`.
Relatorio e log do assessor copiados do D: ao C: e hashes das duas copias
conferidos em `evidence-copy-receipt.json`. Aceite101 permanece imutavel.

7/7 marcos,100% apenas desta integracao delimitada. Identidade fiscal integral,
data materialISS,conversao/base/imposto por documento,XML e obrigacao completa
nao estao comprovados. Producao bloqueada.105 tem componente isolado em
verificacao,nao integrado ao corte104.
