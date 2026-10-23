# 135 — contribuições próprias sobre receita de software

Avanço da ponte135 em23/10:todos os tributos próprios reexecutados e preços conferidos comRTC.166 testes/426 asserções,PHPStan0/Pint5;3 cenários,6 replays e6 oráculosDecimal. Nova declaração sintética400/641,33 reconcilia1041,33−41,33=1000,00;contrato617,92 e diferença de um centavo continuam recusados. Integração/par135 e efeitos judiciais pendentes;0/8 encerrados. Ver `phase4b-own-bridge135-price-reconciliation.md`.

Desenvolvimento local em23/10/2026. `SoftwareOwnRevenue135` calcula valores
próprios por linha de receita e os confronta com o documentoISS já reexecutado.
Não usa os motores de retenção federal para deduzir incidência própria.
Nenhum bundle135 foi instalado;134 continua a última regressão integral.

## Fontes e correção do perfil

Cinco capturas originais foram preservadas em
`D:/ProjetoSaasExports/phase4b-own135-20261023-v1/primary-v1`:
[Lei10.833](https://www.planalto.gov.br/ccivil_03/leis/2003/l10.833compilado.htm),
[Lei9.715](https://www.planalto.gov.br/ccivil_03/leis/l9715.htm),
[Lei9.718](https://www.planalto.gov.br/ccivil_03/leis/l9718.htm),
[MP2.158-35](https://www.planalto.gov.br/ccivil_03/mpv/2158-35.htm) e
[LC116](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp116.htm).
O contrato usa o tratamento de receitas de desenvolvimento de software da
Lei10.833,art10,XXV e§2º/art15,V;alíquotas cumulativasPIS0,65%/Cofins3%;
serviço puro da listaLC116 e a hipótese específica de exportação daMP14,III/§1º.
Não generaliza o perfil a software importado,regimes especiais ou outras atividades.

A conferência de jurisprudência encontrou o julgamento do
[Tema118 noSTF](https://portal.stf.jus.br/jurisprudenciaRepercussao/verAndamentoProcesso.asp?incidente=2637509&numeroTema=118):
mérito em21/10/2026,ata publicada em23/10. A tese excluiISS da basePIS/Cofins.
Captura original em `court-v1`,observada às11:03:08.451956UTC,SHA256
`ff9ef0228ee075941384512042499eedf77db333a238c9f21e39036eac642da0`.
Não se declarou trânsito em julgado,modulação ou aprovação de efeitos temporais.

OV1 do novo componente incluíaISS na base das contribuições. Foi corrigido para
OV2 antes de qualquer integração:base local da contribuição é receita própria
menos o componenteISS do mesmo documento,sob alegação explícitaTema118 vinculada
à fonte. `AVAILABLE_AT` avançou para a observação judicial. Fontes/provas mais
novas não são retrodatadas ao conhecimento do snapshot134. Aplicação judicial
ao caso concreto e efeitos temporais permanecem sem aprovação;este cálculo é local.
`native-v1` e `directed-v2` ficam históricos contrafactuais,não evidência doV2.

## Entrada e resultado

Duas provas com conteúdo originalJSON/SHA256,referência distinta,observação,
cobertura e revogação/supersessão ligam contrato/unidade/linha,partes,preço e
hashISS fresco. O perfil declara empresa privadaBR ordinária,desenvolvimento
doméstico de software,sem mercadorias misturadas,cancelamento,ajustes adicionais
ouIBS/CBS embutidos no preço. Esses fatos são provas sintéticas no ensaio;
não houve autenticação bancária/cadastral.

Data de reconhecimento e competência da receita são próprias. O perfil suporta
linha reconhecida por competência,em2026;não copia automaticamente a dataISS.
O preço composto,com desconto incondicional excluído e condicionado mantido,
é confrontado com o documento. O componenteISS documental é deduzido para
o perfilV2;alocaçãoRTC não substitui essa base. CálculoDecimal,HalfUp em centavos.
Apuração mensal,créditos,resíduos de arredondamento e valor final a pagar não
são resultados deste componente por linha.

ExportaçãoPIS/Cofins exige alegação própria de cliente no exterior e registro
capturado de pagamento com ingresso de divisas,vinculado ao documento,pagador,
beneficiário,país,data e créditoBRL integral. Não se infere a isenção do papelRTC
ou da não incidência municipal. Recebimento parcial,adiantamentos antes da
receita e outras moedas precisam de contrato próprio. Zero não vira isenção.

ISS é transportado do resultado fresco;ICMS tem tratamento local de serviço
puro listado. PIS/Cofins são calculados,com bases/alíquotas nulas na hipótese
de isenção explícita. Os quatro valores são ligados à janelaart12 pelo contrato
anterior,sem reescrever a guarda135 ou os114 arquivos do aceite134.

No cenário preservado,617,92−17,92=600,00 é a base localV2 de contribuições.
PIS3,90+Cofins18,00 deixam578,10 apósISS e contribuições,divergindo21,90 da
parcelaRTC600,00. A coincidência da basePIS/Cofins com a parcelaRTC é incidental:
o controle642,05 tem base623,43 ePIS4,05/Cofins18,70. Nenhum valor foi ajustado
para encaixar a ponte. O ensaioV1,comISS incluído,produzia577,44 e diferença22,56;
foi superado pela revisão judicialV2.

## Validação e próximos trabalhos

143 testes/386 asserções,49 novos;PHPStan0 ePint4. Dois replays integrais134 e
saídas135 idênticas;6 controles (2 positivos/4 recusas),10 conferênciasDecimal.
A primeira regressão dirigida falhou por tentar criar preço contratual110 zero;
o teste foi corrigido para usar a fixture histórica de desconto integral,sem
alterar o contrato110. Logs de todas as tentativas foram preservados.

Recibo compacto:
`storage/app/phase4b-own135-development-20261023/own135-local-validation.json`.
Matriz80 corrente no mesmo diretório,atualizando4B.4.1/4B.4.2 sem alterar revisões
próprias ou encerrar grupos. Próximos:qualificar efeitos judiciais por caso,
reconciliar a ponte com todos os tributos próprios,integrar/regredir o par135,
concluir câmbioISS/importação/demais requisitos.0/8 grupos encerrados;produção bloqueada.
