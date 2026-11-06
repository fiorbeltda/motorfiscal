# B37: regime específico de bens imóveis em NFS-e (06/11/2026)

Item 4B.4.10 (regimes específicos). Escopo: operações com bens imóveis que saem em NFS-e, ou seja, locação e equiparadas,
administração e intermediação.

## Fonte

LC 214/2025, arts. 251–265, no texto compilado do Planalto observado em 03/11/2026 (SHA256 `0a0d190b…4673c`), já com
as redações da LC 227/2026.

| Regra | Artigo | Como o motor trata |
|---|---|---|
| Pessoa física só é contribuinte acima dos limites legais | 251, §§1º–2º | `rtc.real_estate_contributor_basis` com prova; `INDIVIDUAL_NOT_CONTRIBUTOR` é recusado |
| Servidão, cessão de uso ou de espaço, permissão de uso e direito de passagem seguem a locação | 252, §1º | `rtc.real_estate_lease_form` |
| Locação residencial de até 90 dias ininterruptos segue a hotelaria | 253 | O motor recusa e indica o regime B35 |
| Fato gerador no pagamento (locação, administração e intermediação) | 254, III–IV | `rtc.real_estate_payment_date` tem de ser igual a `rtc.operation_date` |
| Destino = localidade do imóvel | 11, II | `destination_basis = PROPERTY`, com UF e município do imóvel iguais ao destino; cIndOp 020101 (locação) e 020301 (administração/intermediação) |
| Base da locação = valor da locação, sem tributos e emolumentos do imóvel e sem condomínio | 255, II e §2º | Registro `rtc.real_estate_settlement` (contrato `RTC-REAL-ESTATE-LEASE-B37-V1`) com cada linha comprovada |
| Redutor social residencial: R$ 600,00 por mês por imóvel, atualizado mensalmente pelo IPCA, limitado à base | 260 (LC 227/2026) | O valor atualizado é informado com a fonte (`value_source_id` e `value_source_hash`). O motor só multiplica pelo número de imóveis e de meses e limita à base; nunca estima o IPCA |
| Redução de 70% na locação | 261, par. único | cClassTrib oficial **200027** |
| Redução de 50% nas demais operações (administração e intermediação) | 261 | cClassTrib oficial **200046** |
| Inscrição do imóvel no CIB | 265 | `rtc.property_registry_id` obrigatório (CIB ou matrícula) |

## Implementação

- `RealEstateRegimeB37.php`:
  - fica atrás do gate `real_estate_regime_b37`;
  - `resolve()` faz a qualificação e a redução;
  - `settle()` calcula a base da locação.
- No motor:
  - a rota com `operation_kind = REAL_ESTATE` só sai do bloqueio genérico quando o gate existe e o fato informa o regime
    `REAL_ESTATE`;
  - a base da locação não se combina com outros ajustes de base.
- O bundle `2026.11.06-b37-draft1` (`scripts/fork_phase4b_b37_real_estate_bundle.py`) traz três regras de classificação,
  todas com `SANDBOX_REVIEW_REQUIRED`:

  | Operação | cTribNac | NBS |
  |---|---|---|
  | Locação | 990101, código oficial da NFS-e Nacional para "Serviços sem a incidência de ISSQN e ICMS" | 1.1002 |
  | Administração | 171201 | 1.1001.1 |
  | Intermediação | 100501 | 1.1001.2 |

## Fora desta etapa (outros documentos ou outras regras)

- Alienação com redutor de ajuste (arts. 257–259), incorporação e parcelamento do solo (art. 262): a alienação usa o
  documento próprio de imóveis (cClassTrib 820005, indNFSe = 0) e o redutor de ajuste vinculado ao imóvel a partir de
  2027.
- Construção civil (art. 252, V).
- Locação em zonas reabilitadas (cClassTrib 200026, art. 158): é regime diferenciado e depende da qualificação da zona.

## Testes

`tests/Fiscal/Integration/RealEstateRegimeB37Test.php` tem 16 testes:
- fonte literal;
- locação não residencial: 1.000,00 − 100,00 de IPTU − 200,00 de condomínio = base de 700,00, com CBS 1,89 e IBS 0,21;
- redutor social com valor e fonte informados, limitado à base, recusado sem fonte e recusado fora do residencial;
- administração e intermediação a 50%;
- seis recusas de condição;
- quatro recusas de apuração;
- b36 sem conhecimento do regime.
