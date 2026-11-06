# B26/B27: domicílio de PF empresarial e vínculo do destino ao relatório de domicílio (05/11/2026)

O Agente B, assumindo o trabalho do A, cobre os itens 4B.2.4 (local), 4B.2.10 (estabelecimento) e
4B.2.11 (domicílio).

## Fontes oficiais (acervo)

- LC 214/2025 art. 11 §3 (redação LC 227/2026): `lc214-camara-observed-20261001.html`.
- Decreto 12.955/2026, na redação do Decreto 13.075/2026: `import130-decreto12955-observed20261022.html`
  (SHA256 `8396d56d…8235`). Artigos usados:
  - **art. 105:** a PF contribuinte ou responsável deve se registrar por estabelecimento; pelo §4-A, a inscrição
    no CNPJ produz efeitos a partir de **01/01/2027**;
  - **art. 106:** definição de estabelecimento;
  - **art. 107, I:** para a PF do art. 105, o domicílio principal é o estabelecimento para o qual se fornece;
  - **art. 107, II:** para as demais PF, vale a habitação permanente ou o centro das relações econômicas (já coberto pelo A153).

## B26: PF do art. 105

`EntityDomicile151` aceita `party_kind=NATURAL_PERSON` no caminho do estabelecimento cadastrado. Isso só vale com:
- `natural_person_subject_to_registration105=true`;
- `natural_person105_role` igual a `TAXPAYER` ou `RESPONSIBLE`, com evidência da atividade;
- `registration_scheme=CNPJ` para operações a partir de 2027-01-01.

Sem esse bloco, o comportamento é o histórico (`NATURAL_PERSON_SEPARATE_QUALIFICATION_REQUIRED`), e o relatório de PJ
continua byte-idêntico. O relatório da PF ganha `natural_person105` com a fonte do regulamento.
Teste: `tests/Fiscal/Integration/NaturalPersonEstablishmentB26Test.php`.

## B27: destino do motor vinculado ao relatório

Antes, os qualificadores A149–A153 rodavam só como comandos de console. Agora, com a política exata
`destination_domicile_report_b27` (bundle `2026.11.05-b27-draft1`), o destino `ACQUIRER_DOMICILE` ou
`RECIPIENT_DOMICILE` exige o fato `rtc.destination_domicile_report`, com um relatório A151, A152 ou A153 que:
- esteja qualificado e faça replay byte-idêntico;
- tenha a mesma base, parte, fornecedor e data da operação;
- tenha a mesma UF e município que o destino declarado.

O motor nunca escolhe o domicílio; o cadastro não é autenticado.
Componente: `app/Modules/Fiscal/ConsumptionReform/DestinationDomicileReportB27.php`.
Teste: `tests/Fiscal/Integration/DestinationDomicileReportB27Test.php`.

## Limites que continuam

- **Integração com os bundles anteriores:** do B27 em diante, todo cenário com destino por domicílio precisa do
  relatório. Os cenários históricos ficam nos bundles anteriores.
- **Fora da PF do art. 105:** outros contextos materiais (presença física, imóveis, bens) seguem pelas regras já existentes.
- **A partir de 2027:** a obrigação de CNPJ da PF entra em vigor, e o ato conjunto RFB/CGIBS do art. 107 ainda pode
  detalhar o cadastro.
