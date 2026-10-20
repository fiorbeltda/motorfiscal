# Temporal83/84: presunção documental e reconciliação decimal isoladas

## Estado

Dois componentes externos, não instalados no motor e sem bundle ativo:

- `D:/ProjetoSaasExports/phase4b-temporal83-presumption-preparation-20261015/SupplyDateEvidenceResolver83-formatted.php`.
- `D:/ProjetoSaasExports/phase4b-temporal84-reconciliation-preparation-20261015/AdvanceTaxReconciler84-formatted.php`.

83:47 casos nativos, incluindo composição com o planejador82. 84:40 casos
nativos comparados com um oráculo Python Decimal independente. Ambos executados
em PHP8.5.10 e aprovados em PHPStan nível8 e Pint após a formatação final.
As primeiras versões funcionais passaram nos mesmos casos, mas falharam no
estilo; fontes, logs e execuções anteriores foram preservados, não substituídos.

Auditor exclusivo: `D:/ProjetoSaasExports/audit_temporal83_84_preparation.py`.
Relatório: `D:/ProjetoSaasExports/phase4b-temporal83-84-combined-audit-20261015/combined-preparation-validation.json`.
Confere fontes do código, resultados, gates e inventário completo aceito78.

## Normalização83

Distingue ausência declarada (`null`) de estado desconhecido (campo ausente).
Exige operação e documento vinculados, prova local, integridade local do documento
e observação até a data civil de conhecimento em São Paulo. Dados inválidos ou
de outro documento não desaparecem silenciosamente diante de prova material.

Para entrega material/disponibilização imaterial, normaliza previsão informada
ou, quando explicitamente ausente, emissão documental. Previsão ainda futura
retorna `PRESUMED_SUPPLY_DATE_NOT_YET_REACHED`: não é convertida em fornecimento
já ocorrido. A avaliação local da presunção registra sua própria data e mantém
separada a observação original do documento; não fabrica observação material.

Prova material em contrário precisa apontar o documento que rebate. Para
serviços e eventos especiais, aceita prova explícita da espécie, mas recusa
aplicar automaticamente a presunção documental sem qualificação de alcance.
Contextos público, continuado e cancelado exigem caminhos próprios.
O componente normaliza evidência; não autentica documento nem qualifica sozinho
o enquadramento legal. Fonte de pesquisa: art11 do regulamento CBS, capturado81;
confronto IBS preservado81/82. Política de fonte permanece não ativada.

## Reconciliação84

Usa DecimalValue/Brick Math do motor e TemporalRateCatalog, sem alterar ambos.
Seleciona cada alíquota na data própria do evento, calcula antecipações por
parcela com arredondamento monetário e calcula o definitivo sobre a base total,
incluindo parcelas antecipadas. Reconcilia separadamente IBS estadual, municipal
e CBS, sem cobrar novamente o definitivo inteiro além das antecipações.

Valor negativo da diferença indica `OVERPAYMENT_PROCEDURE_REQUIRED`, não crédito
automaticamente apropriado. Igualdade indica ausência de débito adicional;
diferença positiva é apenas candidato local. Antecipações computadas não provam
débitos anteriormente escriturados ou extintos. Flags de lançamento, crédito,
política legal, período de apuração e produção permanecem falsas.

Corpora cobrem múltiplas parcelas, mudança de período/alíquota, arredondamento
por parcela, valores acima da precisão inteira binária,
valor antecipado superior ao total (recusa para ajuste), vínculos,
datas inválidas, ausência/conflito/revogação/supersessão de taxa. Taxas futuras
do corpus são explicitamente **sintéticas**, não parâmetros legais disponíveis.
O seletor recusa CBS futura ausente; não há fallback para a taxa de2026.

## Antes de integrar

Completar o contrato de aplicação das fontes por espécie/data, composição
83→82→84 com invariantes e prova dos débitos anteriores, retificações e
cancelamento; separar período de apuração de data do evento/alíquota. Integrar
fatos/perguntas, bundle versionado, trace, dois runners, snapshots/replays e
regressão completa. Verificar também identificadores numéricos textuais e ordem
das parcelas na composição, pois o planejador82 usa chaves de array para IDs.

A aceitação78 continua a base do runtime. Estes resultados não são uma nova
suíte completa, aceitação do requisito4B.1 ou autorização de produção.
