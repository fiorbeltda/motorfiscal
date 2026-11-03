# A150 — áreas e extensões territoriais exatas

BaseGit87d22e7; A144–A149 locais preservados. Escopo4B.2.4/4B.2.5,
com efeitos de precisão/alocação em4B.4.11/4B.4.12/4B.4.14.

Aceite: imóvel em múltiplos municípios agrega áreas por município e determina
um destino pela maior área; empate não escolhe o primeiro registro. Via agrega
extensão por município eUF e devolve frações exatas, sem taxa média ou percentual
arredondado. A lista territorial deve ser completa, vinculada à mesma operação,
fornecimento,partes,ativo,data e unidade; sua soma deve coincidir com o total.
Nenhuma medição é convertida de unidade ou arredondada por suposição.

Fonte primária: LC214 art11,II/§2 eVIII; regulamentoCBSart12,§2 eVIII,
reconsultado em fonteCâmara nesta sessão. Bytes preservados emA145/sources.jsonl:
LC214dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde;
CBS8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235.
Não se decide desempate não previsto nem se altera snapshotIBGE115.

## Manifesto de escrita exclusiva A

- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/locality150_fixture.php`
- `tests/Fiscal/Integration/TerritorialLocality150Test.php`
- `scripts/agent_a/verify150.py`
- `docs/fiscal/agent-a/20261103/task-A150-manifest.md`
- `docs/fiscal/agent-a/20261103/locality150-results.md`
- `docs/fiscal/agent-a/20261103/validation-A150.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`

As duas classes acima foram criadas porA149 e pertencem exclusivamente a A.
Novo contrato opt-in `RTC-MEASURED-TERRITORIAL-LOCALITY-2026.11.03-A150` em
`territorial_measurements`; preservar inputs/saídas anteriores sem essa opção.
Nenhum arquivoB,catálogo,enum global,schema,crédito,engine/factory central ou
console global será alterado. Destinos e frações serão handoff para classificação
e projeção deB; não escolher cIndOp,cClassTrib,NBS ou alíquotas unilateralmente.

Validações: área agregada maior que a maior parcela isolada; empate; soma,unidade,
vínculo,completude eIDs; fração não terminante; agregaçãoUF/município; medida de
alta precisão; ordem estável; CLI repetida,recusa,replay e oráculoFractionPython;
preservação byte a byte da CLI149; Pint/PHPStan e regressão dirigida acumulada.
Artefatos exclusivos em `D:/ProjetoSaasExports/agent-a-A150-territorial-measures-20261103-v1`.

## Preservação de recibos próprios durante reprodução

A reprodução de oráculos após evolução da classe não pode reescrever um aceite
anterior com o hash da implementação nova. Acrescentar guarda de criação única
nos escritores dos próprios recibos, mantendo o índice correnteA144 atualizável.
Caminhos adicionais, todos exclusivosA:

- `scripts/agent_a/evidence_io.py`
- `scripts/agent_a/verify145.py`
- `scripts/agent_a/verify146.py`
- `scripts/agent_a/verify147.py`
- `scripts/agent_a/verify148.py`
- `scripts/agent_a/verify149.py`
- `docs/fiscal/agent-a/20261103/evidence-preservation-A150.json`

Resultado idêntico pode ser conferido novamente; conteúdo diferente exige novo
artefato/versionamento, sem sobrescrever bytes aceitos. O oráculoA150 também
usará essa guarda. Validar criação/repetição/recusa em diretório temporário e
conferir a reproduçãoA150 sem mudar o hash do seu recibo já emitido.
