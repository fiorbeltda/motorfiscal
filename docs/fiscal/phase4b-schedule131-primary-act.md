# Documentos131 — cronograma temporal e contribuinte

Bundle atual:`CONSUMPTION-PHASE4B-2026.10.22-131-draft2`,SANDBOX_CANDIDATE.
Materializados32 perfis/datas dos18 incisos do art1 doAto ConjuntoRFB/CGIBS4,
incluindo alíneas e exceções dos§§1–4. Política ligada ao original preservado,
sem substituir cronograma por uma data anual genérica ou modificar versões anteriores.

`DocumentSchedule131` resolve documento,perfil,regime,data da operação e
conhecimento. Integração em`DocumentObligationEngine` e no fluxoRTC. NFS-e de
serviçoISS geral mantém01/10/2026;plataforma qualificada,serviços específicos
e demais perfis das alíneas correspondentes recebem01/12/2026. Simples/MEI
recebem a exceção de01/01/2027. NF-e diferencia regra geral,monofasia,importação
de bens materiais e contribuinte nãoICMS. DeRE preserva primeira competência
outubro/2026 e dia15 do mês seguinte,sem inventar ajuste de dia útil.

## Importação e pergunta material

Na importação,o regime que controla a exceção é o do contribuinte brasileiro:
adquirenteBR ou destinatárioBR. O regimeFOREIGN do fornecedor não o substitui.
`rtc.recipient_regime` e sua pergunta foram acrescentados em novos fatos/perguntas
do draft2. Ausência desse fato na hipótese aplicável retorna
`RTC_DOCUMENT131_TAXPAYER_REGIME_REQUIRED`,com pergunta nos dois executores.
O valor legadoNON_TAXPAYER do campo de regime de aquisição não é interpretado
como dispensa de documento:incidência/importador já são qualificados antes.

O primeiro draft131 foi preservado. A revisão identificou a necessidade de
usar o regime do contribuinte na importação;correção entregue emdraft2,sem
reescrever o manifesto,catálogo ou evidências do draft1. Seus12 snapshots foram
reproduzidos exatamente. As guardas131/129/128/127/126/122/119 continuam exigidas
nas versões correspondentes,mesmo sem seus marcadores opcionais.

O trace da importação agora apresenta cronograma resolvido e data aplicável,
mas mantém a recusa documental anterior. O cronograma não comprova emissão,
cumprimento de obrigação,dispensa de recolhimento ou obrigação municipalISS.
O candidato130 continua vinculado ao snapshot129;a nova geração não altera
silenciosamente o seu contrato. Fatura/câmbioNT009,baseISS por documento e
documento integrado definitivo ainda exigem implementação/qualificação.

## Fonte e revisão

[Índice oficial dos atos conjuntos](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/legislacao/atos-conjuntos/)
mantém a referência aoAto4. O original preservado e lido integralmente é
`resources/fiscal/sources/official/dere-supplement-observed-20261002-ato-conjunto-rfb_cgibs-no-4-de-30-de-julho-de-2026.pdf`.
SHA256:`67576256ea863adef5905896d6e7068682d993f3e36641ff16529ab66e4fc79e`.
Suas três páginas foram renderizadas e conferidas visualmente. Cabeçalho:
DOU31/07/2026,edição143,seção1,página25. O art3 declara vigência na publicação.
Não se deduziu vigência do nome do arquivo.

A política131 tornou-se disponível para esta revisão em
`2026-10-22T17:49:16+00:00`;a observação do arquivo preservado continua02/10.
Não é uma nova captura válida doPDF:na conferência atual,aURL antiga retornou
HTML e o endereçoDOU recusouHTTP403. Respostas/tentativas foram preservadas
noD:,sem aceitarHTML comoPDF ou inventar nova observação normativa. A leitura
integral qualifica este ato no contrato local;não constitui aprovação jurídica
de produção,autenticação externa ou revisão de todos os atos relacionados.

## Evidências verificadas

- 112 testes dirigidos/809 verificações,PHPStan geral sem erros,Pint639 aprovado.
- 18 decisões/replays:seis comparações129 e12 atuais131 nos dois executores,
 cobrindo serviço doméstico,importação,plataforma,Simples de adquirente e
 destinatário,e pergunta pelo regime ausente.
- 41 replays preservados exatos:28 do129,um do130 e12 do primeiro draft131.
 Não são nova suíte integral ou nova execução dos1441 históricos122.
- Auditor independente:265 conferências. Cada data das32 linhas foi extraída
 do textoPDF por localizador;§3 foi confrontado com a remissão ao incisoXIV.
 Fatos monetários/ISS/federal mantidos nos comparativos;somente o cronograma
 e a versão declarada foram substituídos no resultadoRTC doméstico.
- 107 hashes de runtime atuais,107 fontes válidas e130 bloqueios mantidos.
 ready=false;eligible_for_local_validation=true.122 último aceite integral.

Recibo atual:
`storage/app/phase4b-schedule131-development-20261022/schedule131-draft2-scoped-validation.json`.
RaizD:`phase4b-schedule131-20261022-v1`;`native-draft2-v1`,
`directed-draft2-v1`,`phpstan-draft2-v1`,`pint-draft2-v1` e históricos.
O recibo preliminar do draft1 permanece separado. Uma falha inicial do auditor
confundiu uma referência interna a§1 com o início do parágrafo;foi corrigida
pela delimitação do texto,não pela alteração das datas para satisfazer o verificador.

Matriz80 recebe três atualizações de evidência,sem encerrar requisitos ou grupos.
Fases3/4A encerradas emSandbox;4B parcial,0/8 grupos encerrados;5–7 planejadas.
Sem assinatura,transmissão,commit oupush.
