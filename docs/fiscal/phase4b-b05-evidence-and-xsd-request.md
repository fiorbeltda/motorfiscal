# B05 — Evidências indispensáveis à regressão integral e pedido de XSD (Agente B, 03/11/2026)

Base `87d22e7` + B01–B04 locais, sem commit. Corte de inventário: nenhum código fiscal alterado.

## 1. XSD oficial

| Pacote | Endereço oficial exato | Versão/identificação | Situação em 03/11/2026 |
|---|---|---|---|
| Produção Restrita (usado pelo 143) | https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/esquemas_prod_restrita_20261020.zip | "NFSe-ESQUEMAS_XSD-PRODREST-v1.01-20261020"; página atualizada em 20/10/2026 18h10 | **Baixado e revalidado**: SHA-256 `25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267`, igual à cópia preservada `resources/fiscal/sources/official/dps127-restricted-xsd-observed20261022-20261020.zip`. 10 entradas; nenhuma declara `nFatura`, `vFatura`, `nContCambio` ou `vAjusteBCIBSCBSComExt`. |
| RTC NT004 | https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/nfse-esquemas_xsd-rtc-v1-00-20251210.zip | "Esquemas XSD - AnexoVI-LeiautesRN_RTC_IBSCBS-V1.01.03 - NT004 (ZIP)", listado na página RTC | Download automatizado devolveu HTML e depois 403. Cópia preservada: `resources/fiscal/sources/official/nfse-esquemas_xsd-rtc-v1-00-20251210.zip`, SHA-256 `04662996e290add36f118c4457d8efed17848eb65d64e8b7ab56c6983c2429ad`. **Pedido ao usuário:** baixar manualmente este endereço para conferir o hash. |

Conclusão: o XSD que importa para a recusa do 143 é o oficial vigente na Produção Restrita, e ele continua sem os campos NT009.
A incompatibilidade registrada pelo 143 permanece válida. Nenhum schema foi modificado.

## 2. Evidências da máquina antiga indispensáveis à regressão integral

Inventário completo (pastas, testes que as leem, arquivos citados, falhas atribuídas, hashes registrados):
`storage/app/phase4b-b05-development-20261103/evidence-inventory-full-regression.json`
(SHA-256 `ed27a93ad65c7155bb1cde0826096520b292076e812cb2e2fdb5629684919472`).

Origem histórica: `C:/xampp/htdocs/projeto-saas-motor/storage/app/<pasta>` ou, se migrada,
`D:/ProjetoSaasExports/preserved-storage-app/<pasta>`. Copiar as pastas **inteiras**, preservando bytes (sem conversão de fim de linha).

### 2.1 Indispensáveis (34 pastas lidas por testes)

| Família / finalidade | Pastas `storage/app/…` | Falhas observadas aqui |
|---|---|---|
| Antecipações 88/91 (pagamentos antecipados RTC) | `phase4b-advance88-cli-20261015`, `phase4b-advance88-preparation-20261015`, `phase4b-advance91-integration-20261015` | 60 (+ arquivos que abortam no carregamento) |
| DERE financeiro 9121 (cortes 54–63) | `phase4b-dere9121-54-preparation-20261013`, `-55-command-integration-`, `-57-no-movement-preparation-`, `-58-no-movement-integration-`, `-59-version-preparation-`, `-61-version-integration-`, `-62-period-preparation-`, `-63-period-integration-` (todas `…-20261013`) | 129 |
| Base negativa 49–53 | `phase4b-negative-base49-preparation-20261009`, `-50-closing-draft-`, `-51-current-bases-preparation-`, `-52-settlement-draft-`, `-53-command-integration-` (todas `…-20261009`) | 18 |
| Câmbio de importação/exportação 68/70/73 | `phase4b-import-fx68-integration-20261013`, `phase4b-fx70-origin-preparation-20261014`, `phase4b-partial-export-fx73-preparation-20261014` | 26 (+19 de 69 via fixture 68) |
| Realização/consolidação de títulos (TVM) 27–48 | `phase4b-private-consolidation48-draft-20261009-28` (bind D:), `phase4b-realization-consolidated-expansion47-draft-20261009-28` (bind D:), `phase4b-realization-base-draft-20261006-28`, `-consolidation-preparation-20261009-28`, `-created-rounding-closing-draft-20261008-28`, `-history-draft-20261006-28`, `-negative-history-draft-20261007-28`, `-public-deferral-draft-20261008-28`, `-shared-asset-sources-draft-20261009-28`, `phase4b-variable-yield-20261006-27` | parte das 261 falhas `json_decode(false)` |
| Documento/DPS 74/76 | `phase4b-schema74-cli-corpus-v2-20261014`, `phase4b-international76-gap-20261015` | arquivos excluídos (abortam no carregamento) |
| Simples 78 / temporal 86 | `phase4b-simples78-cli-v2-20261015`, `phase4b-simples78-integration-preparation-20261015`, `phase4b-temporal86-integration-preparation-20261015` | 12 (86) + excluídos |

Hashes já registrados em arquivos versionados: só para 4 pastas (por exemplo, os inventários de 47/48 em
`storage/app/evidence-storage-index-20261021.json`: `0c0c8925…9aac` e `17d9dcad…00fd`). Para as demais,
o recibo de cada corte na máquina antiga é a fonte do hash; o inventário acima lista os arquivos citados pelo código.

### 2.2 Binds do `compose.yaml` (somente leitura no contêiner)

`phase4b-private-consolidation48-draft-20261009-28`, `phase4b-realization-consolidated-expansion47-draft-20261009-28`,
`phase4b-realization-third-sale-integrated-draft-20261008-28`, `phase4b-realization-public-signed-release-draft-20261008-28`
em `D:/ProjetoSaasExports/preserved-storage-app/`. Nesta máquina, `FISCAL_EVIDENCE_ROOT` pode apontar para um diretório no E:.

### 2.3 Recibos para rastreabilidade (não executam testes)

| Recibo | SHA-256 registrado |
|---|---|
| `storage/app/phase4b-pair135-development-20261023/pair135-full-validation.json` | `8af4b36b…4409` |
| `storage/app/phase4b-own136-development-20261023/own136-integration-scoped-validation.json` | `12065031…0b38` |
| `storage/app/phase4b-own136-development-20261023/own136-full-validation.json` | `67258ea3…f2ef2` |
| `storage/app/phase4b-retention137-development-20261023/retention137-full-validation.json` (última integral) | `84ad1d28…5542` |
| `storage/app/phase4b-public139-development-20261026/public139-integration-scoped-validation.json` | `f433b34c…8ce3` |
| `storage/app/phase4b-public139-development-20261026/public139-pair-integration-scoped-validation.json` | `38975a4e…2097` |
| `storage/app/phase4b-currency140-development-20261027/currency140-integration-scoped-validation.json` | `257490e0…8f18` |
| 141/142/143 | já com cópia em `docs/fiscal/checkpoints/20261027-import143/` |

### 2.4 Não indispensáveis

`storage/app/accepted`, `bad`, `failed` e os snapshots `phase4b-*-20261001-*-snapshot.json` aparecem só em scripts ou
como nomes genéricos. Não bloqueiam a suíte, mas `accepted/` alimenta a última aceitação exibida pelo console.

## 3. Enquanto as evidências não chegam

A regressão integral **não** será declarada. Os testes dirigidos das tasks B e as linhas de base (com exclusões
listadas) continuam valendo como validação parcial.
