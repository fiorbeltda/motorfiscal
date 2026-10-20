# Migração documental mensal ao pacote DeRE 1.3.0 — candidato 22

Objetivo local concluído em 06/10/2026. Bundle `CONSUMPTION-PHASE4B-2026.10.06-22`, predecessor21 preservado. D1001, D1011, D1101, D1121, D1199 e D2101 do subconjunto suportado usam o mesmo arquivo oficial1.3.0, SHA256 `3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd`.

O D1011 migra para `evtPGCC-v1_0_4.xsd`; o D1121, para `evtRelDeducoes-v0_0_2.xsd`. A chave documental passa ao atributo `chDFe`; atividade e conta ficam em `desdobDeducao`, e o número de item é atributo. A continuação exige histórico identificado pela mesma conta, com hash conferido. Os contratos antigos continuam reproduzíveis.

Aceitação:898 testes/2.901 assertivas; PHPStan sem erros; Pint361 arquivos; auditoria Composer limpa;46 casos documentais, oito conjuntos positivos,41 corpos XML e quatro replays financeiros. Corpus geral22:15/15 RTC e3/3 integrado; candidato21 novamente reproduzido.2.203 artefatos históricos sem alteração. Evidências: `storage/app/phase4b-schema13-20261006-22/verification-report.json`. Reproduzir com `python scripts/verify_phase4b_dere_schema13_cut.py`, `python scripts/verify_phase4b_financial_credit_cut.py 2026.10.06-22` e `python scripts/audit_phase4b_dere_schema13_cut.py`.

Este recorte continua restrito à dedução de correspondente do art.192, uma conta qualificada por documento, planos referenciais já suportados e D2101 sem movimento. Provas positivas sintéticas; nenhum recibo oficial, assinatura, transmissão ou fechamento efetivo. O XSD confere os corpos sem assinatura; isso não equivale a homologação. Gate43/43 fontes, `ready=false`,119 bloqueios. Fase4B parcial, com componentes4B.7/4B.8 e5.4/6.5.

Próximo recorte: D2101 com principal movimentado, continuidade por ISIN/conta e reconciliação ao D1101. A apuração de rendimentos com memória diária do custo médio e teto Selic permanece uma etapa separada, sem taxas ou provas inventadas.

Fonte técnica: [portal oficial CGIBS](https://www.cgibs.gov.br/declaracao-de-regimes-especificos-dere), MOD1.3.0, AnexoI e regras de validação preservados em `resources/fiscal/sources/official/`.
