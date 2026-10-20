# Cobertura do MVP fiscal

## Fatias implementadas

| Família | LC 116 | `cTribNac` | Localidade | Detalhe DPS |
|---|---:|---:|---|---|
| Desenvolvimento de software | `01.01` | `010101` | estabelecimento do prestador | nenhum grupo adicional |
| Eventos | `12.08` | `120801` | local do evento | `atvEvento` |
| Construção civil | `07.02` | `070202` | município da obra | `obra` |

Cada fatia usa o mesmo kernel declarativo e produz classificação canônica, incidência, localidade, parâmetro municipal, responsabilidade/retenção, tratamento, cálculo decimal, invariantes, trace, decisão, projeção parcial, snapshot e replay.

## Salvaguardas específicas

- Software não infere serviço por CNAE ou descrição comercial.
- Eventos exigem confirmação da atividade e município do evento.
- Construção exige identificação e município da obra, tipo de tomador e compatibilidade do contrato.
- Dedução de materiais de construção só é aplicada com valor, evidência, regra municipal, produção fora do local, venda separada, documento de ICMS e validação aprovada. Ausência gera pergunta; inconsistência bloqueia.
- Para `07.02`, a responsabilidade nacional do tomador pessoa jurídica é representada separadamente do local e da base.
- `tpRetISSQN` usa `1` sem retenção, `2` para tomador e `3` para intermediário.

## Projeção DPS

A projeção tem escopo `PARTIAL_SANDBOX_NO_EMISSION` e inclui apenas o subconjunto necessário ao Sandbox. Campos calculados/enriquecidos pela SEFIN, como `cLocIncid`, `vBC`, `pAliqAplic` e `vISSQN`, ficam identificados como enriquecimento esperado, sem fingir que foram enviados pelo contribuinte.

## Proveniência e determinismo

O manifesto fixa por hash os facts, perguntas, catálogos, parâmetros municipais base, invariantes, schema de cenário e regras. Snapshots de execução com fonte municipal persistida carregam o hash do catálogo base e somente as respostas consultadas. Replay valida todos esses hashes antes de recalcular a decisão.

O corpus possui 14 cenários cobrindo resultados válidos com aviso, ação requerida e inválidos. Cada expected result exige seu `decision_hash` completo.

## Fora do MVP

- IRRF, PIS, Cofins, CSLL e INSS.
- Regimes tributários completos.
- IBS/CBS e obrigações acessórias.
- Consulta ao vivo e emissão pela API Nacional.
- Assinatura de XML.
- Frontend, autenticação, billing e multi-tenancy.

As fixtures municipais embarcadas são sintéticas e sempre geram `MUNICIPAL_PARAMETERS_ARE_FIXTURES`. O uso de dados importados exige a chave e a versão exatas por `--municipal-source-key` e `--municipal-source-version`; ainda depende da validação jurídica e do gate PostgreSQL oficial.
