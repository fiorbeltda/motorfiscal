# Bases negativas49 — planejamento local de consumo

`DereNegativeBaseConsumptionPlanner` recompõe o estoque por meio do reconciliador de retorno, exige a competência imediatamente anterior e valida o pedido D1199. Mantém IBS e CBS separados por raiz financeira1005/1010; não transporta estoque entre natureza ou tributo.

O cálculo local implementa PEPS por período de origem, limite pela base positiva corrente, preservação de saldo remanescente e opção de não utilizar. No método manual, omissão do limite significa saldo disponível. Um pedido superior ao estoque é limitado ao disponível e registra `AVISO_CONFERIR_SALDO_BCN`, sem alterar os parâmetros solicitados. Todas as operações monetárias usam decimal exato.

A seleção manual de várias origens é calculada quando a base corrente comporta a soma dos limites ajustados ao estoque. Se a soma exceder a base corrente e mais de uma origem puder consumir, a prioridade de redução individual precisa de prova específica; o planejador retorna `MANUAL_ALLOCATION_PRIORITY_REQUIRED`. A ordem das linhas do XML não foi convertida em regra fiscal presumida.

Origens duplicadas do mesmo período/raiz, com diferentes sequências, exigem reconciliação das versões e reaberturas. Competências ausentes na cadeia, origem solicitada fora do estoque ou base corrente de outra natureza são recusadas. O planejador tampouco inventa recepção, assinatura ou atualização monetária.

A pesquisa confirmou o art278 da ResoluçãoCGIBS6/2026 na publicação original oficial: dedução futura sem atualização, vínculo com art273§3 e prazo de cinco anos desde o último dia útil. O PDF observado e seu SHA estão em `storage/app/phase4b-negative-base49-preparation-20261009/ibs-negative-base-source-observation.json`. [Publicação oficial](https://www.cgibs.gov.br/upload/arquivos/202604/30084927-res-cgibs-n-6-30-abr-2026-regulamenta-o-ibs.pdf). Essa captura não afirma consolidação de todas as alterações posteriores.

Para qualquer consumo positivo no mês do quinto aniversário ou depois, a rotina exige calendário e regra de fronteira comprovados (`EXPIRY_BOUNDARY_AND_CALENDAR_REQUIRED`). Antes desse mês, a competência é matematicamente anterior à primeira data possível de vencimento; isso não qualifica o último dia útil nem a autenticidade do estoque. Pedidos que não podem consumir nada dispensam a decisão de expiração. Casos de calendário, fronteira, reabertura e efeitos de alteração normativa continuam integrantes do escopo pendente.

Evidências: `consumption-development-validation.json`, `synthetic-consumption-input.json`, `synthetic-consumption-result.json` e logs de captura/reprodução nativa no diretório49. A cadeia executada usa o XML sintético D9199 preservado, o leitor nativo, o reconciliador e o planejador. No exemplo independente de outubro, IBS deduz40,00 de base50,00 e conserva base10,00; CBS deduz20,00 da origem de setembro, limitando o pedido90,00 ao saldo20,00 com alerta. As bases correntes desse exemplo são dados sintéticos; não foram derivadas de fechamento real.

Validação conjunta dos quatro componentes49:65 testes/222 asserções; PHPStan do novo componente e Pint aprovados. Não houve nova suíte completa nem integração da rota mensal. As implementações e evidências48 e as entregas anteriores49 continuam preservadas. Resultado `LOCAL_CONSUMPTION_PLANNED` não autoriza lançamento ou produção.

Próximas etapas do [escopo49](phase4b-next-scope-negative-bases49.md): qualificar calendário/fronteira, estoque e sucessão de retornos, registrar regras temporais em bundle próprio, integrar D1199 com origens e bases correntes comprovadas e validar snapshots/replays/XMLs com regressão integral. O candidato integral aceito permanece27; escopo28/Fase4B e produção continuam abertos/bloqueados.
