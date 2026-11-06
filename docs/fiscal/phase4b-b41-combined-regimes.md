# B41: operação combinada com fornecimentos em regimes diferentes (06/11/2026)

Item 4B.4.6 (operações combinadas).

## Problema

Um pacote turístico pode juntar várias coisas na mesma operação:
- hospedagem, no regime de hotelaria (B35, redução de 40%);
- intermediação da agência, no regime de agência de turismo (B36, base própria);
- serviços no regime geral.

Uma única alíquota "média" estaria errada. O `CombinedSupplyAssessment145`, do A, compõe fornecimentos, mas exige o fluxo
integrado com ISS, e o catálogo Sandbox não tem parâmetro municipal de ISS para esses serviços.

## Solução

`CombinedRegimeSuppliesB41.php` (contrato `RTC-COMBINED-REGIME-SUPPLIES-B41-V1`):
- exige que os fornecimentos sejam independentes, com prova da independência e da alocação dos preços;
- exige que a soma dos valores bata com o total declarado;
- decide cada fornecimento no motor IBS/CBS, no bundle instalado. Cada um mantém o seu regime, a sua classificação, a sua
  base e a sua NFS-e (um documento por fornecimento);
- calcula os totais somando os valores já arredondados de cada fornecimento, sem alíquota misturada;
- recusa alimentação e bebidas embutidas no pacote, que seguem o regime de bares e restaurantes, fora da NFS-e (art.
  280, par. único);
- se um fornecimento não pode ser decidido, a operação inteira fica pendente.

O ISS não é avaliado aqui; ele continua no fluxo do A145.

## Testes

`tests/Fiscal/Integration/CombinedRegimeSuppliesB41Test.php`:

- **Pacote:** hospedagem de 1.000,00 (200048) e agência de 1.000,00 (200051, base 350,00).

  | | Base | CBS | IBS |
  |---|---|---|---|
  | Totais | 1.350,00 | 7,29 | 0,81 |

- **Recusas:** alimentação embutida, valores que não batem e fornecimento único.
- **Pendência:** um componente sem decisão deixa a operação pendente.
