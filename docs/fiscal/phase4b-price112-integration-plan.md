# Próxima integração112 — preços110/111

Plano inicial preservado. Atualização19/10:112 instalado e verificado de forma
direcionada;ampliação,CLI/regressão e aceite final ainda pendentes.
Estado atual em [phase4b-price112-current-integration.md](phase4b-price112-current-integration.md).
108 permanece o predecessor aceito. A lista abaixo registra o plano original.

1. Arquivar código/recursos afetados e conferir os83 hashes108 antes de mudar
   runtime. Preservar o aceite e seus13.395 inputs históricos.
2. Definir transporte explícito de preços,composições e respectivas provas,
   com facts/perguntas e opt-in próprio. Entrada ausente/desativada preserva
   os resultados nominais108;entrada incompleta não permite fallback silencioso.
3. Auditar o grafo de hashes105/106/107/110/111 antes de instalar. As provas
   carregadas nos fatos não podem criar autorreferência;excluir somente os
   carriers de prova explicitamente contratados. Conservar nos hashes os
   registros monetários e demais fatos fornecidos,de negócio ou desconhecidos.
   Não ampliar exclusões genéricas de campos ou tratar negócios como apresentação.
4. Requalificar os predecessores no contexto atual,sem confiar em flags do
   chamador. Provar igualdade entre argumentos e os registros transportados.
   Regerar somente provas sintéticas próprias do novo contexto;conservar
   preços/datas/identidades reais do negócio e todos os corpus históricos.
5. Instalar classes em namespace definitivo,políticas/fontes/bundle próprios,
   testes permanentes,cenários,trace e replays. Cobrir bruto/líquidos,condições,
   datas distintas,zero líquido recusado por110,ciclo de vida e adulteração.
6. CorpusCLI independente,regressão completa com inventário congelado,qualidade
   e auditoria do novo aceite. Não reiniciar suíte viva por timeout.

Resultado nominal continua sujeito à obrigação documental. Composição de
preço na moeda declarada não é conversão/base/imposto ou liberação de XML.
Não copiar taxa/rateioRTC ou substituir a fixture municipal por fonte real
sem mapeamento,versão,efeitos e aplicabilidade comprovados.
