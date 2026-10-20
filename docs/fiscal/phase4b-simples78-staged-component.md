# Simples 78 — componente preparado, ainda não integrado

Atualização posterior de 15/10/2026: o checkpoint abaixo registra a preparação
isolada já encerrada. O componente foi depois ligado a um bundle próprio com
fatos/perguntas e testes permanentes; a suíte completa dessa integração está
em execução. Ver [estado atual da integração78](phase4b-simples78-local-integration.md).
Não confundir os 48 casos isolados abaixo com aceitação da integração completa.

## Escopo real

Componente candidato `SimplesRegularElectionQualifier78` preparado fora do
checkout ativo, durante a suíte 76. Não foi instalado no motor, adicionado ao
autoload, publicado em bundle ou incluído nos 2.370 testes dessa suíte.
Fontes: [capturas nativas oficiais](phase4b-simples78-native-source-validation.md).

Diretório:
`D:/ProjetoSaasExports/phase4b-simples78-component-preparation-20261015/`.
Relatório final do componente:
`staged-component-development-validation-v4.json`.

O contrato distingue opção ordinária e opção no momento de inscrição no CNPJ
para janeiro–junho/2027. Vincula os hashes das duas fontes e sua observação;
exige regime consistente, inscrição CNPJ e evidência própria, contexto de início
de atividade, rota e evidência, protocolo, status e data de observação do status.
Não infere CNPJ a partir de início de atividade. MEI é recusado; regime interno,
segundo semestre e outras competências ficam fora deste contrato, sem decisão
presumida. A rota no CNPJ exige a exceção e opção na mesma data da inscrição.

Usa calendário civil America/Sao_Paulo, recusa datas inválidas/futuras e status
que não cubra a operação. A opção ordinária não é considerada consolidada antes
do fim da janela de cancelamento, inclusive antes de essa janela abrir. Toda
qualificação é local baseada nas evidências fornecidas: protocolo não é
autenticado remotamente e `production_ready=false` permanece explícito.

## Verificações observadas

- 48 casos explícitos, 96 avaliações nativas PHP 8.5.10 e 240 assertions.
- Cada caso foi reavaliado com a ordem das chaves do objeto de hashes invertida,
  sem mudar a decisão nem a ordem determinística da evidência retornada.
- PHPStan sem erros e Pint em dois arquivos aprovados após os ajustes.
- Os quatro arquivos no `/tmp` isolado foram comparados por hash aos do D:.
- Nenhum segundo Pest foi iniciado e app/tests/recursos permaneceram congelados.

Os logs exclusivos `scope78-staged-*-v4` estão no diretório histórico de checks
71. O relatório final vincula os hashes. A primeira rodada 48/192 foi aprovada
antes das mudanças de tipo/estilo/determinismo; seu recibo foi preservado como
histórico e não representa o hash final do componente.

Foram preservadas as falhas de tipo/estilo, uma tentativa de formatação sem
permissão de escrita no arquivo temporário e a falha do primeiro teste de
reordenação. Esta última encontrou saída de evidência com ordem herdada da
entrada; foi corrigida para ordem explícita, sem mudar o conteúdo das fontes
ou as decisões tributárias esperadas. Não enfraquecer os verificadores antigos.

## Trabalho necessário antes de aceitação integrada

Arquivar runtime atual aceito76; criar fatos/perguntas próprios e política
versionada; selecionar o contrato no caminho do Simples sem reescrever a janela
histórica ou confundir o segundo semestre; instalar testes permanentes; executar
cenários integrados e replays antigos/novos; conferir os efeitos ISS/documentais;
rodar regressão e gates finais com a geração nova. As novas fontes ainda não
foram copiadas para `resources/fiscal` nem ativadas por um manifesto.

O componente preparado não comprova sozinho cumprimento de um requisito inteiro
da matriz original de 80 itens. Qualificação documental de exportação, demais
pendências 4B e aprovação de produção seguem independentes e abertas.
