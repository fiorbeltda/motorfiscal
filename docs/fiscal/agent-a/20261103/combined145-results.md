# A145 — resultado local escopado

Base: branch `codex/fiscal-engine-bootstrap`, HEAD87d22e7. Tudo local, sem commit.
Requisitos alcançados:4B.4.6 e composição de4B.2.1/2.3/4.1/4.12/4.13/4.14.

## Comportamento entregue

`fiscal:rtc:assess-combined-supplies operation.json` recebe operação com
fornecimentos independentes especificados. Identidade, valor exato, evidência
de alocação, vínculo às partes e data do conhecimento são conferidos por item.
Cada cenário usa seu bundle instalado e passa pelos runners RTC e integrado;
suas decisões RTC devem coincidir integralmente. A composição não define nova
classificação, benefício, crédito, schema nem alíquota. Não altera arquivos B.

O exemplo sintético contém SaaS1000,00 e educação presencial1000,00, com locais
e tratamentos diferentes, mantendo provas e memórias por fornecimento:

| Fornecimento | BaseBRL | IBS estadual | IBS municipal | CBS |
|---|---:|---:|---:|---:|
| SaaS |1000,00|1,00|0,00|9,00|
| Educação com redução60% previamente qualificada |1000,00|0,40|0,00|3,60|
| Soma |2000,00|1,40|0,00|12,60|

Totais são somas dos valores arredondados por fornecimento, não aplicação de
uma taxa média à base agregada. O teste de duas linhas1,67 comprova CBS0,04,
distinta de0,03 que resultaria do arredondamento único sobre3,34. Os grupos
mantêm data,UF e município, inclusive quando diferentes. Caixa2026 permanece
separado do tributo nominal, sem transformar dispensa declarada em extinção.

Uma dependência fiscal em qualquer item mantém as avaliações individuais,
mas impede o total agregado. Nenhum subconjunto aprovado vira aprovação total.
O replay recaptura os bundles instalados e compara o relatório inteiro; rejeita
adulteração inclusive se o hash do relatório adulterado for recalculado.

## Evidência executada

- Base A144:125 testes/429 asserções em oito arquivos existentes; estado terminal0.
- A145:24 testes/96 asserções; estado terminal0. Não somar nem apresentar como integral.
- CLI real repetida: saídas byte a byte idênticas, exit0 e replay verdadeiro.
- CLI negativa de soma divergente:exit1 e recusa fiscal explícita; totais ausentes.
- Seis conferências independentes com `decimal.Decimal`, por item e tributo.
- PHPStan dos dois arquivos de produção:zero erros. Pint dos quatro PHP próprios:PASS.
- Fontes oficiais reconsultadas:HTTP200, bytes/hash iguais às capturas históricas.

Comandos e hashes: `validation-A145.json`; oráculo reproduzível:
`python scripts/agent_a/verify145.py`. A CLI pode ser reproduzida gerando antes
o ensaio com `php scripts/agent_a/combined145_fixture.php` no contêinerA.
XML JUnit em `storage/app/agent-a-A144-baseline.junit.xml` e
`storage/app/agent-a-A145-final.junit.xml`. Saídas/fontes completas no diretório
externo `D:/ProjetoSaasExports/agent-a-A145-combined-supplies-20261103-v1`.

## Limites e handoff

A relação principal/acessório do art7,II exige qualificação própria; esta versão
não presume independência nem arbitra valores omitidos. Moedas estrangeiras,
entrada/crédito, importação e recolhedores distintos requerem suas qualificações
próprias. Parâmetros futuros continuam dependências dos runners de cada item.

A projeção integrada individual é preservada no snapshot, mas não se afirma
documento combinado completo nem aceite oficial. O contrato devolve
`combined_document_projection=PENDING_AGENT_B_INTEGRATION`, sem assinatura,
transmissão,extinção,apropriação ou autorização de lançamento. B recebe proposta
para consumir `supplies[*]`, memórias, grupos e hashes; recebimento não comprovado.

4B.4.6 permanece parcial até qualificar abrangência e integração aplicáveis;
0/4 gruposA e0/8 grupos globais formalmente encerrados nesta retomada.
Próximo incremento: qualificar a exceção principal/acessório com sua prova e
testes próprios, sem repartir preço ou benefício por suposição.
