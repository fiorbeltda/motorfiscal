# Antecipações90: definitivo com valores anteriores capturados, preparação externa

## Resultado e evidências

Preparação em
`D:/ProjetoSaasExports/phase4b-advance90-final-reconciliation-preparation-20261015/`.
Fonte final: `CapturedAdvanceFinalReconciler90-formatted-v2.php`.
Relatório: `staged90-captured-reconciliation-validation-v2.json`.
Nenhum arquivo de runtime, teste ou recurso fiscal foi integrado/modificado.

28 casos nativos aprovados;14 cálculos confrontados com oráculo independente
PythonDecimal de precisão70 e arredondamento half-up. O probe reexecuta também
os43 casos pré-requisito89. PHPStan nível8 e Pint dos dois componentes finais
aprovados. Auditoria própria volta a conferir os4.692 arquivos congelados88.

## O que a composição faz

O reconciliador chama89 sobre snapshots e livro originais; não aceita um
marcador `RESOLVED` de um resultado fornecido pelo chamador. Exige evidência
material do fornecimento final vinculada a operação, contrato, fornecedor,
documento, data, base e observação, além do contexto validado89.

Calcula o definitivo sobre a base total na data final. Subtrai, por IBS estadual,
IBS municipal e CBS, os valores anteriormente capturados e ligados ao livro,
sem recalcular a antecipação usando o catálogo do definitivo. Exemplo2026:
base final1000 e antecipação300 resultam em CBS9,00−2,70=6,30 e
IBS estadual1,00−0,30=0,70. IBS municipal permanece separado.

As mudanças de taxa dos ensaios são explicitamente sintéticas, não novos atos
oficiais ou parâmetros ativados. Com taxa CBS sintética0,50% no definitivo de
300, a memória final1,50 menos a antecipação capturada2,70 resulta em−1,20.
O tratamento é `OVERPAYMENT_PROCEDURE_REQUIRED`; crédito concedido0,00 e caixa
desconhecido, sem lançamento. O catálogo oficial preservado sem CBS2027 continua
recusando `RTC_RATE_NOT_PROVEN:CBS`, sem fallback.

Os casos cobrem saldo zero, arredondamentos pequenos, duas antecipações,
permutação, base de16 dígitos, taxa sintética com seis casas e recusas de
precisão excessiva, taxa superior a100, prova inválida, livro divergente,
snapshot ausente, registro consumido e definitivo já registrado.

## Uso único ainda é proposta, não baixa no livro

A saída contém uma proposta e token determinístico, sem consumir registros.
Uma verificação adicional revelou que o token inicial não incluía base/contexto
final nem a memória final:14 pedidos distintos podiam compartilhar o token.
A reprodução está preservada em `probe-v3-token-regression/`. A versão final
vincula hashes do contexto final e da memória de cálculo, além do livro e das
identidades. O probe final exige14 tokens distintos para14 contextos/memórias
distintos e repetição determinística do mesmo pedido.

Isso não substitui transação atômica de registro/consumo com conferência do
estado atual, nem qualificação de retificação/cancelamento. Todas as flags de
lançamento, consumo efetivo, extinção, crédito, autenticação e produção seguem
falsas. Vínculo local89 não autoriza por si débito anterior legalmente provado.

## Continuidade

Aguardar resultado terminal e auditoria88, preservar o predecessor e integrar
89/90 em versão própria com corpus e testes permanentes. A composição deve
manter os qualificadores existentes de incidência/local/papéis/base e obrigação
documental, reforçar a integridade do corpo salvo no replay RTC e tratar o
registro/consumo sem alterar evidências históricas. Não conclui o contrato
temporal87, os80 requisitos, Fase4B ou produção.
