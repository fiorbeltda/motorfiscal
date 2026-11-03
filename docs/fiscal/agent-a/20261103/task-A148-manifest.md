# A148 — referências materiais do recolhimento

BaseGit87d22e7; A144–A147 preservados. Escopo4B.3.1/4B.3.2/4B.3.7/4B.3.8/4B.3.10.
Autorização geral de desenvolvimento local; sem escrita em B.

Inspeção: `CollectionDecisionEngine` verifica presença, mas não conteúdo das
referências de instrumento/transação/implantação. A definição STRING/min1 aceita
espaços e controles. A148 deverá reproduzir se esses valores produzem elegibilidade
e impedir qualificação sem identidade material válida, sem inventar formato
CPF/CNPJ nem autenticação externa. Os papéis contribuinte/responsável/pagador
continuam separados; nenhuma evidência de liquidação será presumida.

Aceite: referências em branco/padded/com controle e partes utilizadas na
qualificação retornam correção nos fatos exatos; IDs textuais como0001 permanecem
intactos; ausência e condições legais anteriores preservadas; flag sintética de
implantação em teste não ativa catálogo oficial. Validar nos dois runners e no
componente usado por eles, manter estadoUNCONFIRMED e replay/CLI válidos.

## Escrita exclusiva A

- `app/Modules/Fiscal/ConsumptionReform/CollectionDecisionEngine.php`
- `scripts/agent_a/collection148_fixture.php`
- `tests/Fiscal/Integration/CollectionEvidence148Test.php`
- `scripts/agent_a/verify148.py`
- `docs/fiscal/agent-a/20261103/task-A148-manifest.md`
- `docs/fiscal/agent-a/20261103/collection148-results.md`
- `docs/fiscal/agent-a/20261103/validation-A148.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`

`CollectionDecisionEngine` pertence a sujeitos/recolhimento4B.3. Assinatura e
resultados válidos serão mantidos. Não alterar catálogos, fatos/perguntas,
classes de crédito/documento, factories, engine central ou console global.

Fontes: sem mudança de interpretação do art36/arts31–35; correção de integridade
de referências já obrigatórias. Não se declara pagamento ou implantação oficial.
Arquivo anterior, probes e logs no diretório exclusivo
`D:/ProjetoSaasExports/agent-a-A148-collection-evidence-20261103-v1`.
Validação: reprodução anterior; positivos/negativos; IDs textuais; ambos runners;
snapshot válido anterior; CLI/replay; PHPStan/Pint; regressão dirigida existente.
O ciclo completo de opção/liquidação e aceites integrais permanecem pendentes.
