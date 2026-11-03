# A149 — local material de serviços especiais

BaseGit87d22e7; A144–A148 preservados. Escopo4B.2.3/4B.2.4/4B.2.5/4B.2.7.
Os códigos de referência descrevem localidades especiais, mas as regras
decisórias instaladas concentram-se em domicílio e presença da pessoa. Não
confundir catálogo literal com comportamento material de cada família.

A149 entregará qualificação de local por prova vinculada à operação/fornecimento:
imóvel em único município, presença pessoal integral, evento, serviço físico
sobre móvel,porto,início de transporte de passageiros,entrega documental de
carga e terminal de comunicação fixa. Não inferir local apenas pelo modo remoto
ou escolher o domicílio residual quando houver regra especial comprovada.

Exceções que exigem contrato adicional ficarão explícitas: imóvel em múltiplos
municípios requer maior área; via requer extensão territorial; presença parcial
remota requer domicílio residual; comunicação centralizada requer art11§4.
Nenhuma dessas situações receberá fallback arbitrário. A150 poderá completar
as medições sem deslocar classificação/catálogo para A.

## Manifesto de escrita exclusiva A

- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/locality149_fixture.php`
- `tests/Fiscal/Integration/ServiceLocality149Test.php`
- `scripts/agent_a/verify149.py`
- `docs/fiscal/agent-a/20261103/task-A149-manifest.md`
- `docs/fiscal/agent-a/20261103/locality149-results.md`
- `docs/fiscal/agent-a/20261103/validation-A149.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`

Somente leitura: LC214 consolidada; `ObservedGeographicReference115` e seu
snapshot/política no catálogo141; engines,classes,fatos,schemas e catálogos deB.
Saída própria é contrato de local material para integração; A não cria cIndOp,
cClassTrib,NBS ou novos valores no enum global. Nenhum escritor compartilhado
é assumido e nenhum códigoB é alterado. Classificação e projeção serão handoff.

## Fonte, aceite e limites

LC214 art11,II–IX e §§2/4/5, texto oficial reconsultado na sessão; bytes deA145
HTTP200/SHA256dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde.
URL/observação preservadas emA145/sources.jsonl. Reutilizar o snapshotIBGE115
apenas para coerência territorial observada, sem autenticar domicílio, cadastro
ou existência jurídica histórica. Registros materiais terão vínculo, data e
hash de conteúdo próprios; integridade do registro não autentica sua origem.

Aceite: positivos das oito famílias, perguntas de exceções e ausência/contradição,
documento de carga correto,prova futura/trocada/fora da data,UF incoerente,
CLI repetida e replay. Sem alíquota,crédito,incidência definitiva ou documento
combinado por iniciativa deste qualificador. Regimes/tributos usam suas datas
próprias nos contratos integradores posteriores.
Artefatos: `D:/ProjetoSaasExports/agent-a-A149-service-locality-20261103-v1`.
