# A147 — precisão dos instantes de ciclo temporal

BaseGit87d22e7; alteraçõesA144–A146 preservadas; autorização local vigente.
IDs4B.1.6,4B.1.7,4B.1.8; efeito transversal nos contextos temporais86/88/91.

Defeito observado por inspeção: `TaxPointContext86::policyAvailability` aceita
`available_at` com microssegundos, mas exige segundos inteiros para `revoked_at`
e `superseded_at`. Um instante ISO válido com fração é recusado como sintaxe
inválida mesmo antes do corte. A147 reproduzirá a diferença no código real.

Aceite: preservar resultados dos formatos já aceitos e instantes-limite;
admitir RFC3339 explícito com até seis casas de precisão; comparar instantes,
inclusive offsets civis equivalentes; rejeitar datas impossíveis, valores
não textuais, precisão que seria truncada, timezone ausente/desconhecido e
conteúdo excedente. Cobrir a rota temporal86 nos dois runners e as chamadas de
disponibilidade dos contratos88/91, sem alegar nova revogação oficial.

## Manifesto de escrita A

- `app/Modules/Fiscal/ConsumptionReform/TaxPointContext86.php`
- `scripts/agent_a/temporal147_fixture.php`
- `scripts/agent_a/verify147.py`
- `tests/Fiscal/Unit/TaxPointLifecycle147Test.php`
- `tests/Fiscal/Integration/TaxPointLifecycle147IntegrationTest.php`
- `docs/fiscal/agent-a/20261103/task-A147-manifest.md`
- `docs/fiscal/agent-a/20261103/temporal147-results.md`
- `docs/fiscal/agent-a/20261103/validation-A147.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`

O componente86 pertence à qualificação temporal4B.1; assinatura e formato dos
resultados existentes serão preservados. Nenhum arquivo B, engine/factory
central, catálogo, schema, fato/pergunta global ou console será alterado.
88/91 serão somente lidos/testados; os chamadores continuam recebendo o mesmo
contrato. Compartilhar o efeito da correção no handoff, sem afirmar integraçãoB.

## Fontes e evidências

Sem mudança de interpretação fiscal: este incremento corrige representação
de instante e guarda de ciclo já existentes. Hashes, vigências e origem da
política86 não serão alterados. LC214 e regulamentoCBS revalidados emA145
continuam preservados; não se afirma fato jurídico novo.

Fixtures de revogação/substituição são explicitamente sintéticas em memória.
O arquivo anterior, hash, probes, logs e JUnit ficam em diretório exclusivo
`D:/ProjetoSaasExports/agent-a-A147-temporal-lifecycle-20261103-v1`.
Não reutilizar a ausência de fixtures históricas ignoradas como ausência de
implementação. Testes novos usam política versionada existente e dados próprios.

Validações: reprodução anterior; positivos/negativos e bordas temporais;
dois runners/replay; PHPStan e Pint dos caminhosA; regressão dirigida dos
contratosA145/A146 e de seletores temporais existentes disponíveis. Regressão
integral final, grupos e integração permanecem pendentes.
