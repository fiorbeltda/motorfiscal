# Crédito financeiro do tomador — candidato13

O bundle `CONSUMPTION-PHASE4B-2026.10.02-13` ativa `FinancialCreditEngine` após enquadramento, base e débitos financeiros. Entrada financeira usa esta regra específica; não recebe a elegibilidade do crédito genérico. Saída sem prova do tomador registra `NOT_ASSESSED_ON_OUTPUT`. A decisão específica permanece `ACTION_REQUIRED / RTC_FINANCIAL_DOCUMENT_PENDING`; a família financeira e a Fase4B continuam incompletas.

## Fonte e alcance

LC214 consolidada com LC227, arts190 e194–199; art47/48 e uso pessoal57. Fonte primária: https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html. Artefato preservado `resources/fiscal/sources/official/lc214-camara-observed-20261001.html`, SHA256 `dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde`. Publicação, vigência e disponibilidade da interpretação vêm da regra versionada; aprovação jurídica formal permanece pendente.

O contrato recebe evidências previamente qualificadas; IDs e hashes vinculam a prova, mas não demonstram sozinhos a veracidade dos documentos. Os cenários usam `FIXTURE` e hashes sintéticos para relatórios e benchmarks. Não são dados reais de Selic, DI, tributos pagos ou documentos homologados.

## Rotas e cálculo

| Rota | Regra local |
| --- | --- |
| Parcela de crédito194 | Caixa: parcela paga menos principal contratual e montante Selic qualificado; alíquota da operação do mês de pagamento. |
| Emissão de dívida195 | Mesma fórmula; período de detenção por credor específico provado. Oferta pública/exceção de fundo exclusivo qualificada impede crédito do devedor. |
| Recebíveis196 | Deságio na liquidação antecipada menos montante DI qualificado para o prazo; sem subtração do principal da parcela de empréstimo. |
| Tarifas/comissões198 | Limite pelo IBS/CBS efetivamente pago pelo fornecedor; adquirente específico somente quando a despesa não foi deduzida da base. |
| Outros199 | Crédito vedado quando não expressamente permitido. |

Exige vínculo fornecedor/tomador/documento, categoria concordante com o enquadramento, regime regular, uso não pessoal, provas de pagamento e relatório, controle de solicitação ainda não apropriada. Em194–196 o tomador não pode pertencer à seção específica; art197 restringe associado da cooperativa optante e crédito/dívida referenciados em moeda estrangeira. Tarifas198 têm regra própria.

Benchmark negativo não é admitido; excesso negativo vira base zero. Arredondamento monetário por componente em duas casas. Solicitação não pode exceder o crédito calculado; cálculo não pode superar o limite informado pelo fornecedor.

IBS e CBS exigem extinção comprovada separadamente. Falta de extinção produz componente `CONDITIONAL` e solicitação zero, preservando o outro componente qualificado. Exceção48 exige prova expressa de não implantação de ambas as modalidades relevantes, hash da fonte e débitos fiscais corretos. Cumprimento acessório2026 não substitui essa prova. Tarifas198 exigem pagamento efetivo do fornecedor.

## Validação

43 testes dirigidos /140 asserções, incluindo regressão dos débitos12. PHPStan sem erros. Corpus13/13 RTC parcial e3/3 gerais integrados IBS/CBS+ISS+4A; oito replays. Três candidatos XML gerais conservam somente o conflito oficial de série; nenhum documento financeiro específico foi homologado.

Verificador `scripts/verify_phase4b_financial_credit_cut.py` calcula o valor esperado com `Decimal` antes de gravar goldens. Materializador/verificador recusam sobrescrita divergente. Resultados em `storage/app/phase4b-financial-credit-*-20261002-13*`. Gate20/20 hashes, elegível local, `ready=false`,119 bloqueios.

## Pendências concretas

Séries oficiais Selic/DI com cálculo temporal bruto, enquadramento automático da exceção195/carteira95%, validação factual dos relatórios e dos documentos, conciliação de apropriação persistida, documento financeiro específico e demais bases financeiras. O contrato calcula elegibilidade e solicitação; não apropria, escritura, transmite ou recolhe.
