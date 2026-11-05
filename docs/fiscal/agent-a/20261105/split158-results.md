# A158 — em desenvolvimento

Estado corrente: pausa solicitada pelo usuário após livro de conciliação, testes próprios,
oráculo e regressão dirigida aprovados. Os cortes anteriores
abaixo são históricos e permanecem vinculados aos seus próprios bytes.
O histórico aritmético abaixo
permanece válido para seu próprio corte, preservado integralmente no D:.

Primeiro corte verificável: aritmética padrão do Manual de Operações v1.0.1,
com seis arranjos, proporcionalidade apenas nos três iniciados pelo recebedor,
tributos separados, valores corrigidos/em aberto opcionais, racionais exatos e
truncamento final. CLI local `fiscal:rtc:assess-split-payment` com replay e recusas.
O status positivo é `LOCAL_SPLIT_ARITHMETIC_CALCULATED`, sem provar liquidação,
repasse, implantação, autenticação, crédito ou extinção.

- Testes unitários:27/62, aprovados no corte v2.
- Integração CLI:2/13, aprovados em execução separada.
- Compatibilidade dirigida com B23/H1–H5/A157:802/1825, aprovada;
  inclui os27 testes unitários acima. **Não somar contagens sobrepostas.**
- CLI nativa:45 casos executados duas vezes, saídas idênticas;
  90 comparativos independentes Python Decimal aprovados.
- PHPStan global nível8: aprovado no corte arithmetic-v2.
- Pint: quatro arquivos PHP aprovados em arithmetic-v2, integração em arithmetic-v3.
- Console em leitura:28 verificações aprovadas, endpoint HTTP200.
  Proposta de acompanhamento em `console-proposal-A158.json`; painel global é de B.
- A primeira execução unitária falhou por chamada incompatível com a biblioteca
  decimal instalada. Corrigida para `toScale`; logs de v1 preservados.

Evidências externas e cortes imutáveis:
`D:/ProjetoSaasExports/agent-a-A158-split-payment-20261105-v1`.
Recibo compacto: `validation-A158.json`. Fontes e interpretações:
`split158-source-review.md`. Não houve alteração em recursos de B, catálogos,
bundles aceitos ou no motor nesta etapa A158; a modificação local anterior no motor é A157.

**Estado dos requisitos:**4B.3.7 continua PARCIAL;4B.1.5,4B.3.10,4B.4.11 e4B.4.14
são interfaces em desenvolvimento, sem encerramento por este incremento. A revisão
dos44 IDs em A156 permanece como base; nenhum item foi excluído ou promovido a completo.
0/8 grupos formalmente encerrados; Fase4B em andamento; produção não liberada.
A regressão continua **PARCIAL**, sem reproduzir todos os corpora/famílias/períodos.

**Continuidade obrigatória:** seleção temporal de respostas da plataforma, parcelas e
vínculos a documento/débito; marcos financeiros, informes e repasse consolidado;
conciliação/saldo/excesso; modelo legal simplificado qualificado (distinto da fase1
técnica); integração gated no motor e testes finais. O objetivo original permanece
ativo; A158 ainda não é handoff final ao B. Sem commit/push nesta continuidade.

## Incremento transacional

`SplitTransaction158.php` e CLI `--mode=transaction` qualificam os participantes,
documento/parcela, revisão dos dados fiscais, histórico e seleção de retornos processados
até o congelamento do cálculo. Valida janela de correção em Brasília, monotonicidade
do valor em aberto, marcos financeiros por arranjo e liberação de impeditivos.
Antecipação e cancelamento não geram segregação. Cálculo a partir de liquidação
fornecida não se transforma em pagamento autenticado nem extinção contabilizada.

Neste corte:

- Testes próprios `transaction-unit-v2`:94 testes/231 asserções, aprovados, três arquivos.
- CLI transacional nativa:28 casos repetidos com bytes idênticos;66 comparativos
  independentes Decimal e conferência de instantes/seleção temporal aprovados.
- CLI aritmética nativa v2:45 casos repetidos;90 comparativos Decimal aprovados.
  O v1 permanece preservado e não comprova a nova guarda de soma dos tributos informados.
- PHPStan global nível8 `phpstan-transaction-v1`: aprovado.
- Pint nos sete arquivos PHP A158 `pint-transaction-v2`: aprovado.
- Regressão dirigida `compatibility-transaction-v1`:869 testes/1994 asserções,
  zero falhas/erros/skips. Inclui os94 testes próprios; não somar execuções.
  Seleção de sete arquivos: B23, H1–H5, A157 e A158; continua **PARCIAL**.
- Console em leitura:28 verificações aprovadas e HTTP200. Seu estado observado
  continua B23; a atualização A158 é proposta a B, não alteração da consolidação.

Nova guarda aritmética: soma IBS+CBS informados não supera valor original, conforme
§7.1 do manual. Fixtures inválidas do corte anterior foram corrigidas só no novo corte.
`transaction-unit-v1` foi uma execução positiva anterior às últimas provas de
cancelamento/identidades; a referência atual é v2. Não somar execuções sobrepostas.

O contrato atual cobre uma transação e uma revisão estável dos dados fiscais.
Não comprova o cronograma inteiro de parcelas, reset/atualização de dados, repasse,
saldo/extinção/excesso, prazo com calendário, simplificado ou integração do motor.
Esses pontos continuam trabalho obrigatório do A; somente ativação de novas versões
de fatos/perguntas/catálogos e consolidação da matriz80/console são de B.

Novo checkpoint externo: `transaction-checkpoint-v1.json`, com fontes próprias
preservadas em `transaction-source-cut-v1/`, incluindo o motor A157 usado na
regressão. `validation-A158.json` aponta ao novo corte. O recibo compacto anterior
também foi preservado em `arithmetic-source-cut-v1/` antes da atualização do índice.
As classificações dos44 IDs da revisão A156 não foram promovidas por este avanço.

## Livro de conciliação — corte corrente

`SplitLedger158.php` e CLI `--mode=ledger` conferem conjunto de parcelas, principal e
débitos; informes e repasse consolidado; aplicações ao débito, excesso determinado e
transferências documentadas. O lote pode incluir outros documentos; seu total nunca
é apropriado automaticamente à operação. Recibo sem registro de aplicação deixa saldo.
Falta/sobra do lote são divergências explícitas, sem crédito/refund presumido.

Proporcionalidade não representável em centavos pode ser conciliada como registro
fornecido que fecha o débito, com achados racionais por parcela e estado NÃO COMPROVADA.
Nenhuma distribuição de residual é escolhida pelo programa. Essa regra de engenharia
preserva a evidência; não estabelece critério fiscal nem permite decisão oficial.

- `ledger-own-v2`:153 testes/397 asserções aprovados, quatro arquivos próprios.
- CLI `ledger-native-v2`:13 casos repetidos e comparativos independentes Decimal/Fraction,
  incluindo residual fornecido, lote com outros documentos, falta/sobra, parcela pendente,
  compensação documentada e escalas pequenas/grandes. Recibo externo contém as contagens.
- PHPStan global nível8 `phpstan-ledger-v2`: aprovado. Pint nove arquivos em `pint-ledger-v5`: aprovado.
- Primeiro corte `compatibility-ledger-v1`:927/2151 aprovado, **anterior** ao refinamento
  de proporcionalidade. Fontes e oráculo v1 preservados em `ledger-draft-source-cut-v1/`.
  A regressão do corte corrente é `compatibility-ledger-v2`; não somar suas contagens.
- Corte corrente `compatibility-ledger-v2`:928 testes/2160 asserções, zero falhas/erros/skips;
  inclui os153 testes próprios. Regressão **PARCIAL**, oito arquivos, sem afirmar suíte integral.
- `ledger-native-v2`:13 casos/478 comparativos monetários Decimal, além de conferências
  racionais Fraction; repetições idênticas. PHPStan global nível8 e Pint nove arquivos aprovados.
- Console em leitura:28 verificações aprovadas e HTTP200. Atualização global apenas proposta a B.

Continua **PARCIAL**: não houve fechamento dos44 itens ou de grupos4B. Limites e
próximas implementações estão na seção do livro em `split158-source-review.md`.
O motor permanece com o incremento A157 anterior; A158 ainda não foi encaixada no
fluxo instalado. Recursos de B e todos os bundles/catálogos aceitos permanecem intactos.

Checkpoint final deste incremento em `ledger-checkpoint-v1.json` e fontes próprias
preservadas em `ledger-source-cut-v1/` no diretório externo. `validation-A158.json`
é o índice compacto atualizado, com recibos anteriores preservados. Pausa e próximo
trabalho em `resume-after-A158-pause.md`. Usuário autorizou commit do conjunto A157/A158;
esta autorização é posterior às etapas históricas acima realizadas sem commit.
