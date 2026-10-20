# Simples 78 — íntegra oficial localizada, ativação pendente

Em 15/10/2026, durante a suíte 76, a busca na interface pública de Normas da
Receita por número 194/ano 2026 localizou a Resolução CGSN. A página da íntegra
foi aberta pelo resultado da própria interface:

[Resolução CGSN 194/2026, ato 153801](https://normasinternet2.receita.fazenda.gov.br/#/consulta/externa/153801).

A página informa DOU de 28/09/2026, seção 1-C, página 8; o texto visível alcança
assinatura, vigência e ressalva de reprodução. Não é mais apenas snippet ou
notícia. O DOU direto continuou retornando 403. A consulta antiga retorna 200,
mas seu HTML é o shell JavaScript; não confundir isso com captura do ato.

Evidências exclusivas em
`D:/ProjetoSaasExports/phase4b-simples78-primary-research-20261015/`:

- `primary-capture-observation.json`: tentativas HTTP, URLs, status e hashes.
- `cgsn194-visible-normative-transcription.txt`: transcrição integral do texto
  normativo visível, com parágrafos normalizados e ícones/UI removidos. Não é
  HTML bruto nem fac-símile do DOU. A origem é observação DOM da interface,
  não download direto do ato. Precisa de captura nativa exportável antes de
  constituir a fonte automatizada de um novo bundle.

## Distinções obrigatórias da mudança

O art. 1º da CGSN 194 altera os arts. 1º, 2º e 3º da CGSN 186:

- Opção pelo Simples para 2027: 01/09–15/10/2026; regularização de impedimentos
  até 30/10 não equivale a prazo de opção pelo Simples.
- Opção por IBS/CBS regulares para janeiro–junho/2027: 01/09–30/10/2026,
  com efeitos a partir de 01/01/2027.
- Cancelamento das duas opções: 03/11–20/12/2026. Não basta trocar apenas
  `cancellation_through`: o início da janela também precisa ser representado.
- Início de atividade/CNPJ: exceção própria em 16/10–31/12/2026 para Simples;
  exceção distinta em 31/10–31/12/2026 para IBS/CBS regulares. A regra geral
  não pode qualificar esses casos silenciosamente, nem inferir data de CNPJ.
- A resolução entra em vigor na publicação. Conhecimento da fonte no motor
  continua temporalmente separado da publicação e dos efeitos de 2027.

O catálogo atual preserva a janela antiga e um aviso de supersessão que impede
qualificação positiva após a observação do aviso. Não editar esse catálogo ou
reescrever replays. Futuro corte deverá ter fonte própria, linha substituta,
guardas das exceções, testes das fronteiras, cenário integrado e replay histórico.
Também deverá confrontar a íntegra consolidada da CGSN 186 para não presumir
que trechos não alterados foram revogados ou substituídos.

Nenhuma janela nova foi ativada e a produção continua bloqueada. Esta pesquisa
avança uma pendência original do Simples, sem substituir a frente documental
de exportação ou declarar aceitação de 4B.1/4B.2/4B.8.

## Complemento — redação consolidada conferida

A [CGSN 186 na visão Vigente](https://normasinternet2.receita.fazenda.gov.br/#/consulta/externa/150653/visao/vigente)
foi agora lida integralmente na interface oficial. A visão inicial Multivigente
exibia textos antigos e novos simultaneamente; foi selecionada explicitamente
a visão Vigente. A leitura alcançou os cinco artigos, assinatura e ressalva.
Isso conclui a leitura normativa consolidada solicitada acima, mas ainda não
constitui ativação de política ou aceitação de todos os cenários.

Persistem o §3º do art. 1º, o parágrafo único/incisos do art. 3º e o art. 4º.
O art. 3º liga a opção ao momento da inscrição no CNPJ, com efeitos distintos
para Simples e IBS/CBS. Não usar `provider.activity_start_date` como sinônimo
de data de inscrição no CNPJ. O art. 4º exclui SIMEI dessas regras. A guarda
atual `RTC_MEI_REGULAR_ELECTION_FORBIDDEN` foi encontrada no engine e deve ser
preservada; não foi identificada uma eleição regular positiva de MEI nesse ramo.

Relatório novo: `consolidated-review-observation.json`, no diretório externo
78. Extrato dos dispositivos mantidos:
`cgsn186-retained-clauses-visible-extract.txt`, SHA-256
`fc57d9cc84184b0d2e0e16a3f14b8eb0504c32311390a638e4ce3d67bf44a15b`.
É extrato explícito, não captura integral da página. O documento anterior a
este complemento está preservado em `before-consolidated-review-doc.md`.

Antes de integrar, o corpus deverá distinguir: inscrição CNPJ em 30/10 e
31/10, 31/12 e 01/01; opção ordinária em 30/10 e 31/10; opção no ato de
inscrição e fora dele; efeitos de primeiro versus segundo semestre; SIMEI;
status confirmado/cancelado/pendente; registro ou protocolo posterior ao
conhecimento; janelas e política anteriores para replay. As datas de inscrição,
eleição e evidências precisam ser próprias, sem inferência pelo regime ou ano.
