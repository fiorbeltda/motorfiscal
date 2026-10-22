# Importação130 — candidato documental pelo tomador

Implementado o comando`fiscal:dps:import-candidate`,que consome snapshot
integrado129 com a razão exata`RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN`.
Exige conhecimento idêntico ao manifesto129 preservado e replay completo do
resultado,antes de projetar. Não altera o engine,o bundle129 ou o comando de
projeção de decisões finais. Contrato próprio:
`DPS-IMPORT-CANDIDATE-2026.10.22-130`.

O candidato local inclui tomadorCNPJ como emitente/identificadorDPS,
tpEmit2,cMotivoEmisTI1,prestador estrangeiroNIF/endereço/regTrib explícitos,
COMEX,NBS,e valores provenientes do cálculo realISS e da avaliaçãoRTC.
Não se inventa CNPJ brasileiro ou regimeLUCRO_REAL para o fornecedor estrangeiro.
O regTrib é um dado documental explícito,com referência de evidência.

O perfil atualmente mapeado é importação digital,100% consumoBR,adquirente e
destinatário coincidentes,valor originalBRL,sem ajustes/deduções ou retenções
federais solicitadas. Município da prestação eNBS são fatos explícitos;
não são deduzidos do municípioISS ou do cTribNac. Demais perfis exigem
desenvolvimento próprio;nenhum foi retirado do escopo original4B.

## Saída e limites

`--candidate-output` grava arquivo novo somente depois da validação real no
XSD vinculado. `--report` grava evidência imutável. Não há opção de saída
fiscal final. JSON de sucesso:`CANDIDATE_VALIDATED`,com resultado fiscal
`ACTION_REQUIRED`,fiscal_decision_final=false,document_obligation_complete=false
eproduction_ready=false. O comando anterior continua recusando o mesmo snapshot.

O relatório preserva referências da fatura/transação/câmbio e cálculos
ISS/RTC literais. Não apresenta essas referências auxiliares como se fossem
os campos novosNT009 emitidos noXML. Não conclui obrigaçãoRTC ou municipal,
apropriação/crédito,liquidação ou qualificação de base/câmbioISS por documento.
O trabalho seguinte deve resolver essas integrações;130 não substitui uma
decisão de importação completa por um XML estrutural.

## Fontes e divergência técnica

[Decreto12955,art74](https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html),
[orientaçõesRTC](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/orientacoes-da-reforma-tributaria)
e[notícia oficialNT009](https://www.gov.br/nfse/pt-br/noticias/publicado-o-ato-tecnico-conjunto-rfb-cgibs-no-7-aprovando-a-versao-1-01-da-nota-tecnica-009-nfs-e-e-a-nota-tecnica-010-da-nfs-e-via).
Os três originais estão preservados em`resources/fiscal/sources/official/import130-*`;
URLs,SHA256 e datas estão na política
`resources/fiscal/projections/import-dps-candidate-2026.10.22-130.json`.
Conhecimento mínimo da política:`2026-10-22T17:33:37.832938+00:00`.

O ZIP restrito20/10 permanece comSHA256
`25fdcecc651a67a216496ab10d7566631e6ed10f721e37d62c7845dadb0ea267`.
O leiaute observado ainda limita modalidadeCOMEX a0–4 e não contém os novos
campos de fatura/contrato cambial descritos pela notíciaNT009.130 recusa modo5
e campos não mapeados;nenhum XSD oficial foi editado. Resta qualificar a
compatibilidade entre documentos,versões,ambientes e obrigações aplicáveis.
CronogramaRTC e obrigação municipal não foram fundidos.

## Verificações

- 99 testes/333 verificações dirigidas;PHPStan geral sem erros ePint634 aprovado.
- Um snapshot integrado129 efetivamente replayado;dois candidatos com bytes
  idênticos e validaçãoXSD real. ISS20.00,IBS nominal1.00 eCBS9.00 preservados
  no testemunho sintético de1000.00;nenhuma decisão final foi produzida.
- 24 snapshots históricos127/128 com hashes preservados e replay exato.
- Auditor independente:199 conferências,106 hashes de runtime atuais,
 107 fontes do bundle129 e três fontes documentais adicionais conferidas.
- Manifesto/catálogos129 intactos;somente o projetor de parte estrangeira
 herdado mudou para permitir prestador sob política130 explícita. Os papéis
 históricos permanecem separados e foram verificados.
- Avaliação atual129:130 bloqueios mantidos,ready=false,
 eligible_for_local_validation=true. Sem nova suíte integral ou aceite integral.

Recibo:`storage/app/phase4b-import130-development-20261022/import130-candidate-validation.json`.
Evidências noD:`phase4b-import130-20261022-v1/`,incluindo`native-v1`,
`directed-final-v2`,`phpstan-v1`,`pint-v1` e`historical128-v1`.

## Tentativas e preservação

O Windows registrou novo boot em22/10 às13h59(SP);a execução posterior encontrou
Docker desligado e os contêineres existentes terminados. A tentativa dirigidav1
não executou testes. Docker foi relançado peloWMI documentado e os contêineres
existentes foram iniciados,sem recriar volumes ou banco.

As primeiras capturas textuais apresentaram bytesNUL e divergência de hashes
na leitura posterior. Foram rejeitadas/preservadas em`primary-text-v1`,sem
atribuir causa não comprovada. Nova capturav2,com flush/fsync,releitura eSHA,
recuperou os mesmos bytes oficiais originalmente observados. A política usa
os horários dav2.107 fontes129 e28 snapshots129 anteriores foram reconferidos.
Recibo:`source-storage-integrity-observation.json`. Não se promoveuv1 a evidência válida.

Falhas seguintes foram corrigidas sem remover guardas:leitorJSON ausente no
comando e duas fixtures com valores fora dos contratos de fatos. Recusas dos
testes passaram a conferir a razão específica,evitando sucesso por erro alheio.
Tentativas/logs continuam preservados. Escrita dos novos candidatos e do
coletor foi reforçada com flush/fsync.

Matriz80 preservada com duas atualizações de escopo;0/8 grupos encerrados.
3/4A encerradas emSandbox;4B parcial;5–7 planejadas.122 último aceite integral.
Sem assinatura,transmissão,commit oupush.
