# Documentos128 — adquirente estrangeiro no schema atual

Continuidade da Fase4B sobre127,sem reiniciar ou substituir o aceite integral122.
Bundle:`CONSUMPTION-PHASE4B-2026.10.22-128-draft1`,SANDBOX_CANDIDATE.
Este corte migra a projeção de adquirente estrangeiro com destinatário noBrasil
para oXSD20/10 já integrado na127. Identidade estrangeira não qualifica uma
operação como exportação,importação ou consumo no exterior.

## Fonte e contrato

Em22/10,a[página oficial de homologação](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita)
e o[arquivoXSD20/10](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/esquemas_prod_restrita_20261020.zip)
foram recapturados. Os bytes continuam iguais aos preservados127.
SHA256 doZIP:`25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267`.

Recibo da nova observação:
`D:/ProjetoSaasExports/phase4b-foreign128-20261022-v1/primary-source-v1/capture.json`.
Não se editou o arquivo oficial nem se duplicaram fontes como novas regras.
A versão128 reutiliza os106 arquivos oficiais vinculados e as236 facetas127.

`DpsForeignPolicy128` vincula exatamente o contrato da parte/COMEX e a política
de schema127. Na128,a política nova e as herdadas127/126/122/119 continuam
obrigatórias mesmo quando seus campos são removidos. A entrada não adquire
aprovação fiscal/documental por preencher um objeto ou por produzirXML válido.

## Implementação funcional

Os componentes de parte estrangeira eCOMEX passaram a aceitar o contexto128
explicitamente qualificado,usando o hash atual nas validações. O contrato75/76
continua separado e reproduzível com seus próprios hashes e resultados.
Nenhum dado de proveniência foi substituído artificialmente pelo hash antigo
para contornar a nova validação.

O comando`fiscal:dps:project-xml` reconhece128 e exige replay integral do
snapshot antes de projetar. O projetor127 recebe o contexto estrangeiro128,
preserva as guardas de identidade/valores e mantém finNFSe,CST/cClassTrib e
ordemcNBS nas posições efetivamente encontradas no arquivo atual.

A projeção inclui:

- Tomador estrangeiro comNIF ou motivo1/2 de ausência,em escolha exclusiva.
- Nome,endereço exterior,código de país e vínculos de evidência explícitos.
- Destinatário doméstico distinto,sem transformá-lo no tomador estrangeiro.
- GrupoCOMEX com modo,vínculo,moeda,valor,mecanismos de apoio,movimentação
  temporária/documentos associados e opçãoMDIC fornecidos explicitamente.
- Facetas e ordem do schema observado,sem emitir enriquecimentos nacionais
  como se fossem campos de decisão ou inventar dados ausentes.

Moeda/valor devem coincidir literalmente com os fatos originais;não há conversão
nova ou valor fiscal padrão. Os códigos de moeda já preservados são referências
observadas,sem alegação de validade por competência. A inserção do grupoCOMEX
não prova materialmente o modo de prestação,domicílio,autenticidade ou obrigação.

Produção,escolhaNIF conflitante,evidência ausente,partes inconsistentes,
campos não mapeados,moeda/valor divergentes,tipo monetário inválido,documentos
de movimentação temporária incoerentes,emissão fora do corte e snapshot
adulterado são recusados sem arquivo final ou candidato.

## Validação observada

- 394 testes dirigidos/1.676 verificações aprovados,exit0.
- Inclui128 e compatibilidade75/76,74,127,126,crédito122 e assessor.
- PHPStan geral sem erros com1G;Pint geral621 arquivos aprovados.
- Seis cenários:três opções de identificação estrangeira e controles
  domésticos federal,educação e presencial.
- 24 decisões/24 replays próprios:12 atuais128 e12 comparações127 nos dois
  executores. DecisãoRTC inteira idêntica exceto a versão explícita do catálogo;
  decisõesISS e federais também conferidas integralmente na comparação.
- 12 XML locais XSD-válidos,dois por cenário,com bytes idênticos por par.
- Fatos tipados originais integralmente preservados. Conhecimento/identidade
  sintéticos do novo corte declarados,sem substituir fatos monetários ou fiscais.
- Exemplo estrangeiro:ISS20.00,IBS1.00,CBS9.00 mantidos;isso é testemunho
  sintético da rota doméstica com destinatárioBR,não resultado fiscal de exportação.
- 24 snapshots históricos preservados reproduzidos exatamente:12 do122 e12
  dos cortes126/127. Não é nova execução dos1441 históricos ou da suíte integral.
- Auditor independente:500 conferências;102 hashes atuais de runtime registrados.
- 106 fontes válidas e130 bloqueios. O novo bloqueio explicita os limites da
  parte estrangeira/COMEX,todos os129 bloqueios127 anteriores preservados.
  `ready=false`, `eligible_for_local_validation=true`.

## Evidências e matriz atual

Raiz:`D:/ProjetoSaasExports/phase4b-foreign128-20261022-v1/`.
`native-v1` contém snapshots,identidades,XMLs,relatórios e hashes.
`final-directed-v1`,`phpstan-v1`,`pint-final-v1`,`production-assess-v1` contêm
comandos e resultados terminais. Os dois diretórios históricos registram
inventários originais e replays. A auditoria independente confere também
identificação exclusiva,textos,COMEX literal,partes distintas e provas vinculadas.

Recibo compacto:
`storage/app/phase4b-foreign128-development-20261022/foreign128-scoped-validation.json`.

Matriz consolidada após126–128:
`storage/app/phase4b-foreign128-development-20261022/requirements80-current-matrix.json`.
Os80 IDs/textos e as revisões próprias foram preservados;cinco pendências
receberam atualização de escopo posterior. As17 correções históricas não foram
apagadas ou substituídas,e nenhum requisito foi promovido a encerramento formal.
SHA256:`ce9fbb59fc2d3063493e20f9f446601fc11c91c4f642f27cb29852387bb6fc6a`.

## Limites e continuidade

O corte migra a rota com adquirente estrangeiro e consumo/destinatário doméstico;
não encerra os documentos de importação ou o par da exportação parcial.
Também permanecem ajuste,cancelamento/substituição,demais perfis,base/câmbioISS
por documento,validade material dos parâmetros,crédito completo e operações
combinadas,conforme a matriz80. Esses mínimos4B não foram transferidos àFase5.

122 permanece último aceite integral;126–128 têm validações delimitadas.
0/8 grupos formalmente encerrados. Não houve assinatura,autenticação remota,
transmissão,commit oupush. Continuar documentos/obrigações internacionais e
seus vínculos,com os cortes temporais e provas específicas de cada documento.
