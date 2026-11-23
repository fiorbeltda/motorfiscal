# A163 — calendários e prazos documentados do split

Entrega local da frente 1, em 23/11/2026, sobre A159–A162. Bundle `2026.11.23-a163-draft1`; contrato `RTC-SPLIT-DEADLINE-A163-V1`. Não encerra o requisito 4B.3.7 nem os 44 requisitos da frente.

## Comportamento implementado

Calendários exigem identidade, versão, fonte, publicação, vigência, revogação/superação, ambiente, tributo, autoridade, finalidade, fuso e classificação booleana de **cada dia** da cobertura declarada. Datas faltantes, cobertura extrapolada e identificadores ambíguos são recusados. Não se inferem feriados, dias de expediente ou anos futuros. Calendário de operações do PSP não substitui expediente da RFB/CGIBS.

Três regras estão ativadas:

- Informe de segregação: primeiro corte obrigatório que inclui a liquidação, com igualdade inclusiva e janela de três horas; após o segundo corte ou em dia não útil, usa o primeiro corte do próximo dia útil explicitamente classificado. A inclusão antecipada permitida pelo manual continua possível. O início da janela é o corte, não a liquidação.
- Repasse: primeiro dia útil após a liquidação financeira; meta às 09h e limite às 12h, em UTC−3. Informe atrasado não desloca esse marco.
- Excedente simplificado: três dias úteis após a conclusão da apuração, sem reiniciar a contagem na determinação tardia do excedente. A precisão é de data; não é inventado horário de encerramento de expediente.

O novo fato `rtc.split_deadline_requests` seleciona pedidos explícitos. A integração exige o livro já validado, mesma data de conhecimento e correspondência das datas, hashes e provas dos eventos. Para o padrão, usa o recibo individual de liquidação, inclusive nos pagamentos múltiplos A161, o informe correspondente e o repasse do tributo. Para o simplificado, usa o encerramento e a determinação de excedente do livro A162. Contradições, omissão de desempenho já conhecido e pedidos duplicados são recusados.

A cobertura é somente dos pedidos, não de todo o livro. O desempenho confronta o timestamp fornecido com o prazo: no repasse, o livro disponível registra recebimento pela autoridade, e isso não é prova independente do instante de execução pelo PSP. Não autentica recibos, estabelece saldo, realiza pagamentos, concede crédito ou decide responsabilidade jurídica. `ans_exclusions_assessed=false`: indisponibilidades/manutenções e apuração mensal dos ANS não foram implementadas neste contrato.

## Fontes e decisão interpretativa

[Manual de Tempos 1.0.1](https://cgibs.gov.br/upload/arquivos/202609/24145528-split-payment-manual-de-tempos-v1-0-1.zip), §§5.2, 5.3, 5.5, 6.1 e 6.2. PDF SHA256 `13685b4d1a2f486cd092efb34c83e741b4300edf9692b09ffb6ce0f27036bdf1`. Páginas 18, 19 e 22 renderizadas e conferidas visualmente; recibo e imagens em `D:/ProjetoSaasExports/front1-a163-20261123-source-v1`.

A tabela geral do §5.3 menciona a data do informe; o capítulo específico §6.1 define D+1 como dia útil seguinte à liquidação financeira. Este contrato adota o marco específico, registra a interpretação no resultado e impede que atraso no informe prorrogue o repasse. Essa escolha não é apresentada como solução oficial de uma divergência editorial.

[LC214 consolidada, art.33 §4](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), bytes preservados no recibo A162, fundamenta o excedente simplificado. O [CTN, art.210](https://www.planalto.gov.br/ccivil_03/leis/l5172compilado.htm) reforça a distinção entre expediente normal da repartição e calendário bancário; não se promove o calendário de retenções federais a calendário de devolução IBS/CBS.

O catálogo oficial `split_calendars_a163` permanece vazio: nesta entrega não foi qualificado um calendário oficial por finalidade. Isso não afirma inexistência de fontes oficiais. Calendário fornecido pelo chamador só é aceito como sintético, em envelope sintético; fonte oficial precisa integrar o catálogo versionado. Testes positivos do motor continuam retidos como `RTC_SYNTHETIC_TEST_ONLY`.

## Validação observada

514 testes / 1.569 asserções dirigidos cumulativos; Pint aprovado; PHPStan global serial sem erros. Dez modos nativos repetidos por bytes com replay e dez recusas de adulteração; 20 comparações Decimal e três oráculos independentes de datas/cortes. Execução local PHP8.5; Docker indisponível, regressão integral final pendente.

Reprodução: `python scripts/agent_a/validate_split160.py --increment A163 --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/novo-diretorio`.

Evidência terminal: `D:/ProjetoSaasExports/front1-a163-20261123-final-v2/validation-a163.json`. Índice compacto: `docs/fiscal/agent-a/20261123/validation-A163.json`.

## Próximo trabalho

Qualificar calendários oficiais e os marcos de devolução padrão/fallback e pelo adquirente; implementar exclusões operacionais e pedidos anteriores ao recebimento do informe quando necessários. Depois, continuar os demais pacotes e a auditoria individual dos 44 requisitos. Fases 3/4A encerradas; 4B em andamento, 0/8 grupos formalmente encerrados; fases 5–7 planejadas; produção não liberada.
