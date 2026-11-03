# A153 — domicílio pessoal e critérios de não cadastrados

Agente A. Base remota `https://github.com/fiorbeltda/motorfiscal.git`, branch
`codex/fiscal-engine-bootstrap`, HEAD `87d22e70787b128a97e5aed4b175a39e619798af`.
A144–A152 locais preservados. Contêiner exclusivo com php-fpm, sem suíte viva.
Conferir hashes de todos os arquivos do recibo A152 e guardar as quatro classes
antes da alteração. Não editar matriz/console, catálogo, crédito ou documento de B.

Requisitos: 4B.2.4, 4B.2.5, 4B.2.6, 4B.2.7, 4B.2.10 e 4B.3.2.
Estado: engine central já exige dois tipos/IDs de critérios concordantes para
não cadastrados; não é funcionalidade ausente. Falta nesta frente o vínculo
material por operação, escolha explícita do fornecedor, prova da impossibilidade
e domicílio de PF. A151/A152 guardam essas hipóteses como dependências.

Contrato opt-in de A: `RTC-MATERIAL-PARTY-DOMICILE-2026.11.03-A153`, entrada
`party_domicile_qualification`. Três métodos:

- PF regularmente cadastrada: residência permanente única ou local de relações
  econômicas mais relevantes se nenhuma ou várias; confronto com cadastro.
  Consumidor deve estar explicitamente fora do contexto do art.105 do Decreto;
  PF de atividade econômica exige qualificação própria, sem escolher silenciosamente
  entre habitação e estabelecimento. Não inferir essa condição do CPF/CNPJ.
- Não cadastrado: ao menos dois tipos não conflitantes escolhidos expressamente
  pelo fornecedor. Respeitar os quatro tipos existentes. Dois endereços de ruas
  ou números diferentes não se tornam iguais por pertencerem ao mesmo município.
  IP/geolocalização pode ter resolução municipal explícita; não inventar rua.
  Critério não escolhido divergente é preservado como tal, sem exigir unanimidade
  que a lei não estipula. Registro não escolhido não qualificado fica identificado,
  sem ser promovido a prova da escolha.
- Alternativa do endereço declarado: prova de impossibilidade com cobertura dos
  quatro critérios; enumerar pares disponíveis e impedir a alternativa se existir
  par não conflitante. Endereço declarado deve existir e ser único na evidência.
  Não converter IP isolado, omissão ou conveniência em impossibilidade.

Vínculos de identidade, datas, observação, integridade e geografia observada
permanecem obrigatórios. Respeitar o endereço de arranjo de pagamento do adquirente:
não usá-lo como prova automática do destinatário distinto. Não autenticar fonte
externa, criar cadastro ou determinar incidência/alíquota/crédito.

## Caminhos exatos de escrita exclusiva A

- `app/Modules/Fiscal/ConsumptionReform/PartyDomicile153.php`
- `app/Modules/Fiscal/ConsumptionReform/EntityDomicile151.php`
- `app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php`
- `app/Console/Commands/QualifyEntityDomicile151Command.php`
- `app/Console/Commands/QualifyServiceLocality149Command.php`
- `scripts/agent_a/party153_fixture.php`
- `tests/Fiscal/Integration/PartyDomicile153Test.php`
- `scripts/agent_a/verify153.py`
- `scripts/agent_a/reconcile144.py`
- `docs/fiscal/agent-a/20261103/task-A153-manifest.md`
- `docs/fiscal/agent-a/20261103/party153-results.md`
- `docs/fiscal/agent-a/20261103/validation-A153.json`
- `docs/fiscal/agent-a/20261103/requirements44-reconciliation.json`
- `docs/fiscal/agent-a/20261103/handoff-B.md`

O novo arquivo é um trait privado de A para separar a qualificação material do
despachante A151 e reutilizar suas guardas de registro/data/identidade. Nenhuma
assinatura pública global alterada. A149 aceita relatório A153 requalificável
no serviço pessoal remoto, contrato `RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153`.
Entradas sem opção nova preservam o comportamento anterior. Opções de domicílio
incompatíveis não podem ser combinadas com a contratação centralizada.
Também recusar os registros legados de cadastro/estabelecimento no mesmo input
do perfil A153, evitando que estados cadastrais contraditórios sejam ignorados.

Somente leitura: engine/factories centrais, fatos, catálogo141, geografia115,
regulamentos e recibos históricos. O handoff para B especificará endereço/parte,
método, critérios escolhidos/não escolhidos, limitações e exemplos, sem alterar
o tratamento dos fatos globais ou declarar integração com B.

Fontes primárias consultadas: LC214 art.11 X, §3 I a/II/III e §5; Decreto12955
art.12 §3 e art.104–107, incluindo distinção de PF no art.107 I/II. Conservar
as versões e diferenças de alcance; não promover o regulamento CBS a fonte IBS.
Capturar bytes e comparar com A145/A152 sem substituir as capturas anteriores.

Validações: residências zero/uma/várias, relevância econômica qualificada,
cadastro divergente/fora de vigência, papéis e condição cadastral; pares de
tipos distintos, divergência de endereço dentro da mesma cidade, terceiro
critério não escolhido, IP sem endereço inventado, alternativa com prova de
indisponibilidade e alternativa proibida quando existe par; temporalidade,
IDs textuais, hashes/replay adulterados, CLI repetida/recusa e ligação remota,
oráculo independente, Pint/PHPStan e regressão dirigida acumulada.

Evidências exclusivas:
`D:/ProjetoSaasExports/agent-a-A153-party-domicile-20261103-v1`.
Previstos `preflight.json`, `LC214.html`, `CBS.html`, `sources.json`, quatro
arquivos `*-before.php`, `native-cases-v1.json`, `cli-first.json`,
`cli-second.json`, `cli-invalid.json`, `cli-service-first.json`,
`cli-service-second.json`, `cli-A149-after.json`, `cli-A150-after.json`,
`cli-A151-after.json`, `cli-A151-service-after.json`, `cli-A152-after.json`,
`cli-A152-service-after.json`, `cli-exits.json`, `directed-v1.log`,
`directed-v1-exit.json`, `agent-a-A153-directed-v1.junit.xml`,
`regression-v1.log`, `regression-v1-exit.json`,
`agent-a-A153-regression-v1.junit.xml`, `pint-v1.log`, `pint-v1-exit.json`,
`phpstan-v1.log`, `phpstan-v1-exit.json`, `closure-v1.json`,
`source-package-final/manifest.json` e cópias inventariadas com hash.
Novas tentativas recebem novos caminhos; recibos aceitos não são sobrescritos.
Análise estática final após completar os metadados normativos:
`phpstan-v2.log`, `phpstan-v2-exit.json`; preservar o primeiro corte aprovado.
O corte dirigido v1 passou 252/943. Completar duas provas positivas para PJ e
entidade sem personalidade não cadastradas; ensaio nativo adicional de PJ.
Artefatos finais: `directed-v2.log`, `directed-v2-exit.json`,
`agent-a-A153-directed-v2.junit.xml`, `pint-v2.log`, `pint-v2-exit.json`.
Nenhum comportamento de produção foi alterado após o primeiro dirigido.
Acrescentar `cli-combined.json` e `cli-fallback.json` para exercitar os três
métodos pela CLI real, além da ligação de serviço remoto.

Sem commit/push/PR/merge/tag/deploy. Não encerrar grupo/fase a partir de
contagens dirigidas; o objetivo permanece os 44 requisitos de A e seus aceites.
