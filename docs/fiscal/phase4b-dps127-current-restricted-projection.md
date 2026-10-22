# Documentos127 — projeção no XSD restrito observado20/10

Continuidade autorizada da Fase4B em22/10/2026. Parte do corte126 validado
localmente e do último aceite com regressão integral122. Esta entrega migra
efetivamente a projeção XML de três perfis regulares para o novo arquivo
oficial observado; não encerra4B.7,4B.6,4B.8 ou a Fase4B.

Bundle:`CONSUMPTION-PHASE4B-2026.10.22-127-draft1`,SANDBOX_CANDIDATE.
Não se alteraram os bundles/fontes históricos ou os padrões dos comandos.
O comando existente`fiscal:dps:project-xml` seleciona o novo projetor somente
quando o bundle vincula expressamente o contrato127.

## Fonte primária preservada

[Página de homologação](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita)
e seu[arquivoXSD20/10](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/esquemas_prod_restrita_20261020.zip).

Arquivo original:
`resources/fiscal/sources/official/dps127-restricted-xsd-observed20261022-20261020.zip`.

- Observação completa:`2026-10-22T11:13:42.341713+00:00`.
- Tamanho original:36.351 bytes,dez arquivos noZIP.
- SHA256:`25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267`.
- Raiz:`DPS_v1.01.xsd`.
- SHA256 da raiz:`fe45e5250a48e519aba89fc6a472863b8e602ed957778fb64692804933a00d0c`.

Captura,URL final,horários e hashes de todos os membros:
`D:/ProjetoSaasExports/phase4b-dps127-20261022-v1/primary-source-v2/capture.json`.
A primeira tentativa recebeuHTTP403;um User-Agent explícito permitiu a nova
captura. Não houve autenticação ou transmissão de dados fiscais.

O nome da raiz e`versao=1.01` continuam iguais aos anteriores,mas os bytes e
o conteúdo mudaram. A seleção é vinculada a contrato/hash/observação/ambiente,
não apenas ao nome do arquivo ou ao ano. A observação não cria data de
vigência jurídica,ativação remota ou encerramento da obrigação documental.

## Alterações funcionais

O materializador lê oZIP sem modificá-lo e registra236 caminhos escalares e
suas facetas. O contrato127 vincula integralmente esse inventário,as quatro
dependências da raiz e o mapeamento dos campos.

No artefato efetivamente observado:

| Campo | Caminho anterior relativo a infDPS | Caminho127 |
| --- | --- | --- |
| finNFSe | IBSCBS.finNFSe | finNFSe |
| CST | IBSCBS.valores.trib.gIBSCBS.CST | IBSCBS.valores.trib.CST |
| cClassTrib | IBSCBS.valores.trib.gIBSCBS.cClassTrib | IBSCBS.valores.trib.cClassTrib |
| cNBS | após xDescServ em cServ | antes de xDescServ em cServ |

`indDest` continua em`IBSCBS` nesseXSD; não foi deslocado com base apenas em
um leiaute descritivo. O novo`indFinal` é opcional nesse arquivo e não é
inferido automaticamente deB2B/B2C. A faceta de`finNFSe` aceita apenas`0`;
este arquivo não foi ampliado para simular notas de débito/crédito.

`DpsLayoutPolicy127` qualifica os campos na decisão e nos caminhos preliminares
de importação. Sua remoção não desliga a guarda na versão127. As políticas
119/122 e a referência126 permanecem obrigatórias,mesmo com os marcadores
removidos. Bundles anteriores não recebem a nova etapa de trace retroativamente.

`DpsXmlProjector127` reutiliza as guardas de identidade,regime,partes,valor e
retenções do projetor anterior e reposiciona somente os nós comprovados pela
nova fonte. Não troca os fatos ou a decisão fiscal para obterXML válido.
O comando confere replay integral do snapshot antes de projetar e escreve o
arquivo final somente depois da validação noZIP original.

O seletor exige ambiente2,observação anterior à emissão/conhecimento,
competência coerente,série compatível e política/facetas intactas. Produção,
timestamp inválido,emissão futura,prova anterior à observação,identidade
conflitante e snapshot adulterado são recusados semXML final ou candidato.

## Validação observada

- 305 testes dirigidos aprovados/1.310 verificações,exit0.
- Inclui127,comandos/projector históricos74/76,cIndOp126,crédito122 e assessor.
- PHPStan geral sem erros com1G;Pint geral617 arquivos aprovados.
- Três perfis:federal,educação e atendimento presencial.
- Corpus:12 decisões/12 replays próprios,metade126 e metade127,nos dois
  executores. Comparação independente da decisãoRTC inteira,permitindo apenas
  versão do catálogo e metadados/caminhos documentais expressamente declarados.
- SeisXML válidos noXSD original,dois por perfil,com bytes iguais em cada par.
- Todas as fontes de cenário preservam integralmente seus fatos tipados;
  novos cabeçalhos de conhecimento e identidades sintéticas são explícitos.
- Doze snapshots históricos122 preservados reproduzidos exatamente;essa
  amostra não representa uma nova execução dos1441 históricos.
- Auditor independente:285 conferências de bytes,dependências,decisões,
  XMLs,facetas e gates. Inventário atual de99 hashes de runtime registrado.
- 106 fontes válidas;129 bloqueios de produção. `ready=false`,
  `eligible_for_local_validation=true`.

Não foi repetida a suíte integral. 122 continua o último aceite integral
9061/143868;os números atuais são dirigidos ao escopo127 e compatibilidade.

Tentativas recusadas permanecem noD:. A primeira projeção passou no perfil
federal e encontrou a ordemcNBS nos outros dois;foi corrigido o projetor,
sem editar oXSD. PHPStan apontou o retorno potencialDOMNameSpaceNode doXPath;
o helper passou a aceitar somenteDOMNode. O comparador nativo recusou a
diferença adicional do hash do validador até sua inclusão explícita e limitada
na lista de mudanças documentais. Nenhuma exclusão genérica foi aplicada.

## Evidências e limites

Raiz:`D:/ProjetoSaasExports/phase4b-dps127-20261022-v1/`.
Capturas,preparação,logs terminais e tentativas recusadas ficam nessa raiz.
`native-v3` contém snapshots126/127,identidades,relatórios,XMLs e hashes.
`historical-v1` registra os doze replays preservados122.

Recibo compacto:
`storage/app/phase4b-dps127-development-20261022/dps127-scoped-validation.json`.

O corte cobre a projeção local regular doméstica nos três perfis descritos.
Não encerra documento definitivo de importação,par documental da exportação
parcial,notas de ajuste,demais grupos/perfis,campos de comércio exterior127,
cadastros ou efeitos materiais de crédito. As rotas históricas76 permanecem
reproduzíveis no seu contrato; não foram declaradas migradas para127 por uma
projeção doméstica positiva. NBS,correlação eXML válido não decidem o tratamento
fiscal por si sós. Não houve assinatura,transmissão ou liberação de produção.

Próxima frente:completar os campos/documentos internacionais e perfis restantes
no contrato atual,com base/câmbioISS e vínculos por documento quando exigidos,
seguindo as pendências da matriz80.0/8 grupos formalmente encerrados.
