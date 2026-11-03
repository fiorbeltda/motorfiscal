# Agente A — A146: fornecimento principal e acessórios

BaseGit87d22e7, alterações locaisA144/A145 preservadas. Escopo4B.4.6,
4B.2.1/2.3 e4B.4.1/4.14. Autorização geral de desenvolvimento permanece.

Lacuna confirmada: A145 trata fornecimentos independentes especificados, mas
não a exceção do art7,II/§2 da LC214. A146 qualificará principal e acessórios
com vínculo e prova de serem condição/meio, sem inventar preço por acessório.
A contraprestação total será avaliada uma única vez pelo tratamento do principal,
nos dois runners. Independência, conflito de identidade, tratamento alternativo
ou mistura com o contrato independente serão recusados com razão explícita.

Escrita exclusiva de A:

- `app/Modules/Fiscal/ConsumptionReform/CombinedSupplyAssessment145.php`
- `scripts/agent_a/combined146_fixture.php`
- `tests/Fiscal/Integration/PrincipalAccessoryAssessment146Test.php`
- `scripts/agent_a/verify146.py`
- `docs/fiscal/agent-a/20261103/task-A146-manifest.md`
- `docs/fiscal/agent-a/20261103/combined146-results.md`
- `docs/fiscal/agent-a/20261103/validation-A146.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `scripts/agent_a/reconcile144.py` (vínculo ao aceite escopado)

Nenhum arquivo de B, contrato compartilhado anterior a A145, catálogo, schema,
factory, engine central ou console global será editado. A CLI própriaA145
continuará a descobrir o modo pelo contrato explícito de entrada.

Fonte:LC214 art7,II e§2, captura oficial revalidada na sessãoA145, HTTP200,
SHA256dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde.
URL e horários emA145/sources.jsonl; nenhum novo parâmetro fiscal.
Novo contrato `RTC-COMBINED-PRINCIPAL-ACCESSORIES-2026.11.03-A146`.

Validações: positivo sem preços artificiais para acessórios; negativos de
independência/vínculo/prova; ausência de duplicação; replays e ordem; CLI duas
vezes; Decimal independente; preservação byte a byte da CLI aceitaA145; Pint,
PHPStan e teste dirigidoA145+A146. A regressão final integrada continua pendente.
Artefatos externos: `D:/ProjetoSaasExports/agent-a-A146-principal-accessories-20261103-v1`.
Dependências e handoffB: documento combinado e perfis fora da qualificação atual.
