# A148 — referências materiais de recolhimento

BaseGit87d22e7. Arquivo de A alterado: `CollectionDecisionEngine.php`.
Escopo4B.3.1/4B.3.2/4B.3.7/4B.3.8/4B.3.10; sem nova regra de incidência.

## Defeito comprovado e correção

Nos dois runners, espaço em instrumento/transação/fornecedor/adquirente era
aceito em uma qualificação de recolhimento pelo adquirente. Os quatro probes
anteriores retornaramVALID/VALID_WITH_WARNINGS; a caracterização teve23 falhas
e2 passes. O componente também aceitava referência de implantação em branco
quando recebia parâmetro sintético de implantação ativa.

As referências usadas na qualificação agora devem ser textuais, utilizáveis,
sem padding/separadores nas bordas ou controles. Não são corrigidas por trim,
convertidas para número nem confundidas com autenticação. Falta de conteúdo
geraACTION_REQUIRED e o fato exato para correção. ID0/01/0001 e texto Unicode
válido são preservados. Não se introduz formato cadastral inventado.

Fornecedor/recolhedor e adquirente/pagador elegível continuam separados.
Elegibilidade não prova pagamento ou extinção; o caso válido permanece
`extinction_status=UNCONFIRMED`. A falta de implantação oficial do split e as
contradições de instrumento mantêm suas recusas próprias.

## Evidências terminais

-33 testes dirigidos/125 asserções.
-254 testes/860 asserções na regressão dirigida, incluindo os33; não somar.
-Quatro referências corrigidas nos dois runners, com perguntas existentes.
-CLI válida executada com a classe anterior e com a atual:bytes idênticos.
-Snapshot válido anterior reproduzido integralmente pela classe atual.
-CLI de transação em branco:ACTION_REQUIRED,sem decisão. Seu exit0 é o contrato
  da CLI existente `fiscal:rtc:run`; não significa aprovação fiscal.
-PHPStan dos componentes147/148:zero erros; Pint dos trêsPHP148:PASS.

Recibo `validation-A148.json`; verificador independente
`scripts/agent_a/verify148.py`. Diretório externo de fontes,probes,JUnit e logs:
`D:/ProjetoSaasExports/agent-a-A148-collection-evidence-20261103-v1`.

Sem mudança em assinatura, catálogos, fatos/perguntas globais, schema, crédito,
factory, engine central ou console. Nenhum parâmetro sintético virou implantação
real. O ciclo de opção/recolhimento/liquidação, demais perfis e aceites completos
continuam pendentes. Nenhum grupoA encerrado; sem commit,push ou publicação.
