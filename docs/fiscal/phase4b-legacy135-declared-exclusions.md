# 135 — incidência própria e exclusões declaradas por documento

Avanço135V2 em23/10:cálculo próprio dePIS/Cofins por receita de software,comISS deduzido sob alegação localTema118 (mérito21/10,ata23/10).143 testes/386 asserções,PHPStan0/Pint4;2 replays134 e10 conferênciasDecimal. A ponte preservada diverge21,90 no documento nacional. Efeitos judiciais e integração pendentes;0/8 grupos encerrados. Ver `phase4b-own135-software-revenue.md`.

Retomada em23/10/2026 após reativação do objetivo. Checkpoint publicado134
preservado,sem novos commits ou push nesta retomada. Docker estava desligado;
foi aberto oculto porWMI e os mesmos contêineres foram iniciados,sem recriação.

Fonte primária: [LC214,artigo12](https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm),
capturada em23/10 às10:41:42.939166UTC. SHA256:
`70672c70d87f6e8978c34cd10056fd2d91e7a80c6f3483fa8715c850aae6d9c7`.
Bytes e26 parágrafos ativos em
`D:/ProjetoSaasExports/phase4b-price135-20261023-v1/primary-v2`.
A extração omite texto tachado. A tentativaV1 preservou bytes,mas falhou na
extração de parágrafos implicitamente fechados noHTML;V2 corrigiu o parser.
O hash desta página difere da captura anterior do endereço compilado129;
isso não é,por si,prova de alteração normativa no artigo12.

O §2º,V trata dos montantes incidentes dos tributos indicados entre01/01/2026
e31/12/2032. O §1º inclui tributos fora das exclusões e descontos condicionados.
IPI (§2º,II),COSIP (§2º,VI),IBS/CBS e demais exclusões possuem fundamentos
distintos;não foram indevidamente agregados ao mesmo contrato temporal.

`LegacyExclusionWindow135` é componente isolado no checkout,sem integração a
bundle,CLI ou decisão fiscal. Fixa fonte,observação,intervalo e lista de quatro
tributos. Recebe contexto documental e quatro avaliações próprias declaradas:
ISS,ICMS,PIS,Cofins. Exige valor decimal em centavos,hash do contexto e proveniência
distinta por tributo. Recusa ausência,duplicidade,tributo fora da cláusula,
valores inválidos,divergência documental e soma superior ao preço. Reordenação
das avaliações não altera sua canonicalização. OISS do contexto é vinculado
por hash;este componente não reexecuta nem autentica cada avaliação própria.

Um resultado de retenção federal não solicitada,retenção efetiva ou incidência
não avaliada não vale como avaliação própria com valor zero. Zeros somente
entram como declarações expressas com a mesma proveniência exigida dos valores
positivos. O contrato ainda não valida ciclo de vida/conteúdo original dessas
avaliações nem sua fundamentação própria. Campos de saída explicitam avaliações
não reexecutadas,demais exclusões não avaliadas,aprovação legal/produção falsas.
É uma fronteira de reconciliação de declarações; não fecha a pendência fiscal.

O cenário anterior617,92−17,92=600,00 funciona somente sob a alegação explícita
de ausência de outros tributos embutidos. No controle sintético comPIS4,02 e
Cofins18,54,o saldo é577,44. Esses valores adicionais são controles,sem alíquota
ou incidência normativa atribuída ao caso real. Nenhum preço ou base anterior
foi reescrito para forçar a ponte.

Validação:29 testes novos/36 asserções;94 testes/316 asserções com os casos133/134,
PHPStan0,Pint3 arquivos. O ensaio reexecutou integralmente o snapshot134 duas
vezes,com saída idêntica;8 controles (4 positivos declarativos e4 recusas),
4 conferênciasPythonDecimal. O evento `FEDERAL_WITHHOLDINGS_SKIPPED` está no
snapshot e não produz zeros de incidência própria. Evidências em
`D:/ProjetoSaasExports/phase4b-price135-20261023-v1`;recibo compacto em
`storage/app/phase4b-legacy135-development-20261023/legacy135-scoped-validation.json`.

Próximo:obter/reexecutar avaliações próprias e provas materiais por tributo,
ligá-las aoISS fresco e ao ciclo de vida documental,avaliar demais exclusões,
depois integrar/regredir o par135. Última suíte integral é134 (9406/145560),
sem nova execução integral neste corte. Nenhum grupo4B encerrado;matriz80
permanece134,com todas as pendências de câmbioISS/importação/demais perfis.
