# DeRE — fontes observadas em02/10/2026

Índice oficial: https://www.gov.br/sped/pt-br/assuntos/documentos-fiscais/dere/manuais-e-documentos-tecnicos/versao-atual-1. Três artefatos foram obtidos pelo endpoint oficial `@@download/file` e preservados integralmente; URLs e hashes em `resources/fiscal/sources/official/dere-source-observation-20261002.json`. Ainda não estão ativados no bundle13: o gate desse bundle permanece com20 fontes anteriores.

| Artefato observado | SHA256 |
| --- | --- |
| Manual de usuário1.1.0,62 páginas | `6677a3596771239a440e8d44b998b27e4be28c332dbb818533f84ff3319a5dba` |
| Leiautes de eventos1.2.0,124 páginas | `7adbc82a55f0d60dfc2a63771a4c65c67d479e6a50a7821079e73f38b21873c7` |
| ZIP XSD1.2.0 /produção restrita | `c750f7d522400e5bd93b7ddd1d1d0d8108cd033eb250c1f10011ac57cc732818` |

A leitura do conteúdo confirma que **D-2101 é o débito em títulos de dívida com oferta pública**, não o evento geral de crédito financeiro. Página física33 do leiaute, tabela2.6.1, verificada visualmente. D-2211 identifica adquirentes de crédito/TVM; D-2221 trata antecipação de recebíveis. Os transacionais são identificados como leiautes preliminares no documento atual; não confundir disponibilidade do pacote com vigência obrigatória comprovada.

Página física15 do manual, também verificada visualmente: D-1001/D-1011 são pré-requisitos de tabelas, D-1101 é o balancete mensal e D-1199 conclui o ciclo. Projetar uma operação isolada não homologará automaticamente a declaração mensal.

O ZIP contém28 esquemas. Inspeção inicial de `evtOperFinanc-v0_0_1.xsd`, `evtServRemTarifa-v0_0_1.xsd` e `evtAntecReceb-v0_0_1.xsd`: namespace próprio, CNPJ raiz8, chave de agrupamento53, período mensal, identificação do adquirente, detalhes e tributos por componente. Importam `xmldsig-core-schema.xsd`, não incluído no ZIP observado. O envelope exige referência de assinatura; assinatura real continua fora do escopo do projeto. Deve-se preparar projeção local sem confundir candidatura com autenticação ou aceitação operacional.

Verificador `scripts/inspect_phase4b_dere_sources.py` confere os três hashes e inventaria todas as dependências do pacote, sem extração insegura nem alteração de esquemas. Relatório `storage/app/phase4b-dere-source-inspection-20261002.json`.

Próximo trabalho: atos instituintes e efeitos/ambientes, anexos de tabelas e regras, nota orientativa do pacote, dependência XMLDSig oficial preservada, composição da chave e mapeamento entre bases do motor/eventos. A fonte foi obtida; a homologação e a projeção financeira ainda precisam de desenvolvimento.
