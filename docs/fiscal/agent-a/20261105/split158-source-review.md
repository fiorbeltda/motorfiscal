# A158 — revisão de fontes e fronteiras, 05/11/2026

O escopo original 4B.3.7 permanece aberto: padrão/simplificado, parcelas, vínculo a
operação/débito, liquidação/conciliação e implantação/parâmetros qualificados. O primeiro
incremento abaixo implementa a aritmética técnica padrão; não encerra esse requisito.

## Fontes primárias preservadas

- [Página CGIBS](https://cgibs.gov.br/split-payment): snapshot bruto e recibo em
  `D:/ProjetoSaasExports/agent-a-A158-split-payment-20261105-v1/sources/`.
- [Manual de Operações v1.0.1, ZIP oficial](https://cgibs.gov.br/upload/arquivos/202609/24145322-split-payment-manual-de-operacoes-v1-0-1.zip).
  SHA256 ZIP `9c4e0473efd00bb7a99c2250405f1b4b6f9cebc8b3e52d87553e9c677c55f25d`;
  PDF `f5b03d9484fbbe98edec24cd0531d49fb08be6652026c311a051f78b890ec24a`.
  Captura em 05/11/2026; bytes e horário exato em `sources/manuals-receipt.json`.
  PDF 53 páginas, texto extraído preservado; página24 renderizada e inspecionada.
- Manuais de Integração v1.1.1, Tempos v1.0.1 e OpenAPI v1.1.1 baixados da mesma
  página oficial, com hashes individuais no mesmo recibo. Ainda não são prova de
  implementação completa desses contratos/tempos; leitura detalhada continua pendente.
- [LC214 consolidada](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html),
  arts31–35, snapshot aceito A156/A157 SHA256
  `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.
- [Comunicado RFB, 30/09/2026](https://www.gov.br/receitafederal/pt-br/assuntos/noticias/2026/setembro/comunicado-5):
  testes de integração em ambiente controlado. Reconsultado nesta retomada. Não serve
  como ato de implantação definitiva nem autoriza decisões fiscais oficiais pelo motor.

## Decisões de implementação e trabalho restante

O Manual §§5.1–5.4 é a referência do cálculo. Aritmética usa racionais exatos,
aplica os limites antes de truncar centavos e calcula os dois tributos separadamente.
Valores corrigidos e em aberto iguais a zero são informação presente; não são tratados
como ausência. O contrato distingue os seis arranjos e seus iniciadores. O valor original
é da transação, sem substituir pelo valor total da operação ou do financiamento.

Não há compensação cruzada. O cálculo não valida a origem/autenticidade de mensagens
da plataforma nem prova que a versão usada era a processável no instante da liquidação.
Essas verificações pertencem ao ciclo ainda a desenvolver, não ao resultado aritmético.
Retornos em aberto evoluem apenas para menor (§4.2.1); correção pode aumentar dentro
da janela específica. Não implementar uma proibição genérica de toda correção a maior.

As páginas5–6 limitam a primeira fase técnica a B2B opcional e excluem o simplificado.
Isso não elimina a obrigação de desenvolver o modelo legal parametrizado do art33:
será um contrato distinto, com qualificação do percentual/ato e sem simular implantação.
O caminho aritmético da fase1 recusa o procedimento simplificado expressamente.

Páginas25–26 distinguem marco financeiro, impeditivos, antecipação de recebíveis,
informe e repasse consolidado por PSP/arranjo/tributo. Um cálculo isolado não constitui
repasse, pagamento autenticado ou extinção. Nenhum desses efeitos será inferido de
elegibilidade, de informe preliminar ou do adiantamento de recebível.

Próximos incrementos: snapshots temporais da plataforma; liquidação por arranjo;
parcelas e vínculos a débitos/documentos; conciliação de informes/repasse/excesso;
modelo legal simplificado com prioridade/alocação; integração gated no motor; testes
de regressão, oráculos e recibos finais. Fatos/perguntas/bundle reais continuam de B.

Também foram lidas e inspecionadas visualmente as páginas21–22 do Manual de Tempos,
SHA256 `13685b4d1a2f486cd092efb34c83e741b4300edf9692b09ffb6ce0f27036bdf1`.
Sua tabela fixa o repasse em D+1 útil, meta09h e limite12h, e diferencia os marcos
de encerramento de retorno a maior/a menor/em aberto. Essas regras estão identificadas
para o próximo incremento; não foram implementadas pelo cálculo isolado.

## Incremento transacional posterior ao corte aritmético

`SplitTransaction158` é um contrato local de evidência, **não uma implementação da API
da Plataforma Pública**. Seu campo `sequence` ordena versões do histórico fornecido;
não presume que exista campo homônimo no protocolo OpenAPI. Cada retorno liga
documento, transação, revisão dos dados fiscais, tributo, categoria, disponibilidade,
processamento e evidência. A seleção usa a versão mais nova processada até o instante
de consolidação do cálculo, sem deixar mensagem antiga processada depois sobrescrever
versão nova. Retorno disponível mas ainda não processado não modifica a segregação.

A janela de aumento é calculada em UTC−3, até00h30 do dia seguinte à iniciação,
inclusive. Valores em aberto só podem diminuir. As regras usam §§4.2.1 e7.2 de
Operações e §5.6 de Tempos. Revisões dos dados fiscais que invalidam retornos ainda
exigem expansão do ciclo: o contrato atual recusa histórico declarado mutável, em vez
de reutilizar retorno de outra revisão silenciosamente. O corte anterior permanece
imutável, inclusive suas contagens e oráculos, em `arithmetic-source-cut-v1/`.

O vínculo documental admite o canal direto com governo para arranjos do recebedor;
no pagador exige documento disponível e vinculado antes da iniciação. A parcela tem
número, quantidade, valor original, tributos e evidência de alocação vinculados ao
documento/operação. É **uma transação**, não a conferência completa de todas as parcelas.

O marco de liquidação informado deve corresponder ao arranjo e ao maior instante entre
o marco financeiro e a liberação dos impeditivos não contratuais (§6.1 Operações).
O crédito contratual posterior não altera esse instante. Antecipação e cancelamento
possuem estados explícitos, sem produzir pagamento. A segregação calculada a partir
do marco informado ainda não prova autenticidade, repasse consolidado ou extinção.

§7.1 de Operações também motivou a guarda de soma IBS+CBS informados versus valor
original no componente aritmético. Os casos anteriores que usavam valor original3
com tributos30 eram inconsistentes para o fluxo técnico completo; o novo corte dos
oráculos usa entradas válidas e mantém os bytes/resultados antigos preservados.

Continuidade: conferir cronograma integral de parcelas e débitos, resets dos dados
fiscais, informes e repasse, conciliação de saldo/excesso, prazos com calendário
qualificado, modelo legal simplificado e integração do motor em nova versão.

## Aprovação técnica e pesquisa do simplificado

Conferido integralmente o [Ato Técnico Conjunto5, de22/09/2026](https://cgibs.gov.br/upload/arquivos/202609/24094106-ato-tecnico-conjunto-5-rfb-cgibs-assinado-assinado.pdf).
Ele aprova expressamente as versões de Operações, Integração, OpenAPI e Tempos usadas
nesta task. PDF bruto SHA256 `e403dcd404f96b046683fbada11687bb863be3c54f0e4014afe393c498935961`,
capturado em05/11/2026 com recibo `sources/technical-source-revalidation-v1.json`.
O art4 condiciona efeitos à publicação nos veículos indicados; este corte não atribui
data presumida de implantação nem converte aprovação de documentação em autorização
de operação definitiva. O ato examinado não fixa percentual do simplificado.

A LC214 foi recapturada nesta retomada: mesmo SHA256 da fonte aceita A156/A157.
A consulta aos índices oficiais e a busca dirigida não localizaram, no material
examinado, ato que qualifique os percentuais do simplificado para uma decisão oficial.
Isso não prova inexistência de qualquer publicação. O modelo legal ainda precisa ser
desenvolvido e testado com parâmetros sintéticos identificados; a pesquisa/qualificação
para eventual uso oficial deve continuar. Não classificar trabalho funcional faltante
como dependência externa resolvida apenas por uma guarda.

## Livro de conciliação da operação

`SplitLedger158` é um contrato local de conferência de registros fornecidos. Não
substitui escrituração, autenticação da Plataforma Pública ou integração bancária.
O conjunto de transações deve fechar o principal e os débitos nominais da operação,
com todos os números de parcelas e identidade documental/partes/instante de conhecimento.
Cada transação continua sendo qualificada pelo contrato transacional antes da agregação.

O vínculo N:1 entre informes e repasse usa Operações§6.2 e Tempos§6.1–6.2. O agrupamento
é por PSP recebedor direto, arranjo, data de referência declarada e tributo. A parcela
desta operação é derivada dos seus registros; totais de outros documentos são explicitados
separadamente, sem assumir que o valor consolidado inteiro pertence ao fornecedor.
Um recibo por grupo/tributo pode mostrar falta ou sobra, que ficam como divergência
do lote. Ausência de repasse e de informe também permanecem visíveis.

Aplicações ao débito exigem registro próprio da autoridade, operação/documento/partes,
modalidade, valor, data e recibo. Receber um lote ou calcular segregação não gera aplicação
automática. Compensação já aplicada é evidência de modalidade distinta; não há cálculo,
apropriação ou utilização de crédito pelo A. Excesso exige determinação separada após
o saldo ter sido extinto naquele instante; transferência consome somente esse excesso
e deve beneficiar o fornecedor. As aplicações/excessos da operação não excedem os seus
valores informados nem os fundos efetivamente registrados no repasse correspondente.

O [Decreto12.955, art31](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html)
também remete a proporcionalidade das parcelas a ato conjunto. Snapshot05/11/2026,
SHA256 `8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235`,
recibo `sources/decreto12955-ledger-v1-receipt.json`. A pesquisa dirigida e os materiais
consultados não determinaram aqui um critério para atribuir centavos residuais entre
parcelas. Isso não prova inexistência de regra: a pesquisa/qualificação continua.

O primeiro rascunho do livro exigia proporcionalidade exatamente representável em centavos.
Seus bytes foram preservados em `ledger-draft-source-cut-v1/` com os respectivos oráculos.
O corte seguinte preserva alocações fornecidas que fechem o débito, mas registra desvio
racional por parcela e `allocation_proportionality_proven=false` quando necessário.
Ele **não** escolhe parcela beneficiária do residual nem aprova a regra usada pelo originador.
Exemplo sintético31/3 e alocações10,33/10,33/10,34 fica conciliável como registro,
com proporcionalidade não comprovada. Cronograma que não fecha o débito continua recusado.

Limites ainda funcionais: revisões dos dados fiscais e resets; pagamentos múltiplos
de um mesmo boleto; cronograma futuro com transações ainda não iniciadas; validação de
janelas dos informes, dias úteis e prazos de transferência/repasse; modelo legal
simplificado; integração gated do motor. As datas de referência são fornecidas, não
calculadas por calendário presumido. Nenhum desses pontos é promovido a completo pelo
status matemático `LOCAL_SPLIT_LEDGER_RECONCILED`.
