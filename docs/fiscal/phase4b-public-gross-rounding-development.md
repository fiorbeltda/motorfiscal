# Desenvolvimento28 — movimentos brutos e arredondamento público

O draft30 (`2026.10.08-28-draft30`) concilia apropriações diárias positivas e negativas do mesmo título com os movimentos contábeis fornecidos e a memória fiscal D2101. O motor verifica um lançamento independente quando os brutos arredondados diferem da variação líquida mensal. Não executa lançamento contábil nem cria receita, dedução ou ajuste fiscal para eliminar a diferença.

O contrato optativo `PROVIDED_PUBLIC_GROSS_ROUNDING_JOURNAL_8_2` calcula os totais por sinal em oito casas e arredonda cada lado para duas casas. A diferença contra `vVarMensal` deve ser não nula, de no máximo um centavo. Exige duas linhas balanceadas, na conta do título e na mesma conta do rendimento, com data, operação, vínculo ao título, origem D2101 e prova independente da memória e da política. O fechamento mensal exige esse mesmo lançamento e preserva os brutos efetivamente fornecidos. Políticas anteriores continuam recusando a diferença não comprovada.

Há oito cenários de primeira aquisição e venda na mesma competência, todos com custo inicial10.000 e PIS/Cofins zero:

| Apropriações | Líquido mensal | Brutos arredondados C/D | Lançamento comprovado | D2101 parcial/total |
|---|---:|---:|---:|---:|
| +1,005 / −1,015 |−0,01|1,00 /1,02|+0,01|0,00 /0,00|
| +1,015 / −1,005 |+0,01|1,02 /1,00|−0,01|0,01 /0,01|
| +1,005 / −1,005 |0,00|1,00 /1,00|ausente|0,00 /0,00|
| +1,00 / −0,98 |+0,02|1,00 /0,98|ausente|0,02 /0,02|

Cada linha tem venda parcial de metade da quantidade e venda total. Na parcial dos dois primeiros pares, o valor médio não arredondado é4.999,995 ou5.000,005, mas a baixa efetivamente fornecida é5.000,00. O contrato optativo `PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2` exige prova independente dessa baixa e reconcilia a diferença de±0,005 criada pela venda. O saldo remanescente conserva curva e arredondamento separadamente. Isso não representa consumo de uma ponte anterior; um saldo anterior disponível não é presumido.

A cobertura dessa baixa arredondada exige primeira aquisição, uma única venda, ausência de histórico, rendimentos anteriores e valor justo, além de ganho contábil zero. O restante da análise mantém os contratos existentes. Bases totais dos cenários são700,00,700,01 ou700,02; a dedução genérica300 permanece separada. A conta de resultado correspondente no D1101 mantém `vApur=0.00`, e o rendimento tem origem fiscal única D2101.

Os testes dirigidos conferem todos os campos disponíveis do oráculo diário independente, a baixa arredondada, ausência de postagem e produção, e recusam política ausente, alegação de homologação, linhas duplicadas, data/valor/origem incorretos, prova ausente ou adulterada e prova de baixa parcial com sinal trocado. As capturas usam criação exclusiva e comparação canônica em nova execução.

Validação:20 testes dirigidos/2.554 asserções na captura; suíte ampla1.407/14.764, PHPStan sem erros e Pint412 aprovados. Oito replays exatos e48 corpos XML nos seis eventos mensais, com somente a assinatura ausente no XSD1.3.0. Movimentos/saldos D1101 e campos D2101 foram comparados integralmente aos dados reconciliados. Regressões drafts29/27/25/23/21/18 e candidatos27/26/25 aprovadas. Auditoria de5.099 históricos sem alteração e49 fontes oficiais conferidas; gate permanece bloqueado. Relatório: `storage/app/phase4b-realization-public-gross-rounding-draft-20261008-28/progress-report.json`.

Fonte: MOD1.3.0, páginas físicas66/67 e86/87, preservado em `resources/fiscal/sources/official/dere-observed-20261005-mod.pdf`, SHA256 `b7756c7b0cec5cff46d77e1b1ea5d657a4e2476fa6033f8192561c17dd70ab2d`. O manual exige movimentos brutos realizados e regras de arredondamento; ajustes fiscais documentados não autorizam modificar arbitrariamente bruto ou base. A leitura e a interpretação deste recorte estão em `storage/app/phase4b-realization-public-gross-rounding-draft-20261008-28/research.json`. Não foi estabelecida homologação normativa da convenção local.

Reprodução: `scripts/build_phase4b_tvm_public_gross_rounding_draft.py` reproduz bundle, controles e fixtures a partir das entradas congeladas e cálculo decimal independente. `scripts/verify_phase4b_tvm_public_gross_rounding_draft.py` confere snapshots, replays, oráculos, saldos, IBS/CBS, XMLs, fontes e históricos; os comandos locais de memória extensa usam512MB.

A [publicação oficial da Receita Federal](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/outubro/receita-federal-publica-versao-1-3-0-da-documentacao-tecnica-da-dere/), publicada em07/10 e reconferida em08/10, confirma o pacote técnico1.3.0. Sua captura está preservada em `storage/app/phase4b-realization-public-gross-rounding-draft-20261008-28/dere-publication-confirmation-20261008.html`, com hash no registro próprio. Essa publicação não homologa a convenção local de conciliação.

Limitações: transporte histórico dessas curvas com sinais opostos e dos saldos de arredondamento criados pela venda permanece sem prova. A política histórica anterior continua exigindo sinais consistentes e não é relaxada por esta aprovação mensal. Provas institucionais sintéticas e corpos XML sem assinatura não estabelecem recepção, pagamento, transmissão ou homologação. Candidato27 continua a última integração aceita localmente; escopo28 integral, Fase4B e produção permanecem abertos/bloqueados.

[Próximo objetivo de transporte histórico](phase4b-public-mixed-sign-history-next.md).
