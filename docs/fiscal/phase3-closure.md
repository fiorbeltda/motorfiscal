# Fechamento da Fase 3 — ISS

Data de corte: 17/09/2026.

## Resultado

A Fase 3 do roadmap do motor completo está implementada como `ISS-PHASE3@2026.09.17-1`, ainda com status `SANDBOX_CANDIDATE`. O bundle foi publicado no PostgreSQL local e pode ser selecionado explicitamente sem substituir o bundle histórico do MVP.

## Cobertura funcional

- catálogo imutável com os 338 códigos `cTribNac` do Anexo B;
- 337 códigos tributáveis confirmados e `990101` explicitamente não suportado como ISS tributável;
- competência territorial por estabelecimento prestador, local da prestação, evento, obra, tomador e intermediário;
- importação de serviço com emitente tomador ou intermediário;
- regra marítima, incluindo o tratamento do subitem `20.01` com fundamento adicional na Pesquisa 10;
- responsabilidade nacional na importação e nos 13 subitens do art. 6º, §2º, II, distinguindo tomador de intermediário;
- bloqueio de retenção para MEI/SIMEI;
- tratamento de valor fixo mensal do Simples;
- seleção auditável da alíquota de retenção do Simples: início de atividade, alíquota informada ou fallback de 5%;
- mudança temporal já conhecida entre 2026 e 2027 para o período de referência da alíquota do Simples;
- hipótese de benefício ilegal abaixo da carga mínima, com deslocamento de competência e responsabilidade;
- subitem `15.01` separado para regra especializada, sem reativar a territorialidade de destino afastada pela ADI 5835;
- cálculo de base, deduções de construção documentadas, alíquota e ISS;
- projeção parcial da DPS, trace, hashes, snapshots e replay;
- importação versionada de snapshots municipais com alíquota, responsabilidade, fundamento legal e identificador da regra.

## Comportamento conservador

A ausência de regra municipal de responsabilidade não equivale a ausência de retenção. Quando não existe uma hipótese nacional conclusiva nem uma regra municipal versionada, a execução retorna `NEEDS_MUNICIPAL_RULE`/`ACTION_REQUIRED`. O motor não cria responsabilidade por diferença de Municípios nem por falta de cadastro do prestador.

Para operações de arranjo de cartões no subitem `15.01`, a classificação especializada ainda exige revisão da função concreta do participante. O motor não generaliza credenciadora, emissora, titular ou estabelecimento credenciado como se fossem o mesmo sujeito.

## Seleção do bundle

```bash
php artisan fiscal:scenario:run caminho/cenario.json \
  --bundle-manifest=resources/fiscal/bundles/iss-phase3-2026.09.17-1.json
```

Para usar as regras publicadas no PostgreSQL, acrescente:

```bash
--rule-bundle-key=ISS-PHASE3 --rule-bundle-version=2026.09.17-1
```

## Continuidade

A Fase 4A já foi iniciada em bundle separado: retenções federais `IRRF`, `PIS`, `Cofins`, `CSLL` e `INSS`, mantendo classificação, responsabilidade, base, alíquota, vencimento e fundamento legal separados por tributo. O estado corrente está em [phase4a-status.md](phase4a-status.md).
