# A160 — revisões fiscais e reset dos retornos do split

Frente 1, 23/11/2026; sequência do A159, requisito 4B.3.7 e interfaces temporais/memória
4B.1.5 e 4B.4.14. O incremento não encerra o split nem os 44 requisitos da frente.

## Comportamento

`RTC-SPLIT-TRANSACTION-A160-V1` mantém um histórico completo de revisões fiscais fornecidas,
com sequência local contínua, versão única, instante efetivo de processamento pelo PSP,
documento e seus instantes de emissão/vínculo, valor original, IBS/CBS e evidências.
São registros locais de qualificação, não um modelo de mensagens oficial da plataforma pública.

O cálculo seleciona a última revisão efetiva até o congelamento. O envelope deve conter
exatamente os valores dessa revisão; uma versão posterior conhecida não substitui o snapshot
usado na liquidação anterior. Quando não há liquidação, a seleção usa o instante de conhecimento
e continua sem produzir segregação. Revisões após a baixa ou a liquidação são recusadas.

Uma alteração material de valor, tributos ou documento exige reset explícito com evidência,
aplicado na mesma revisão local. O motor não presume que toda mudança publicada pela plataforma
invalide mensagens. Sem comprovação do reset, esse caminho retorna ação necessária. Metadados
sem alteração material não constituem uma revisão fiscal neste contrato.

Retornos antigos permanecem no trace, mas não são usados na revisão ativa. Um retorno antigo
processado com atraso não volta a ter precedência. A monotonicidade do valor em aberto é
conferida separadamente em cada revisão; o reset não reabre a janela original para correções
a maior. A ausência de retorno novo processado a tempo faz o cálculo usar os valores informados
da revisão ativa. Documento, datas, valores, ordem e limites continuam sujeitos às guardas A158.

Atualização de conteúdo só é admitida para boleto e Pix Dinâmico. Pix Automático admite
histórico estável de uma revisão; alteração exige cancelamento e nova cobrança, conforme o manual.
Não se inventa autenticação, implantação, transferência, baixa fiscal ou apropriação de crédito.

## Ativação

- Bundle derivado do A159: `consumption-phase4b-2026.11.23-a160-draft1.json`.
- Política exata A160 no gate existente `split_payment_a158`.
- Livro `RTC-SPLIT-LEDGER-A160-V1`: aceita transações estáveis A158, transações com histórico
  A160 e parcelas futuras A159. Cronograma, segregação, informes, repasses e aplicações
  continuam separados e conciliados pelo mesmo núcleo.
- Contratos A158/A159 continuam selecionados explicitamente por seus métodos e políticas;
  não aceitam silenciosamente transações de histórico A160.
- Fato ARRAY existente reutilizado; pergunta e catálogo derivados versionados. O catálogo
  de transição não muda; a guarda de implantação continua ativa.

```text
php artisan fiscal:rtc:assess-split-payment input.json --mode=fiscal-history
php artisan fiscal:rtc:assess-split-payment ledger.json --mode=lifecycle
python scripts/fork_phase4b_a160_lifecycle_bundle.py
python scripts/agent_a/validate_split160.py --php C:/tools/php85/php.exe --output D:/ProjetoSaasExports/front1-a160-new-run
```

## Fontes e evidências

O [Manual de Operações 1.0.1](https://cgibs.gov.br/upload/arquivos/202609/24145322-split-payment-manual-de-operacoes-v1-0-1.zip)
foi recapturado em 23/11/2026: ZIP SHA256
`9c4e0473efd00bb7a99c2250405f1b4b6f9cebc8b3e52d87553e9c677c55f25d`, igual ao A158.
O PDF preservado também foi reconferido por hash; página 18 renderizada e inspecionada.
Foram consultadas as seções 4.2.1, 5 e 7.2, que sustentam atualização, limites de cálculo
e uso dos retornos processados a tempo. Fonte e recibo detalhados em
`D:/ProjetoSaasExports/front1-a160-20261123-v1/source-review.json`.

O verificador A160 executa a regressão dirigida cumulativa A158/A159/A160/A157/B34/B38/B39,
Pint, console, três modos nativos com repetição byte a byte e replay, três recusas por
adulteração e seis comparações independentes de valores com Python Decimal. PHPStan global
é executado e registrado separadamente em processo único, após falha de memória do ambiente
na execução paralela. A validação é local PHP 8.5, com armazenamento temporário no D:;
Docker indisponível. Não constitui regressão integral nem aceite de todos os requisitos.

Pendências do split: múltiplos pagamentos do mesmo boleto, modelo legal simplificado com
parâmetros qualificados, calendários/prazos e prova final do ciclo completo. Dependências
oficiais não são substituídas por parâmetros inventados.

Resultado final: **317 testes/1.149 asserções**, zero falhas; Pint aprovado e PHPStan global
nível 8 sem erros em processo único. Os três modos nativos repetiram bytes e confirmaram
replay; três adulterações foram recusadas e seis comparações Decimal passaram. Console:
28 verificações e HTTP 200. Recibo compacto em `agent-a/20261123/validation-A160.json`;
evidências externas finais em `D:/ProjetoSaasExports/front1-a160-20261123-final-v3`.
