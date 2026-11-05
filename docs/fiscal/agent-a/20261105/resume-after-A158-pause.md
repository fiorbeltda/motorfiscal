# Ponto seguro de pausa — A157/A158, 05/11/2026

Pausa expressamente solicitada pelo usuário, com autorização para commit do progresso.
Branch `codex/fiscal-engine-bootstrap`; base `f501885153d64f77ea8a7571fa680d5c9f43e970`.
Commit identificado como Codex, sem afirmar finalização da A158 ou dos44 requisitos.
Nenhum push solicitado nesta ordem. Nenhum processo de testes/PHPStan permanece vivo;
PHP-FPM existente mantido, sem alterações de Docker, volumes ou banco.

## Preservado

- A157: recolhimento pelo adquirente, recibos/aplicações intermediárias, excesso e
  transferências; integração gated no motor. Pacote externo e relatório próprios
  preservados; escopo validado anteriormente, sem encerrar o requisito4B.3.8.
- A158: aritmética padrão, qualificação temporal/transacional e livro de conciliação;
  CLI com modos arithmetic/transaction/ledger. Nenhuma integração A158 no motor ainda.
- Corte corrente:928 testes/2160 asserções na regressão dirigida, zero falhas/erros/skips;
  inclui153 testes próprios/397 asserções. PHPStan global nível8 e Pint nove arquivos
  aprovados.13 cenários CLI do livro repetidos,478 comparativos Decimal e conferências
  Fraction; console em leitura28 verificações/HTTP200. Regressão **PARCIAL**.
- Evidências completas e fontes oficiais no D:, em
  `D:/ProjetoSaasExports/agent-a-A158-split-payment-20261105-v1`.
  Checkpoints aritmético, transacional e do livro preservam seus próprios bytes/hashes.
- Fonte/relatórios A157 em
  `D:/ProjetoSaasExports/agent-a-A157-acquirer-collection-20261105-v1`.

## Retomada

1. Conferir branch, remoto, estado local, processos e este commit antes de atualizar;
   preservar novas mudanças e não usar reset/clean/checkout de arquivos/force push.
2. Retomar A158: revisões fiscais/reset de retornos; múltiplos pagamentos de boleto e
   cronograma futuro com transações ainda não iniciadas; prazos/janelas com calendário
   qualificado; modelo legal simplificado, sem presumir percentuais oficiais.
3. Para resíduos do cronograma, preservar alocações fornecidas e seus desvios racionais.
   Não inventar distribuição de centavos nem converter conciliação em aprovação legal.
4. Integrar A158 no motor atrás de gate novo, com vínculo aos fatos nominais/documentais,
   testes e candidatos em memória. Propor a B os fatos/perguntas/bundle novos necessários;
   não editar seus recursos, catálogos, testes, matriz80 ou console.
5. Concluir a validação do conjunto integrado e o handoff A158; continuar o desenvolvimento
   dos44 IDs4B.1–4B.4 e a auditoria A154, sem reduzir o objetivo ao split payment.

Sem encerramento formal:0/8 grupos, Fase4B em andamento, fases3/4A fechadas e5–7 planejadas.
Produção não liberada; essa liberação não é requisito artificial para o aceite funcional
em Sandbox. Os impedimentos funcionais reais e as dependências externas devem continuar
separados. Nenhum recebimento/aceite/ativação por B é presumido.
