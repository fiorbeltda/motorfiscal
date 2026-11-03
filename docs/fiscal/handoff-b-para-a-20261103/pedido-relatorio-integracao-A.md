# Pedido de relatório de integração — Agente B → Agente A (03/11/2026)

Encaminhamento intermediado pelo usuário. Nenhum agente publica commits ou pushes por iniciativa própria.
B só consolida na matriz 80 o que vier comprovado neste relatório; o que não vier fica `PENDENTE_DE_INTEGRACAO_A`.

Pedimos ao Agente A um relatório com:

1. **Base:** remoto, branch, commit-base (SHA), caminho do clone e `git status`.
2. **Diferenças locais:** lista fechada de arquivos alterados/criados e `git diff --stat`, mais o patch completo
   (`git diff > A-<data>.patch`, com o SHA-256 do patch) e os arquivos novos empacotados.
3. **Requisitos 4B.1–4B.4:** para cada ID tocado, a classificação (comprovado no escopo / sem evidência integral /
   parcial / lacuna / dependência externa), com o código, os testes e o recibo correspondentes.
4. **Contratos que impactam B:** fatos, enums, campos de decisão, sujeitos/papéis, bases e valores IBS/CBS, datas e
   moedas que alimentam créditos (4B.5), catálogos (4B.6), DPS (4B.7) e corpus/invariantes (4B.8).
5. **Testes:** comandos exatos, ambiente (PHP 8.5.12 nativo `E:\tools\php85.cmd` ou contêiner), resultados terminais
   (testes/asserções) e quais suítes foram excluídas por falta de evidência.
6. **Evidências e hashes:** recibos, snapshots, logs e oráculos, com SHA-256.
7. **Pendências e incompatibilidades conhecidas**, inclusive pedidos ao B.
8. **`ConsumptionReformEngine.php`:** se o patch B02 (ver `B02-especificacao-integracao-para-A.md`) foi aplicado, com o
   SHA do arquivo antes/depois e o resultado dos testes da seção 4 da especificação.

Formato preferido: um JSON de recibo + um Markdown curto, numa pasta única (ex.: `E:\dev\motorfiscal-agente-a-evidence\handoff\`).
