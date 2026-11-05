# Cobertura e dependências, corte de 05/11/2026

Esta rodada não encerra os44 itens A, os80 requisitos nem qualquer grupo4B.
Não trocar ausência de prova por classificação integral. A proposta individual está em
`requirements44-handoff-H10.json`; a matriz80 continua sendo consolidada por B.

| Família/perfil | Prova nova | Limite para consolidação |
|---|---|---|
| SaaS/serviço digital ordinário | Componentes estadual/municipal/CBS, matriz2026/2027/2030/2033; preço/tributos legados em testes135 reexecutados | Perfil qualificado de software não cobre todos os serviços digitais nem todos os efeitos judiciais/federais |
| Educação presencial/remota | Componentes e redução de alíquota existentes exercitados separadamente nos quatro anos candidatos | Vigência/layout futuro estendidos só em memória; não são atos futuros oficiais |
| Saúde presencial/glosa | Componentes separados, base900 após glosa e alíquota reduzida; quatro anos candidatos | Não cobre todos os benefícios/regimes de saúde |
| Importação de serviço | Livro, documento definitivo e pagamentos com datas antes do crédito IMPORT; nominal separado na matriz candidata BRL | Não há emissão/validação oficial concluída nem cobertura de todas as importações/bens materiais |
| Financeiro art192 / art193 | Seis casos2027/2030/2033, municipal sintético não zero; teste2026 e condições legais existentes na regressão | Distribuições sintéticas não são atos da União/CGIBS; tipos financeiros além dessas duas bases continuam delimitados |
| Transporte internacional de passageiros | Cálculo da metade legal do preço e guardas materiais; classificador candidato exclusivamente sintético | B deve criar classificação real própria antes de ativar; não usar cClassTrib/NBS copiados de SaaS como classificação oficial |
| Demais famílias locais A149/A150 | Testes dos qualificadores locais/territoriais na regressão | Qualificação de local não equivale a classificação/incidência/cálculo; matriz de cálculo completa depende de novas regras e fatos versionados por B |
| Operações combinadas, imunidade, cooperativas, não onerosas, plataformas e cobrança | Testes existentes relacionados na revisão44, quando incluídos no plano | Não extrapolar a matriz nominal nova a todos esses perfis; manter lacunas individuais e fontes necessárias |

## Dependências identificadas

1. **Ativação B em nova versão.** Fonte: README H6–H10 e B23 em `1fa9969`.
   Dois fatos ARRAY documentais e um fato de redução, perguntas, gates e classificação própria
   descritos em contracts-H6-H10.md. Impacto: o B23 permanece com comportamento histórico;
   código entregue não prova que a integração futura foi ativada/aceita.
2. **Documento de importação oficial.** Fonte: A143 e fontes oficiais preservadas/testadas em
   ImportDpsModel143Test, baseB23. Continuam os impedimentos de schema/regras de emissão ali
   observados. Nenhuma nova fonte compatível/documento emitido foi fornecido nesta rodada.
   Impacto: memória local e crédito qualificado não liberam obrigação documental completa.
3. **Parâmetros futuros não comprovados no acervo.** Fonte: catálogos de taxas aceitos e
   testes de recusa temporal da regressão. Não é uma afirmação de inexistência universal de atos.
   Impacto: exemplos2027/2030/2033 são candidatos SYNTHETIC_TEST; B deve obter/validar cada
   ato oficial aplicável antes de uma decisão oficial. Não inferir alíquota futura de uma soma.
4. **Fixtures históricas ausentes.** Fonte: excluded-tests.txt do B23 (41 arquivos).
   Onze arquivos da seleção histórica A154 estão nessa lista. Impacto: reexecução parcial;
   v1/v2 interrompidas de A154 não viram aprovação. Não regenerar seus corpora como se fossem
   capturas históricas. Hashes e origens recuperáveis dos três corpora86 continuam no checkpoint
   anterior, sem alteração de mounts/instalação nesta rodada.
5. **Pendências funcionais dos44.** Fonte: revisão individual H10, além da A154 original.
   PF empresarial, ciclo de cobrança/liquidação e combinações não cobertas não foram declarados
   concluídos pela aritmética ou pelo vínculo documental. São trabalho regular remanescente,
   distinguido das dependências de fonte/ativação; não classificar tudo como bloqueio externo.

Fonte legal reconsultada em05/11/2026: Câmara, LC214 consolidada,
SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`.
Snapshot bruto/recibo ficam no pacote externo A156. LC214 arts47–52,64 e12§8 sustentam os
contratos delimitados; nenhum parâmetro oficial novo foi inferido desses exemplos.
