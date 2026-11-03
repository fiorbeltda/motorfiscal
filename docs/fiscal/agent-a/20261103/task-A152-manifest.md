# A152 — contratação centralizada e domicílio da matriz

Agente A. Base: remoto `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, HEAD `87d22e70787b128a97e5aed4b175a39e619798af`.
Alterações A144–A151 preservadas, com pacote final A151. Processos observados
no contêiner exclusivo: somente php-fpm; nenhuma suíte viva a reiniciar.

Requisitos: 4B.2.4, 4B.2.5, 4B.2.10, 4B.3.2 e 4B.3.9.
Efeito adicional em 4B.2.7 pela ligação explícita do serviço pessoal remoto.
Estado existente: A151 qualifica estabelecimento atendido, mas guarda
centralização como dependência explícita. A149 exige qualificação adicional
para comunicação fixa centralizada. Não foi encontrado contrato atual para
vincular matriz, regime de ambos os tributos e ausência de vedação nessa escolha.

Aceite: opt-in em `EntityDomicile151` para a exceção do art. 11, §4. Exigir
aquisição centralizada por adquirente nacional, regularmente sujeito a IBS e
CBS; mais de um estabelecimento ativo, matriz única e vinculada; aquisição e
estabelecimentos abrangidos identificados; qualificação externa explícita de
ausência de vedação à apropriação de IBS e CBS. Qualificação ausente, parcial,
contraditória ou não vinculada exige pergunta. Selecionar matriz, sem transformar
saldo de crédito, pagamento, suspensão temporária ou crédito zero em vedação.

Abranger as famílias do §4: comunicação fixa, locação de bem móvel material e
hipóteses residuais do inciso X, incluindo serviço pessoal remoto. Provar o
escopo de cada família; não aplicar a exceção a serviço especial não abrangido.
Contratos novos, exclusivos de A:
`RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152` e
`RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152`.
Entradas antigas sem opt-in conservam saídas e perguntas existentes.

## Caminhos exatos de escrita A

- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/centralized152_fixture.php`
- `tests/Fiscal/Integration/CentralizedDomicile152Test.php`
- `scripts/agent_a/verify152.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A152-manifest.md`
- `docs/fiscal/agent-a/20261103/centralized152-results.md`
- `docs/fiscal/agent-a/20261103/validation-A152.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

Somente leitura: `CreditDecisionEngine`, `CreditInput122`,
`InputCreditTreatmentQualifier`, políticas e catálogo 141, geografia 115,
contratos de fatos, núcleo/replay e todos os recibos anteriores.
Nenhum caminho compartilhado ou de escrita de B.

## Fronteira com B

`credit_restriction_evidence` é envelope de consumo próprio A, ligado à mesma
operação/partes/data, com referência opaca de qualificação externa e predicados
IBS/CBS. Não se cria contrato/enum de B nem se altera o motor de crédito. A
consome o predicado, não o deduz de valores ou de estados ALLOWED/CONDITIONAL.
Ensaios identificam a origem sintética; não afirmar recebimento de B, autenticidade
ou integração. Dados reais continuam dependentes da qualificação de B.

Fontes: LC214 art.11 §4 I/II e Decreto12955 art.12 §4, versões atualizadas
da Câmara consultadas nesta sessão. Capturar bytes/hash/observação, comparar com
A145 e preservar a diferença entre o alcance IBS/CBS da LC e o regulamento CBS.
Não promover norma de um tributo a fonte do outro.

Validações: quatro famílias, matriz versus filial/terminal, regimes distintos,
proibição por tributo, prova desconhecida/parcial, identidade/data/vigência,
matriz ausente/duplicada/fechada, contagem de estabelecimentos ativos, cobertura,
precisão de instantes, texto de IDs, replay adulterado, ligações opcionais A149,
CLI repetida/recusa, oráculo independente, Pint/PHPStan e regressão dirigida.

Evidências exclusivas no D:
`D:/ProjetoSaasExports/agent-a-A152-centralized-domicile-20261103-v1`.
Caminhos previstos: `preflight.json`, `LC214.html`, `CBS.html`, `sources.json`,
as quatro classes/comandos `*-before.php`, `native-cases-v1.json`,
`cli-first.json`, `cli-second.json`, `cli-invalid.json`,
`cli-fixed-first.json`, `cli-fixed-second.json`, `cli-personal.json`,
`cli-A149-after.json`, `cli-A150-after.json`, `cli-A151-after.json`,
`cli-A151-service-after.json`, `cli-exits.json`,
`directed-v1.log`, `directed-v1-exit.json`, `agent-a-A152-directed-v1.junit.xml`,
`regression-v1.log`, `regression-v1-exit.json`, `agent-a-A152-regression-v1.junit.xml`,
`pint-v1.log`, `pint-v1-exit.json`, `phpstan-v1.log`, `phpstan-v1-exit.json`,
`closure-v1.json`, `source-package-final/manifest.json` e cópias inventariadas.
Não sobrescrever tentativas nem recibos aceitos.

O primeiro corte dirigido passou 183 testes/718 asserções. Acrescentar duas
contradições de centralização na ligação pessoal remota, nos dois sentidos.
Próximos artefatos sem substituir v1: `directed-v2.log`,
`directed-v2-exit.json`, `agent-a-A152-directed-v2.junit.xml`,
`pint-v2.log`, `pint-v2-exit.json`. Código de produção e casos nativos
permanecem os mesmos; a regressão acumulada será executada após esse complemento.
Oráculo adicional por CLI: `moved-headquarters-input.json`, `cli-moved.json`,
`moved-cli-exit.json`. Reatribuir explicitamente o papel de matriz à unidade
do RJ, recalcular os hashes dos registros sintéticos e conferir que o destino
acompanha esse registro; não aceitar uma resposta fixa em SP.

Proposta de atualização por ID no índice próprio e handoff ao usuário para B.
Matriz/console globais, classificações, créditos, documentos e fechamento 4B
continuam fora da escrita de A. Nenhum commit/push/PR/merge/tag/deploy.
