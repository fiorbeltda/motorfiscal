# Exportação 79 — prova nativa do tipo, não da operação

## Resultado e limites

Em 15/10/2026 foi executado no PHP8.5.10/libxml um harness isolado para
`TCTribMunicipal`. São 13 fragmentos: sete aceitos e seis recusados; onze
veredictos tinham expectativas explícitas, e dois casos de limite lexical
foram registrados como observação nativa. Nenhum teste Pest adicional foi
iniciado e nenhuma entrada da suíte78 foi alterada.

Evidência exclusiva:
`D:/ProjetoSaasExports/phase4b-export79-type-probe-20261015/isolated-type-native-validation.json`.
Saída completa: `native-type-probe.stdout`, SHA-256
`8b56798c630693199e386a46145914a6734b32a6bc1ccec9c73381ad2988be6c`.
O relatório vincula inputs, classe validadora e versão PHP; não contém aceitação
de documento completo, incidência de ISS, obrigação ou produção.

## Harness e preservação da fonte

O ZIP oficial preservado tem dez membros. O fechamento de dependências usado
pela DPS tem quatro: DPS, tiposComplexos, tiposSimples e xmldsig. Esses quatro
foram copiados byte a byte para um arquivo derivado, com seus hashes conferidos.
Foi adicionado um root sintético `tribMunProbe` tipado como `TCTribMunicipal`,
que inclui a DPS oficial. O wrapper não é um schema governamental de documento
e seu ZIP derivado não deve ser cadastrado como fonte oficial ou contrato DPS.

A primeira preparação confundiu o inventário do arquivo inteiro com o do
fechamento de dependências e parou na assertiva. O script, o ZIP vazio parcial
e `preparation-attempt1-failure.json` foram preservados; não houve execução
nativa nessa tentativa. A v2 seleciona explicitamente o fechamento e preserva
o inventário oficial completo. Nenhum membro oficial foi editado ou normalizado.

## Veredictos efetivos

| Fragmentos tipados | Resultado |
| --- | --- |
| Exportação3, resultadoUS, não retido1 | Aceito |
| Exportação3 sem país de resultado | Aceito |
| Exportação3 com resultadoBR ouZZ | Ambos aceitos |
| Exportação3 com grupo de imunidade2, alíquota2,00 ou retenção2, separadamente | Três aceitos |
| Paísmisto `uS`, incidência5, retenção ausente ou sequência invertida | Quatro recusados |
| País `USA` ou `prefix_US_suffix` | Ambos recusados |

Nos fragmentos aceitos, o tipo não faz a qualificação semântica/fiscal das
combinações. Não concluir que grupo de imunidade, alíquota ou retenção são
fiscalmente permitidos na exportação só porque a estrutura passa. Também não
afirmar proibição universal desses campos sem as regras de negócio aplicáveis.
ZZ foi aceito lexicalmente; sua pertença/significado numa tabela de países não
foi qualificada. A validação não autentica resultado, pessoa, residência ou
protocolo e não prova implantação remota.

Os fragmentos não reutilizam nem adulteram snapshots econômicos aceitos.
Não são candidatos completos produzidos a partir de decisões tributárias;
testam apenas o comportamento estrutural dos tipos oficiais observados. O
[diagnóstico77](phase4b-export77-document-gap.md) e a
[inspeção dos tipos79](phase4b-export79-observed-schema.md) continuam indicando
qualificação ISS independente e projeção RTC como trabalhos necessários.

Produção, exportação integral/parcial e a Fase4B permanecem incompletas.
