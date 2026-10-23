# 135 — ponte do preço com todos os tributos próprios

IntegraçãoCLI135 em23/10:comando monta doisDPS completos XSD com replay134,ponte própria,identidade,COMEX e classificação.203 testes/498 asserções,PHPStan global0/Pint673,22 históricos exatos e2 XML distintos idênticos entre execuções. Grupo próprioPIS-Cofins ainda só no relatório. Regressão integral iniciada12:03:18UTC com5896 entradas,incluindo scripts/helpers;resultado terminal pendente.0/8 grupos encerrados. Ver `phase4b-pair135-local-projection.md`.

Em23/10/2026 foi implementado `DocumentOwnTaxBridge135`. Reexecuta o cálculo
próprio135V2 e compara o preço de cada documento,menosISS/ICMS/PIS/Cofins,
com a respectiva parcelaRTC. Nenhum preço é derivado pelo componente a partir
do percentualRTC. A ponte não altera o preço contratual,baseISS ou baseRTC.
Nenhum bundle135 foi instalado ouDPS completo emitido neste corte.

## Contrato e controles materiais

O perfil exige exportação parcialBRL,baseRTC declaradamente sem tributos
legados e nenhum ajuste adicional. Recalcula a alocação pelo total e percentual
dos fatos correntes,comparando total,parcelas,método e referências de provas
com o estágioRTC fresco. Não subtrai novamente tributos da baseRTC já líquida.

O resultadoISS é ligado à projeção112 dos mesmos fatos,que já distingue os
campos de apresentação derivados pelo motor de valores fornecidos pelo usuário.
O primeiro ensaio comparava o hash integral de fatos fornecidos com o hash
dos fatos resolvidos,recusando o positivo. A correção usa a projeção existente,
sem ignorar campos monetários fornecidos. Um controle explícito com valor
fornecido em `invoice.gross_amount` continua recusado.

O componente reexecuta `SoftwareOwnRevenue135` com as provas originais;não
aceita somente um marcador de cálculo concluído. Confere ainda a causalidade:
um memorando próprio não pode preceder as provas de preço/composição/ISS
das quais depende. Valores próprios nulos deISS por não incidência permanecem
nulos no resultado de origem;zero usado na soma não substitui o tratamento.

## Novo cenário sintético e histórico preservado

A fixture declara novos preços400,00/641,33 para exercitar um positivo. O
segundo produzISS18,60,base de contribuições622,73,PIS4,05 eCofins18,68:
641,33−18,60−4,05−18,68=600,00. Total documental1041,33,tributos41,33 eRTC1000,00.
São novas entradas sintéticas de contrato,sem alteração de contrato comercial
real. O componente somente confere a igualdade;não calcula um novo preço alvo.

O contrato anterior400,00/617,92 foi refeito em cenário separado e recusado
por diferença doméstica21,90. Ele não foi corrigido ou sobrescrito. Preço
doméstico641,34 foi recusado por um centavo. Também se recusam alterações de
alocação,prova destacada,memo revogado ou anterior ao preço e política divergente.

O helper133 recebeu argumento opcional `declaredPrices` para reutilizar a
requalificação dos antecessores106/107/110/111 eISS133. Os argumentos/defaults
anteriores foram mantidos;preços uniformes continuam suportados. A opção nova
não aceita uso simultâneo com `amount`. Novos contratos e provas são capturados
em cenário separado,sem modificar as fixtures históricas em disco. A ponte
recebe o estágioISS de uma avaliação134 real,com replay integral antes do uso.
Identidades deste ensaio são sintéticas;ele não emite um novo par fiscal.

## Evidência e escopo restante

166 testes/426 asserções,23 novos de ponte;PHPStan0,Pint5 arquivos. Dois ensaios
idênticos abrangem3 cenários,6 replays integrais134 e6 conferênciasPythonDecimal.
Snapshots,provas e logs estão em
`D:/ProjetoSaasExports/phase4b-own-bridge135-20261023-v1`.
Recibo: `storage/app/phase4b-own-bridge135-development-20261023/own-bridge135-local-validation.json`.
Os114 arquivos de runtime134 e os7 arquivos do cálculo próprio anterior ficaram
intactos. O helper compartilhado mudou de forma explícita e seus testes foram
reexecutados;a última suíte integral continua sendo134 histórica,não uma nova
aprovação integral do estado atual.

Matriz80 corrente no mesmo diretório,com evidência em4B.4.2 e revisões próprias
preservadas. Próximo trabalho:integrar a ponte ao percurso135 e ao parDPS com
classificação,identidades,COMEX e fontes qualificadas;comprovar efeitos judiciais
e demais perfis,concluir câmbioISS/importação e outros requisitos originais.
Aprovação dos efeitos temporaisTema118,autenticidade,obrigação completa eprodução
continuam falsas.0/8 grupos encerrados.
