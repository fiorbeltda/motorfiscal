# Temporal85: composição local de evidências, eventos e reconciliação

Componentes83/84 e planejador85 foram compostos externamente, sem instalação
no app, alteração de recursos fiscais ou nova aceitação de regressão integral.

Evidência: `D:/ProjetoSaasExports/phase4b-temporal85-composition-preparation-20261015/`.
Auditor: `D:/ProjetoSaasExports/audit_temporal85_composition.py`.
Relatório exclusivo: `composition-preparation-validation.json`.

## Prova nativa

- 36 casos anteriores do planejador continuam conformes.
- 11 cenários compõem resolvedor83 → planejador85 → reconciliador84.
- Catálogo real do bundle78: antecipação300,00/fornecimento1000,00 em2026
  produz diferença CBS6,30 no exemplo, sem alegar débito anterior escriturado.
- O mesmo catálogo recusa a CBS2027 ausente. Caso alternativo usa taxas
  explicitamente sintéticas para provar somente a aritmética da virada de período.
- Previsão futura não é enviada ao cálculo como fornecimento já ocorrido.
- IDs textuais0/1/01/0001 preservam o tipo. Reproduziu-se no planejador82
  a conversão da chave numérica textual em inteiro e a recusa subsequente do84.
  O85 mantém uma lista explícita de IDs e não deriva seus tipos das chaves.
- A lista de reconciliação é ordenada lexicalmente. Inverter a entrada das
  parcelas produz plano e resultado idênticos, sem enfraquecer unicidade.

PHP8.5.10; PHPStan nível8 e Pint aprovados. O auditor confere fontes, resultados,
gates, vínculo ao catálogo real e as3.836 entradas aceitas78 sem alterações,
adições ou remoções.82,83 e84 históricos permanecem intactos.87 casos de83/84
não são somados aos47 daqui como se fossem uma única suíte independente.

## Limites e próximo trabalho

Ainda não há política temporal de fonte ativada, prova de débitos anteriores,
período de apuração qualificado, procedimento de cancelamento/pagamento a maior,
autenticação documental ou autorização de lançamento/crédito. Resultado
continua local e candidato. Serviços e espécies especiais sem prova explícita
não recebem presunção automática. Nenhuma taxa futura foi promovida ao catálogo.

O próximo corte é qualificar o contrato por espécie/data e integrar os eventos
ao fluxo RTC/ISS com fatos/perguntas próprios, bundle versionado, trace,
invariantes, corpus nativo/replays e regressão completa. Não substituir o cálculo
da operação inteira por uma avaliação de parcela sem tratar a reconciliação.
A matriz dos80 requisitos permanece pendente e produção continua bloqueada.
