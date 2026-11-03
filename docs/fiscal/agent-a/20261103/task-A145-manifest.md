# Agente A — A145: operações com fornecimentos distintos

Base e autorização: mesmas de A144, HEAD87d22e7, desenvolvimento local autorizado.
IDs: 4B.4.6; efeitos verificáveis em 4B.2.1,4B.2.3,4B.4.1,4B.4.12–14.

## Lacuna e aceite

O runner atual calcula uma operação/fornecimento por cenário. `base_adjustments`
não representa múltiplos serviços. A145 compõe fornecimentos independentes
especificados, com valor e evidência próprios, e concilia sua soma com o total
contratado. Avalia cada item nos runners RTC e integrado, com conhecimento
capturado dos bundles existentes. Não altera classificação, crédito, schema ou
catálogos para fazer um caso passar.

Aceite: cenário positivo com serviços de tratamentos/locais distintos; memória
decimal e totais IBS estadual/municipal/CBS separados; recusa de preço não
especificado, soma divergente, IDs ambíguos, conflito de partes, conhecimento
anterior à observação e dependência fiscal em qualquer item; replay e CLI real.
Sem arbitramento automático nem presunção de vínculo principal/acessório.
Cobrança unificada sem especificação exige qualificação própria do art13.

## Manifesto fechado de escrita

- `app/Modules/Fiscal/ConsumptionReform/CombinedSupplyAssessment145.php`
- `app/Console/Commands/AssessCombinedSupplies145Command.php`
- `tests/Fiscal/Integration/CombinedSupplyAssessment145Test.php`
- `scripts/agent_a/combined145_fixture.php`
- `scripts/agent_a/verify145.py`
- `docs/fiscal/agent-a/20261103/task-A145-manifest.md`
- `docs/fiscal/agent-a/20261103/combined145-results.md`
- `docs/fiscal/agent-a/20261103/validation-A145.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md` (acréscimo da entrega)
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json` (vínculo escopado)

Artefatos pesados e execução: diretório exclusivo
`D:/ProjetoSaasExports/agent-a-A145-combined-supplies-20261103-v1`;
temporários de ensaio em `storage/app/agent-a-A145/` (ignorados no Git).

Somente leitura: engines/serviços/factories centrais; schemas, catálogos,
bundles, fatos/perguntas e fixtures históricos; classes do B; console global.
Nenhum caminho existente de contrato compartilhado será alterado. Os novos
componente e comando tratam exclusivamente composição de bases e avaliações
de fornecimentos4B.2/4B.4, com entrada própria e descoberta automática de comando.

## Fontes, validação e limites

LC214 consolidada, art7 e §§1–3, conferida em03/11/2026 11:18:56Z, HTTP200,
SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.
Decreto12955 consolidado, consulta11:18:56Z, HTTP200,
SHA256 `8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235`.
URLs, bytes e horários completos em `sources.jsonl` no diretório externo.
Nenhuma fonte histórica foi substituída. A observação nova não será retroagida.

Testes positivos/negativos, concorrência de IDs, bordas de centavos, ordenação,
guardas futuras sem taxas inventadas, CLI repetido, oráculo Decimal Python,
PHPStan e Pint nos caminhos próprios. Regressão final integrada não é substituída.
Dependência B: projeção documental de múltiplos fornecimentos no documento
combinado, classificações/perfis não atendidos e integração global.
Não conceder crédito, extinção, assinatura, transmissão ou produção no agregado.
