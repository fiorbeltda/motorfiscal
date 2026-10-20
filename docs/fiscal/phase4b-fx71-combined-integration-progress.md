# Fase 4B — origem, fuso e decimal exato (geração 71)

A geração 69 foi aceita localmente com 2.213 testes e 45.531 assertions. Essa evidência permanece histórica e não representa aceitação da geração seguinte.

A geração 71 instala conjuntamente a guarda de origem material da cotação, limites de offset civil até ±14:00 e preservação do token decimal monetário do JSON original. O valor numérico decodificado por JSON é consultado somente para validar o tipo de entrada; o cálculo usa o token original e BigDecimal. Uma cotação sintética de 9999999999.12345678 multiplicada por 1000000.00 resulta exatamente em 9999999999123456.78. Esse ensaio é de precisão, sem alegação de cotação oficial.

O bundle próprio `2026.10.14-70-draft1` exige qualificação material e oferece seleção local qualificada apenas para USD. EUR/AUD permanecem como evidências numéricas históricas, mas o novo bundle recusa a falta de qualificação material. O bundle anterior mantém seus resultados históricos.

Foram verificados 62 testes direcionados (207 assertions), análise estática, estilo, nove decisões integradas e 14 replays anteriores. A suíte completa da geração 71 foi iniciada e sua aceitação depende de saída terminal e auditoria `audit_phase4b_fx71_combined_integration.py --full`. Fontes anteriores foram arquivadas antes das alterações. A evidência de progresso e a aceitação final são arquivos distintos, criados sem sobrescrever registros históricos.

Continuam pendentes a origem material das demais moedas, conversão cruzada, fechamento da obrigação documental de importação e a auditoria completa dos requisitos originais da Fase 4B. Produção permanece bloqueada; esta migração não conclui toda a fase.
