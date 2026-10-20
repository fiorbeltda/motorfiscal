# Preparação 75 — fragmentos de identidade estrangeira

## Estado em 15/10/2026

`DpsForeignPartyProjector` foi acrescentado como componente de preparação,
sem chamadas em comandos ou outras classes do aplicativo. Não altera o
projetor doméstico, não publica bundle e não gera o par documental fiscal.
Os seis arquivos vinculados pelo relatório técnico 74 continuam com os
mesmos hashes. A suíte completa 74 de 2.306/45.937 continua sendo aceitação
histórica daquele corte, não regressão completa do código novo 75.

## Fonte observada e distinção de ambiente

Em 15/10 foram recapturados os portais RTC/homologação e o ZIP publicado no
link de homologação. O ZIP manteve SHA-256
`6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc`
e os quatro membros do fechamento 74. Modos comExt ainda 0–4; não contém
nFatura/vFatura/nContCambio/nDUIMP/nDUE/vAjusteBCIBSCBSComExt. A data anunciada
do pacote 2 não foi convertida em prova de disponibilização de schema ou
implantação remota. A observação está preservada no D: em
`phase4b-document75-schema-observation-20261015/fresh-schema-validation.json`.
Portal: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita

Os tipos exatos de pessoa, endereço e texto foram registrados antes da
implementação em `storage/app/phase4b-document75-foreign-party-preparation-20261014/`.
Esse nome de diretório conserva a data da primeira inspeção; a observação
nova de 15/10 é separada, sem sobrescrever a fonte de 14/10.

## Contrato do componente

O contrato `DPS-FOREIGN-PARTY-CANDIDATE-2026.10.15-75` produz apenas fragmentos
nos caminhos observados `toma` ou `IBSCBS.dest`, com NIF OU motivo de ausência,
nome e endereço estrangeiro. Não aceita CPF/CNPJ substituto, dados nacionais
misturados, informações extras silenciosamente descartadas, controle XML,
identificação ambígua ou vínculos de evidência ausentes. A ordem dos elementos
segue o XSD preservado. O código 0 de cNaoNIF descreve ausência na nota de
origem e não é tratado como dispensa comprovada para esta entrada de emitente.
Isso não significa que 0 seja estruturalmente inválido em todo uso do XSD.

O XSD deixa `end` opcional em alguns tipos; este contrato de preparação pede
endereço completo explicitamente. Não converte essa opção do componente em
obrigatoriedade fiscal universal. Cada nome/endereço respeita seu tipo real:
TSNomeRazaoSocial do tomador é xs:string até 300 caracteres, enquanto o
destinatário usa TSDesc150 derivado de TSString. NIF aceita Unicode conforme
o tipo observado; textos não representáveis em campos restritos são recusados,
nunca transliterados ou truncados. XML é escapado por DOM, sem concatenação.

O resultado preserva hash canônico da entrada, hash do fragmento e os três
vínculos de evidência. Declara falsos: qualificação em tabela de países,
autenticação da identidade, prova de residência, obrigação documental,
validação nativa de documento completo e aptidão para produção. `ZZ` pode
corresponder ao pattern de duas letras sem ser país qualificado: a distinção
está explícita e tem teste. O chamador futuro precisa qualificar esses fatos
e o contexto tributário, não promover o fragmento apenas porque foi projetado.

## Verificação executada

- 34 casos novos de componente, incluídos no corte dirigido final de 75 testes
  e 421 assertions (há testes históricos do comando e avaliador nesse total).
- Seis escolhas NIF/ausência nos dois papéis são conferidas permanentemente
  contra um envelope completo e o XSD nativo, além de igualdade determinística.
- Corpus nativo separado: oito envelopes estruturais válidos e sete corrupções
  rejeitadas. Os envelopes alteram pessoas de uma base sintética; não são
  decisões tributárias integradas de exportação e não foram transmitidos.
- PHPStan dirigido aprovado e Pint dos três arquivos PHP aprovado.
- Auditor de componente conferiu logs, hashes, entradas, vínculos, XMLs, fontes
  de 15/10, base anterior intacta e ausência de chamador fiscal ativo.

Relatório exclusivo:
`storage/app/phase4b-document75-foreign-party-native-v3-20261015/foreign-party-component-validation.json`.
Os logs/JSON de checks continuam no diretório 71 com labels scope75 distintos.

Primeiros erros preservados: duas expectativas dos testes aplicavam ao nome
do tomador a restrição de TSString que seu tipo não herda. A inspeção oficial
confirmou a diferença; os testes foram corrigidos e ampliados, sem restringir
indevidamente o runtime. Um check posterior apontou ordem de imports; a
correção foi arquivada e a regressão final repetida. Cada corpus nativo v1/v2/v3
continua separado; v3 inclui entrada exata e hash para reprodução da prova.

## Próximas condições para integração

Qualificar país/residência/identificação e dados reais por fonte/competência;
determinar tratamento ISS separado de IBS/CBS e os valores documentais;
vincular ambos os documentos à transação e às parcelas; provar obrigação e
schema por ambiente/data; integrar e revalidar as duas rotas sem transformar
COMEX em exportação ISS automática. O schema observado ainda não prova a
migração de consumo misto NT009. Não há aceitação integral 75 nem 4B.
Produção continua bloqueada.
