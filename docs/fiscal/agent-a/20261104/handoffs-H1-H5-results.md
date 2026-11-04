# A155 — H1–H5 para o Agente B (04/11/2026)

Base `e3a632bb186eec18682af5c8898c10e8610f3808`, branch `codex/fiscal-engine-bootstrap`, clone `D:\ProjetoSaasWorkspaces\agente-a-fase4b-20261103`.
A144–A153 já publicados em `6c0f81b`; B74a4195 e pacotee3a632b recebidos por fast-forward local.
Desenvolvimento A154 pausado; ponto de retomada em `paused-A154.md`. Usuário autorizou commit local ao final;
o SHA será registrado no recibo externo `commit-receipt.json`, sem SHA circular dentro deste conteúdo. Sem push autorizado.

| Handoff | Entrega local e prova |
|---|---|
| H1 | B02 chamado nos ramos ordinário/importação pelo adaptador A155, gate exato; status/valores sem gate preservados. |
| H2 | Tabela Q-B06-1 implementada, 640 casos (320 × 2 runners) executados em captura de teste em memória. Sem editar catálogos aceitos. |
| H3 | B13 antes da decisão de crédito; B17 na decisão/trace, sem somar nem apropriar; CLI de apuração encadeia B11→B14 e desconta utilizações anteriores. |
| H4 | `source_kind` não oficial, `synthetic=true` e marcadores malformados recusados; B19 aprovado. Saída legada sem marcadores mantida. |
| H5 | Este relatório, recibo JSON, 44 itens individuais, patch completo e fontes novas empacotadas. |

Regressão dirigida final: **1747 testes / 69752 asserções**, sem falhas/erros/skips.
Inclui os testes próprios e as suítes H1 especificadas, componentes B11/B13/B14/B17/B18, B19, anexos, importação129/141/143,
par135, pagamento139, financeiro e ISS. O comando e hashes estão em `regression-v3-command.json` no pacote externo.
Não somar às suítes sobrepostas: dirigido anterior701/1447; smoke anterior46 aprovados e1 falha de contexto de teste corrigida.
A regressão v1 preserva quatro falhas de asserção por posição da lista corrigidas para buscar credit_id; v2 passou e v3 é o corte final.
V3 conserva o objeto B13 assinado sem acrescentar campos e preserva referências B07 STRING com espaços internos.
Compatibilidade A145–A153 no código atual: 508 testes / 1807 asserções, sem substituir recibos históricos.
PHPStan global nível8 sem erros; Pint8 aprovado. Três oráculos Python Decimal, CLI repetida byte-idêntica e recusaexit1.
Console:28 verificações aprovadas; sua consolidação continua de B, não foi atualizada por A.

**B precisa continuar:** criar novas versões de catálogo/bundle/fatos/perguntas e ativar os blocos exatos B02/B07/A155
(constantes no código; contrato em `task-A155-H1-H5-manifest.md`). Os 320 casos B07 originais permanecem pendentes
sem bundle instalado; a implementação foi exercitada pelos640 casos próprios. O teste de caracterização B16
que esperava aceitar `synthetic=true` agora falha intencionalmente e deve ser atualizado por B. Evidência preservada
em `B-characterization-change.json`; nenhum teste B foi alterado por A.
Ao criar as versões novas, B também deve restringir ao acervo histórico as asserções B02/B07 de ausência de gates;
elas passam no estado atual, mas não devem proibir a nova versão explicitamente habilitada.

Os44 requisitos de A estão detalhados em `requirements44-handoff-H5.json`, com classificação, código/hash,
testes, recibos históricos e lacunas. Isso permite consolidar avanços parciais; não equivale a fechar os44 itens.
H1–H4 removem as dependências de implementação do A descritas nos handoffs, com ativação/integração de B ainda pendente.

Interfaces: H1 adiciona `zero_semantics_version`, `zero_causes`, `amount_semantics` por componente quando habilitado.
H2 usa os3 fatos novos B07 e registra `RTC_ANNEX_NONCORRESPONDENCE_QUALIFIED`. H3 usa os fatos do manifesto,
`credit_integration155` na decisão/trace e `RTC-PERIOD-CREDIT-ASSESSMENT-2026.11.04-A155` na apuração separada.
Valores IBS/CBS ordinários e presumidos permanecem separados. Ledger completo inclui ajustes e utilizações anteriores;
preservam-se datas de apropriação, restrições de ressarcimento e segregação de tributos. Não há posting/extinção/autenticação.

Fontes oficiais revalidadas: Câmara LC214, SHA`dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`; bytes iguais ao snapshot anterior.
Planalto pela ferramenta web recusou acesso; snapshot bruto publicado por B foi preservado e seus testes de trechos executados.
Não houve taxa futura, parâmetro oficial, cadastro ou benefício inventado.

Pacote externo: `D:\ProjetoSaasExports\agent-a-A155-H1-H5-20261104-v1`. Patch: `A155-H1-H5.patch`, basee3a632b; arquivos novos incluídos.
Antes de aplicar, B confere seu HEAD/diff e executa `git apply --check A155-H1-H5.patch`; não sobrepor alterações concorrentes.
Arquivos B, factory, definições globais, XML, banco e infraestrutura não foram editados por A.
O recibo contém hashes centrais antes/depois. `source-package-final/manifest.json` inventaria as cópias.
Recebimento por Claude/B e aplicação na matriz/console ainda não comprovados; usuário é a ponte.
