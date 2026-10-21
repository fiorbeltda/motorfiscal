# Créditos122 — perguntas de correção

Correção instalada e validada em197 testes/698 verificações apósPint;
PHPStan geral sem erros ePint geral605 aprovados. Auditoria independente
conferiu4974 arquivos:exatamente dois arquivos de código e dois de teste
mudaram;nenhum recurso fiscal,parâmetro ou fonte oficial foi alterado.

Evidência:
`D:/ProjetoSaasExports/phase4b-credit122-preparation-20261021/question-fix-v2/question-fix-directed-validation.json`.

As quatro recusas originalmente sem perguntas permanecem em`question-gap-v1`,
com snapshots eSHA anteriores. Código e testes pré-correção foram preservados
em`question-fix-v2/before/`,antes de qualquer edição.

Linhas sem evidência,ambíguas,malformadas ou com imposto desconhecido agora
apontam para a pergunta já existente de`rtc.credit_adjustments`;o índice exato
da linha permanece no rastreio. Valores negativos/excessivos recusados pelo
componente aritmético também indicam os fatos corrigíveis,somente no caminho
122,com estágio`CREDIT_CORRECTION122`. Fatos já solicitados são preservados;
razões de parâmetro/obrigação indisponível não recebem perguntas inventadas.

Não muda elegibilidade,valor concedido,autenticação ou prova de extinção.
O componente aritmético anterior não foi modificado;os caminhos119 mantêm
seus testemunhos e rastreios anteriores,sem aplicar a correção retroativamente.

A primeira execução do auditor dirigiu-se aos marcadores textuais sem remover
coresANSI e foi recusada. Código/falha preservados;v2 confere oSHA dos logs
brutos antes de remover somente sequênciasANSI de apresentação. Os testes
não foram alterados para acomodar essa falha de leitura do auditor.

Novo corpus nativo terminal:90 cenários,180 decisões e180 replays próprios,
108 checks de perguntas e84 conferênciasDecimal,com entradas congeladas intactas.
Inclui entradas negativas domésticas e de importação. Captura concluída
noD:,com worker lançado pelo caminho absoluto doPowerShell viaWMI. O primeiro
lançamento com nome curto não iniciou processo e ficou registrado como falha.
Comparação independente dos176 resultados anteriores:156 inteiros idênticos e
20 com apenas perguntas/fatos solicitados e rastreio explicitamente permitidos
alterados. Todas as entradas,decisões,status e razões anteriores permaneceram
iguais. Não houve exclusão genérica de rastreio ou mudança de versão no comparador.
Recibos:`credit-cli-v2/credit122-cli-validation.json` e
`question-fix-v2/question-result-comparison.json` sob o diretório122 noD:.

O corpusv1 de176 decisões/replays e os1441 históricos descrevem o runtime
ANTERIOR à correção. Não reescrever seus recibos ou declarar que já validaram
esta alteração. Preços ampliados,regressão integral e aceite122 continuam
pendentes.119 segue como último aceite integral;58 IDs examinados,22 ainda
por revisar,nenhum grupo4B formalmente encerrado e produção bloqueada.
