# Preço ISS111 — composição local verificada

Preparação concluída em19/10/2026. Não instalada no motor:108 permanece o
último aceite integrado. Os oito grupos4B continuam parciais;produção bloqueada.

## Entrega

`PartialExportIssPriceComposition111` reexecuta110 e,por ele,107/106/105 antes
de compor preços por documento. Exige contrato e política exatos,duas linhas
vinculadas,enumeração completa dos ajustes e prova independente capturada.
Confere conteúdo,envelope,contexto atual,observação,cobertura nas datasISS,
revogação e superação;recusa reuso dos IDs das provas anteriores.

O preço para avaliação ISS é principal mais acréscimos do serviço menos
descontos incondicionais comprovados localmente. Desconto condicionado não
reduz esse preço. O contrato explicita se110 recebeu bruto,líquido apenas dos
incondicionais ou líquido de todos os descontos;reconcilia a base declarada
sem subtrair duas vezes ou transportar rateio/cotaçãoRTC.

O candidato de pagamento após condições não prova recebimento ou quitação.
Preços em BRL/USD/EUR/AUD são compostos na moeda declarada;isso não qualifica
câmbio ou baseBRL. Zero líquido que viole o contrato positivo110 continua
recusado. Operações especiais,preço desconhecido/pauta,alterações de preço e
demais regimes exigem escopos próprios,não defaults silenciosos.

## Fontes e efeitos

18 capturas primárias preservadas e192 verificações de fontes/runtime.
A regra geral de preço e desconto incondicional está no
[art14 da Lei13701](https://legislacao.prefeitura.sp.gov.br/lei-13701-de-24-de-dezembro-de-2003/consolidado)
e é corroborada pelo
[guia da Fazenda](https://www2.prefeitura.sp.gov.br/web/fazenda/w/servicos/iss/2478).
Imposto integrante do preço não é uma dedução adicional. Mercado/pauta e
deduções setoriais foram separados do preço contratual efetivo de software.

A cadeia condicional da Lei18270art21/26II foi localizada na
[IN15/2025](https://legislacao.prefeitura.sp.gov.br/portaria-15-de-5-de-dezembro-de-2025/consolidado),
com publicação08/12/2025 nos detalhes oficiais e corroboração no
[FAQ da Fazenda](https://prefeitura.sp.gov.br/fazenda/w/servicos/iss/11090).
Trata de construção/DTCO,não de dedução genérica de software. O slug oficial
contém `portaria`,mas o texto e a identidade são IN SF/SUREM15;ambos preservados.

A [Lei18066](https://legislacao.prefeitura.sp.gov.br/lei-18066-de-28-de-dezembro-de-2023/consolidado)
contém alíquota2,9% para item1,exceto1.09,e17.24,com efeitos próprios.
A data28/03/2024 é derivação de calendário a partir da publicação29/12/2023,
não ativação de política. Mapeamento municipal/aplicabilidade integral ainda
pendentes. A tabela2% anterior declara ser fixture;foi preservada.

## Evidência executada

- 107 casos/2.239 verificações:50 composições locais,42INVALID,15ACTION_REQUIRED.
- 160 resultados completos110 iguais aos capturados,não apenas status.
- 400 cálculos independentes com PythonDecimal e1.793 checks na auditoria final.
- Três composições com preços/ajustes distintos por papel e três bases de entrada.
- Valores que perdem centavos em float e moedas locais de entradaBRL/USD/EUR/AUD.
- PHPStan dos dois componentes ePint dos dois arquivos111 aprovados.
- 83 hashesruntime108 e seu aceite preservados;nenhum novo testePest integrado.

Os24 vetores iniciais são oráculos de desenho,não24 testes adicionais do motor.
As fixtures novas usam conhecimento19:00UTC **sintético**,não captura futura real.
Nos positivos,fatos,provas materiais/resultados e identidades de negócio
anteriores foram preservados;somente mapeamentos/hash/observação sintéticos
107 foram rederivados,e novos preços foram declarados nas novas fixtures.
Uma prova positiva às18:00 expira antes das19:00 e permanece recusada,sem
reabrir ou adulterar o resultado anterior.

Primeiro auditor de fontes falhou por esperar redação diferente do §4º;
original e falha preservados emsource-contract-v1,seguido de revisão exata
ev2 aprovado. PrimeirosPHPStan/Pint detectaram anotação de iterable e estilo,
preservados antes da correção. Resultados antigos111 permaneceram iguais após
ampliar a bateria e formatar os dois arquivos temporários.

Evidências noD:
`D:/ProjetoSaasExports/phase4b-iss-base111-primary-research-20261019/`.
Recebofinal:`price111-local-component-validation.json`,SHA256
`a544b1224b2d5fcacf4b34d7b8e26ae2509c7249a09ad26180cf79c4127f7740`.
Execuçãofinal:`native-v5.stdout`;qualidade:`phpstan-v3` e`pint-v3`.
Fonte/contrato:`source-contract-v2/iss-base111-source-scope-review.json`.

## Limites e próximo passo

Permanecem falsos:baseISS calculada,imposto por documento,conversão cambial,
aprovação jurídica integral,autenticação governamental,XML,lançamento eprodução.
Próximo trabalho:integrar110/111 em versão própria,com contrato de transporte,
fatos/perguntas e corpus;depois compor conversão/base/imposto e parXML com
fontes próprias. Não dispensar a auditoria individual dos80 requisitos.
