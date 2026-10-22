# Catálogos126 — referência cIndOp com contexto explícito

Retomada autorizada em22/10/2026,a partir do commit473bfcc e do aceite122.
Entrega delimitada:materialização literal da referênciaRTC1.03.00 e separação
do AnexoC de homologação,com guarda obrigatória na nova versão126. Não encerra
o requisito4B.6.2 inteiro,nem qualquer grupo ou a Fase4B.

Bundle:`CONSUMPTION-PHASE4B-2026.10.22-126-draft1`,SANDBOX_CANDIDATE.
122 continua sendo o último aceite com regressão integral. Seus bundles,
catálogos,cenários,fontes e recibos não foram reescritos.

## Fontes e descoberta

Captura exclusiva,com URL final,bytes,SHA256 e horário de observação:
`D:/ProjetoSaasExports/phase4b-catalog126-20261022-v1/primary-source-v1/capture.json`.
Cópias oficiais imutáveis:`resources/fiscal/sources/official/catalog126-*`.

- [PortalRTC](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc).
- [Homologação/testes](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita).
- [Documentação de produção](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual).
- [AnexoVII1.03.00](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/anexovii-indop_ibscbs_v1-03-00-nt009.xlsx):
  SHA256`95f30a44ee94adeea57fb92f3a0337b1e62fdc106f393e73894087889be9e5aa`.
- [AnexoC da homologação](https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/anexo_c-indop_ibscbs-snnfse-prodrest-v1-01-20260122.xlsx):
  SHA256`f13b08dc802e82c3c43d591d06575922095595a17c94e61bc4a37c41633a744b`.

Também preservados:NT009v1.01 e leiaute de homologação20/10. As pesquisas33 e
oLEIA-ME foram consultados como especificação;contagens históricas da pesquisa
não substituem os códigos efetivamente encontrados nas fontes originais.

RTC1.03.00 tem40 códigos.OAnexoC tem26 códigos distintos;há18 somente naRTC e
quatro somente noAnexoC:`030103`,`030104`,`050103`,`050104`. Células vazias e
linhas não classificadas como códigos foram preservadas/ignoradas explicitamente,
sem preencher atributos por herança. As tabelas não foram fundidas.

A primeira observação completa deste corte terminou em
`2026-10-22T10:51:26.468337+00:00`. Isso limita o conhecimento do bundle126;
não estabelece vigência jurídica nem implantação dos anexos.

## Comportamento implementado

`CIndOpReference126` confere o contrato canônico completo,proveniência e todas
as linhas materializadas antes da avaliação. A versão126 exige essa guarda
mesmo quando o campo da política é removido. As políticas119/122 continuam
obrigatórias;remover a política de crédito não desliga sua validação na126.

O perfil`LOCAL_SANDBOX` seleciona explicitamente a referênciaRTC1.03.00.
A data da operação deve estar dentro da cobertura local herdada05/01–31/12/2026;
esse intervalo não é registrado como vigência oficial. O conhecimento deve ser
posterior à captura completa. Código desconhecido,permissão literal diferente
de`S`,fonte alterada e materialização divergente são recusados.

`PRODUCTION_RESTRICTED` identifica separadamente oAnexoC;`PRODUCTION` não tem
edição ativada. Ambos devolvem
`RTC_CINDOP126_OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN`,pois este corte
não comprovou suas datas oficiais de ativação. Não se infere supersessão de
uma tabela a partir da publicação da outra.

Integração:operação padrão e parcela doméstica da exportação parcial recebem
`CINDOP_REFERENCE126` no trace,com edição,ambiente,data,linha,aba e hashes.
Todas as rotas da126 passam pela guarda inicial`CINDOP_POLICY126`.
As novas permissões de catálogo não criam regrasENGINE_DECISION_RULE;
fornecer020102 a uma operaçãoSaaS continua gerando conflito de classificação.
Os novos indicadores de localidade/NFS-eVia permanecem literais na referência;
não são emitidos nem usados como regra fiscal automática.

O assessor acrescenta um bloqueio próprio de produção. Assinatura,transmissão,
autenticação remota e frontend de produto não integram esta entrega.

## Validação observada

- Testes dirigidos finais:205 aprovados/882 verificações,exit0.
- PHPStan geral:sem erros,com limite512M. A tentativa com128M foi recusada por
  memória insuficiente e está preservada;resultado incompleto não foi aceito.
- Pint geral:`composer lint` aprovado em611 arquivos,exit0.
- Corpus nativo:32 decisões/32 replays exatos,em oito cenários e dois executores,
  sendo16 decisões126 e16 comparações122 com os mesmos fatos/conhecimento.
- Comparador confere status,decisão,razões e fatos ausentes;somente a versão
  explícita do catálogo de códigos é ajustada na comparação da decisão.
  Não se afirma igualdade integral dos traces entre versões.
- Amostra histórica:12 snapshots122 preservados,seis cenários nos dois
  executores,com hashes originais conferidos e replay exato.
- Produção:105 fontes conferidas,129 bloqueios,`ready=false` e
  `eligible_for_local_validation=true`.

Os casos de exportação parcial com conhecimento movido para22/10 alcançam a
nova guarda da parcela doméstica e depois recusam as provas de identidade
presas ao corte anterior. São recusas comparadas,não documentos positivos.
Nenhum fato monetário,data material ou evento das fontes foi atualizado para
forçar aprovação. A suite completa9061 e os1441 históricos de21/10 continuam
evidências históricas122,não novas execuções deste corte.

Tentativas de preparação,testes e coletores recusados permanecem noD:.
Falhas iniciais incluíram códigos numéricos nas chaves do dataset e uso de
hash físico onde o manifesto exige hash canônico;ambos foram corrigidos sem
enfraquecer a validação. O coletor ampliado passou a registrar saída nãoJSON
e conferir a população esperada,sem aceitar exit0 isolado como corpus completo.

## Evidências e trabalho restante

Raiz:`D:/ProjetoSaasExports/phase4b-catalog126-20261022-v1/`.
`final-directed-v1`, `phpstan-v2`, `production-assess-v1`, `native-v5` e
`historical-v1` contêm logs,recibos terminais,snapshots e hashes.
O recibo compacto do corte é
`storage/app/phase4b-catalog126-development-20261022/catalog126-scoped-validation.json`.
O auditor independente lêOOXML diretamente,sem reutilizar o materializador
openpyxl,confere as duas tabelas e preservação dos recursos anteriores.
Recibo:319 conferências;91 hashes anteriores de runtime intactos e exatamente
três alterações declaradas,além do componente novo. O console mantém o aviso
de divergência desses três arquivos em relação ao aceite integral122;esse aviso
não foi suprimido nem substituído pelo resultado dirigido126.

Restam a vigência e seleção oficial por ambiente,conciliação dos códigos
divergentes,compatibilidade integral com o schema/leiaute aplicável e as
classificações/perfis restantes. A1.03.00 foi incorporada como referência local
na126;isso não deve permanecer descrito como se nenhuma implementação existisse,
nem ser promovido a ativação oficial ou encerramento4B.6.

Os80 requisitos continuam examinados;0/8 grupos formalmente encerrados.
Próxima frente funcional:continuar a seleção documental aplicável e a
qualificação das provas por corte de conhecimento,seguindo a matriz atual.
