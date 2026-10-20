# Temporal82: planejamento isolado de eventos, sem integração

## Estado e evidência

Componente externo em
`D:/ProjetoSaasExports/phase4b-temporal82-event-preparation-20261015/TaxPointEventPlanner82.php`.
Não foi instalado em app, testes ou recursos. Não existe bundle82 ativo.

36 casos passaram nativamente em PHP8.5.10. PHPStan nível8 e Pint do arquivo
passaram, com logs exclusivos `scope82-staged-*` no diretório histórico71.
O auditor `D:/ProjetoSaasExports/audit_temporal82_event_preparation.py` verifica
o corpus, hashes, gates e inventário completo de3.836 entradas aceitas78.
Resultado exclusivo: `event-preparation-validation.json` no diretório externo82.

## O que o componente faz

Recebe eventos explícitos e evidências locais vinculadas à operação/parcela.
Valida datas civis e observação até a data de conhecimento em São Paulo,
valores monetários textuais, identificação e unicidade de parcelas. Recusa
datas inválidas, eventos futuros, prova de outra operação/parcela, valor pago
inconsistente, documento sem vínculo e contexto público sem qualificação própria.

Para fornecimento ordinário, aceita oito espécies explícitas: entrega material,
disponibilização imaterial, término de serviço, início de transporte nacional,
fim de transporte internacional de carga, descoberta sem documento idôneo e
aquisição em licitação pública ou leilão judicial. Isso não qualifica sozinho
que a operação informada pertence à espécie declarada.

Para execução continuada, distingue constituição do direito de recebimento de
mero documento e escolhe a primeira ocorrência entre exigibilidade e pagamento
de cada parcela. Para antecipações, separa pagamento de seleção da alíquota:
data mais antiga entre pagamento e documento correspondente. O fornecimento
final mantém base total e lista de parcelas a reconciliar. Não soma nem deduz
antecipações, não seleciona alíquotas e não produz lançamento ou crédito.

O retorno é `LOCAL_EVENT_PLAN_ONLY`, com flags explícitas de política de fonte,
cálculo, reconciliação, período de apuração, autenticação e produção falsas.
Não interpretar esses planos como decisões fiscais aceitas.

## Histórico preservado

A primeira execução teve35/36 casos conformes. O teste negativo usou objeto JSON
vazio, convertido em array vazio pelo decode associativo do PHP, indistinguível
da lista vazia válida. A v2 usou objeto não vazio; não afrouxou a regra nem alterou
o componente para aprovar o teste. As duas execuções e fixtures foram preservadas.

As capturas oficiais82 incluem índices CGIBS, regulamento original reobservado
com hash idêntico ao67 e Resolução13, cuja página foi renderizada e conferida.
Essa resolução altera o art617 (efeitos), não o art11. O índice de regulamentos
observado lista6/13 e a portaria conjunta7; isso não prova exaustividade jurídica.
O índice de resoluções observado chega à18. Manter distinta verificação da
publicação/disponibilidade e qualificação de vigência por espécie de operação.

## Trabalho obrigatório antes da integração

- Completar presunções documentais §§8/9, prova em contrário e vínculo à espécie.
- Vincular política a fontes/versionamento/observação e limites de aplicação;
  não usar automaticamente o texto observado para toda data histórica.
- Reconciliar bases/antecipações com aritmética decimal exata e resultados
  por IBS estadual, municipal e CBS, inclusive alteração de alíquota e cancelamento.
- Tratar o período de apuração separadamente: nenhuma exceção genérica de cinco
  dias foi ativada. Cobrir as viradas de período/2026–2027 no cálculo integrado.
- Integrar fatos/perguntas, trace, bundle, dois runners, snapshots/replays,
  invariantes e regressão completa antes de aceitação de desenvolvimento.

A aceitação78 permanece a base do motor. Este componente não conclui4B.1,
documentação fiscal ou os80 requisitos, e não remove nenhum bloqueio de produção.
