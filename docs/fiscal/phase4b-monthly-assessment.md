# Cálculo mensal consolidado de IBS/CBS

Bundle `CONSUMPTION-PHASE4B-2026.10.02-7`, candidato de sandbox. Contrato `LC214_ART42_45_53_MONTHLY`; motor `MonthlyAssessmentEngine`, integrado ao fluxo RTC e ao motor ISS/4A. Avalia `rtc.assess_monthly_period=true` e o fato array singleton `rtc.monthly_assessment=[ledger]`.

## Contrato e cálculo

Exige mês encerrado, contribuinte regular, BRL, cadastro de todos os estabelecimentos e estabelecimento central pertencente ao cadastro. Exige livros completos de todos os regimes, débitos anteriores, créditos líquidos de anulações e evidências correspondentes. Sem essas informações retorna `ACTION_REQUIRED`.

Cada componente exige identificador único, tributo, estabelecimento, data, observação não futura, status de qualificação resolvido, referência de evidência e hash da qualificação. São referências a registros previamente qualificados: não constituem assinatura, revisão jurídica ou validação automática da veracidade do registro. As fixtures declaram isso expressamente. Uma mesma parcela documental de crédito não pode aparecer no mês corrente e no saldo anterior.

Os débitos exigíveis são separados do valor nominal da operação. Um lançamento `NOT_DUE` deve ter débito0; o cálculo não transforma automaticamente o nominal de2026 em obrigação a pagar. Os créditos do mês devem estar efetivamente apropriados. Créditos ordinários exigem referência de extinção; presumidos exigem referência e hash da regra previamente qualificada. Essa consolidação não concede elegibilidade a créditos presumidos nem materializa seus regimes legais.

Saldos anteriores são conciliados por parcela original: valor menos compensação já usada, ressarcimento recebido e reserva de pedido ainda pendente. Os três grupos são distintos; reserva não pode repetir ressarcimento recebido. Mantém a data original de apropriação e verifica a expiração de cinco anos. Valores negativos ou disposições acima da parcela original são inválidos.

Débitos anteriores vencidos, não extintos e não inscritos têm prioridade no cálculo de uso dos créditos; seu valor previamente qualificado inclui acréscimos legais comprovados. O saldo anterior não coberto fica explícito e separado da obrigação do mês.

| Etapa por tributo | Fórmula |
|---|---|
| Crédito disponível | Crédito apropriado + saldo anterior disponível − crédito alocado aos débitos anteriores |
| Saldo antes das extinções | Débitos do mês − crédito disponível + ajustes positivos − ajustes negativos |
| Saldo final | Saldo antes das extinções − extinções comprovadas III–V |
| Resultado | Positivo: a recolher; negativo: a recuperar; zero: equilibrado |

Ajustes positivos/negativos são valores previamente qualificados com regra e hash de fonte; não se presume regulamentação nem se recalculam acréscimos legais sem seus parâmetros. As extinções aceitam split, recolhimento do adquirente e pagamento do responsável, vinculados ao débito qualificado e respectivo tributo. Não aceitam compensação por crédito como segunda dedução. Não aceitam valor acumulado acima do débito ou reutilização da mesma prova. O corte inclui extinções ocorridas depois do encerramento até a data de avaliação, desde que conhecidas. A imputação geral de pagamento sem operação específica ainda precisa de contrato de alocação próprio.

IBS e CBS nunca se compensam entre si. A memória conserva o livro, operandos, alocação anterior, saldo anterior remanescente e resultado por tributo. Cálculo decimal, sem float. Não realiza escrituração, entrega, confissão de dívida, compensação efetiva ou pagamento. Vencimentos e prazos de entrega permanecem `null` sem regulamento vinculado.

## Fonte e alcance

Fonte: [LC214 consolidada, arts.42–45,47 e53–54](https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html), preservada com SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. A política de consolidação exige previamente qualificados os débitos, créditos e ajustes. Os qualificadores existentes de crédito e anulação permanecem independentes; a memória de manutenção do corte `-3` não se torna automaticamente saldo disponível.

Esse contrato fecha o cálculo quantitativo mensal de um livro comprovado. Permanecem a qualificação material dos demais regimes, a incorporação de livros reais, a imputação documental geral e os requisitos restantes da auditoria integral.

## Evidências reproduzíveis

Materializador: `scripts/fork_phase4b_monthly_assessment_bundle.py`. Verificador independente dos valores: `scripts/verify_phase4b_monthly_assessment_cut.py`. Corpus20/20 integrados,22 testes dirigidos/55 asserções, três snapshots reproduzíveis e três XMLs candidatos com somente o conflito oficial de série. Um positivo executa efetivamente ISS institucional imune, RTC e4A com pagador familiar; os demais usam ISS municipal fixture e4A `NOT_REQUESTED`.

| Caso | Saldo IBS | Saldo CBS | Hash da decisão |
|---|---|---|---|
| Misto de dois estabelecimentos | 65,00 | −90,00 | `27a5a13e2dfb21d6dcb4e19d2208cb44a2412c1c5b8c04abb2c75be11a9bac74` |
| Ajustes qualificados | 70,00 | −100,00 | `a42e34efdae4442ba4d1a4f194cbf295e6b01cc823141357fb2b6d5c3b810640` |
| Integração com4A e ISS imune | 65,00 | −90,00 | `8effedecd60249c9d6b7d5bd887e84147d1ad0a1b72b43b79b6cdf4d42de3dae` |

Corpus: `storage/app/phase4b-monthly-assessment-corpus-20261002-7.json`. Artefatos: `phase4b-monthly-assessment-*-20261002-7-*`. Gate: `phase4b-production-assessment-20261002-7.json`,20/20 fontes íntegras, elegível local, `ready=false`,117 bloqueios. Nenhuma aprovação formal, emissão, assinatura, transmissão ou recolhimento.
