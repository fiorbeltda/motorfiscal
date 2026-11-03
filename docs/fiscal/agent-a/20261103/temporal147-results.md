# A147 — precisão do ciclo temporal corrigida

BaseGit87d22e7. Um arquivo existente de A alterado:
`app/Modules/Fiscal/ConsumptionReform/TaxPointContext86.php`.
Assinaturas, fontes, vigências e estrutura dos resultados foram preservadas.

## Defeito e resultado

O parser anterior aceitava somente segundos inteiros em revogação/supersessão.
Recusava instantes válidos com microssegundos e lançava exceção quando o campo
continha byte nulo. A caracterização sobre a classe anterior produziu18 falhas
e15 passes; os arquivos anteriores e resultados foram preservados.

O qualificador agora admite instantes explícitos com até seis casas decimais,
UTCZ ou offset civil válido. Não trunca precisão excedente nem normaliza uma
data impossível. Offset desconhecido-00:00, timezone ausente, espaços, tipos
não textuais e byte nulo são recusados de forma controlada.

Antes da revogação/substituição a política continua disponível; no instante e
depois dele, indisponível. Os hashes da fonte e a observação original da política
não mudam. As linhas de ciclo dos testes são sintéticas; não representam ato
oficial de revogação, publicação nova nem ativação de implantação.

## Validação terminal

-40 testes dirigidos/125 asserções, incluindo os dois runners e replayRTC.
-221 testes/735 asserções na regressão dirigida; inclui os40 acima eA145/A146,
  não somar as contagens. Não é a regressão integral final da Fase4B.
-8 probes comparados com oráculo `datetime` Python:6 corrigidos e2 preservados.
-CLI real sem alteração de ciclo: resultado byte a byte igual ao produzido pela
  classe anterior, carregada isoladamente por `auto_prepend_file`.
-Snapshot criado com a classe anterior reproduzido integralmente pela atual.
-Contratos88/91 continuam aplicando as suas próprias guardas de dados após
  a validação do ciclo; o parser não transforma ausência de fatos em autorização.
-PHPStan sem erros e PintPASS em quatro arquivosPHP de A.

Recibo: `validation-A147.json`. Oráculo: `scripts/agent_a/verify147.py`.
Pacote externo: `D:/ProjetoSaasExports/agent-a-A147-temporal-lifecycle-20261103-v1`.
As fixtures históricas86 em `storage/app` não vieram no clone versionado; os
testes novos usam a política real do catálogo91 e dados de prova próprios.
Nenhum corpus histórico foi fabricado ou reescrito para simular sua recuperação.

## Matriz e coordenação

Avanço escopado4B.1.6/4B.1.7/4B.1.8, sem encerramento dos itens ou do grupo.
A correção114 anterior continua preservada e não foi reaberta. Restam eventos
públicos/cancelados/especiais, liquidação e provas integrais por família/período.
Nenhum catálogo, schema, arquivoB, engine/factory central ou console global foi
editado. A proposta de matriz/console está no handoff; recebimentoB não provado.
Sem commit,push,PR,merge,produção ou alteração do checkout original.
