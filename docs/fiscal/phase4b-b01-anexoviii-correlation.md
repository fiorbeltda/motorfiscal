# B01 — Anexo VIII v1.02.00 como referência versionada (Agente B, 03/11/2026)

Frente B (4B.5–4B.8) da execução com dois agentes. Base: `codex/fiscal-engine-bootstrap`
em `87d22e70787b128a97e5aed4b175a39e619798af`, clone exclusivo do Agente B, sem commit.

## Requisitos abrangidos

- 4B.6.1 (materializar e versionar), 4B.6.5 (correlação oficial quando existir),
  4B.6.8 (`OFFICIAL_CORRELATION`), 4B.8.4 (catálogos versionados).
- Nenhum requisito é encerrado por este corte. O Anexo VIII continua sem autoridade decisória.

## Fonte oficial consultada

- Página: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc
  (Portal Nacional da NFS-e), "Atualizado em 26/10/2026 15h52", consultada em 03/11/2026.
- A página lista "Anexo VIII versão 1.02.00 (26/10/2026)" e informa que o Anexo VIII é
  trabalho inicial, sem regras de negócio baseadas nele nos ambientes indicados.
- Arquivo: `anexo_viii-tabela-correlacao-v01-02-00-20261026.xlsx`, 151.656 bytes,
  SHA-256 `12cc80bc92687856b47e961b98cdbaa3f8cae568c099b3ef71994e73482ac7d0`,
  obtido em 2026-11-03T12:04:30Z e preservado em `resources/fiscal/sources/official/`.
- Revalidação histórica: o arquivo preservado
  `anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx`
  (`a21be0e8…2fb3`) tem bytes idênticos ao download oficial atual.
- Ambiguidade registrada, não resolvida: na página, o título "1.01.00" aponta para
  `…_v1-00-00.xlsx` (`86c3e30f…26bf`) e o título "1.00.00" aponta para `…_v1-01-00.xlsx`.
  Nenhuma planilha declara versão internamente. O rótulo histórico 1.01.00 foi preservado.
- Pacote XSD: a página continua oferecendo apenas `nfse-esquemas_xsd-rtc-v1-00-20251210.zip`
  (NT004). Não há XSD oficial publicado com os campos NT009; a recusa XSD do 143 permanece
  coerente com a fonte oficial observada em 03/11/2026.

## Entrega

- `scripts/materialize_phase4b_b01_anexoviii_correlation.py`: mesma regra literal da
  materialização 1.01.00 (aba `tabela geral`, NBS/INDOP/cClassTrib preenchidos, sem herança).
- `resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json`
  (SHA-256 do arquivo `3835aa14…a3d3`; hash canônico `839607a1…4a48`):
  251 correlações completas e 1.358 linhas não vazias incompletas não preenchidas.
  Frente a 1.01.00: 72 adicionadas, 21 removidas, 179 inalteradas (chave LC116/NBS/cIndOp/cClassTrib).
  Colunas E/F mudaram de `PS ONEROSA?`/`ADQ EXTERIOR?` para `COD_ATIV_SN`/`DESCRIÇÃO ATIV_SN`;
  nova aba `REGRA OP NÃO ONE_EXT`.
- `App\Modules\Fiscal\ConsumptionReform\AnexoViiiCorrelationReferenceB01`: consulta literal
  por NBS somente em `LOCAL_SANDBOX`, com vínculo de hash do catálogo e dos bytes da fonte e
  disponibilidade temporal. Ambientes oficiais retornam
  `OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN`. A ausência de linha retorna
  `NO_COMPLETE_OFFICIAL_CORRELATION` com `absence_is_not_nonincidence=true`. Todas as respostas
  carregam `decision_authority=false`, `tax_treatment_selected=false`, `cclasstrib_selected=false`,
  `cindop_selected=false` e `bundle_bound=false`.

O catálogo não é ligado a nenhum bundle e não altera o catálogo ativo 141, que continua
referenciando a correlação 1.01.00 (teste dedicado).

## Validação (PHP 8.5.12 NTS Windows, `E:\tools\php85.cmd`)

- `vendor\bin\pest tests/Fiscal/Unit/AnexoViiiCorrelationReferenceB01Test.php`: 18 testes/87 asserções aprovados.
- Oráculo independente em PHP (ZipArchive/XML, sem openpyxl): 251 linhas idênticas ao catálogo;
  1.01.00 relida idêntica às 200 correlações do catálogo ativo 141; diferença recomputada.
- Negativos: linha adulterada, escalada de autoridade (linha e catálogo), vínculo a bundle, versão
  trocada, bytes alterados/ausentes/anteriores, conhecimento anterior à observação, `PRODUCAO_RESTRITA`,
  `PRODUCAO` e NBS malformado.
- Pint `--test` aprovado nos 2 arquivos PHP; PHPStan do arquivo novo sem erros; gerador determinístico
  (dois runs com o mesmo SHA-256).
- Teste dirigido, não regressão integral.

## Limites

- Correlação não é lei nem regra decisória. Nenhuma ENGINE_DECISION_RULE foi criada ou alterada.
- A ativação em bundle, a seleção por ambiente oficial e a conciliação com as 99 regras do motor
  ficam para uma task posterior, com fonte de vigência oficial.
- O runtime é PHP 8.5.12 nativo no Windows, não o contêiner histórico (Docker indisponível: VT-x
  desabilitado na BIOS desta máquina).
