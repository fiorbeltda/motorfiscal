# Importação132 — reconciliação declarada de valores por documento

Em22/10/2026 foi integrada ao comando `fiscal:dps:import-candidate` a conferência
opcional `--values-document` com `--invoice-source`. As duas opções devem ser
fornecidas juntas. A execução preserva a captura de conhecimento129 e reexecuta
integralmente o snapshot antes de conferir valores ou gravar qualquer candidato.
O bundle fiscal corrente131-draft2 permanece intacto;132 é contrato documental,
sem novo bundle, regra tributária, alíquota ou conversão.

O contrato `IMPORT-DOCUMENT-VALUES-132-V1` vincula a declaração da fatura e linha
única à identificação da fatura de importação, NIF do fornecedor, adquirente,
serviço canônico e competência do snapshot. Confere SHA256 dos bytes fornecidos
e exige observação não posterior ao conhecimento. O perfil declara uma única
linha de serviço cobrindo toda a fatura, preço emBRL e ausência de deduçãoISS no
resultado já calculado. Compara separadamente preço declarado, `operation.amount`,
brutoISS e bruto originalRTC, com centavos exatos e sem arredondamento implícito.
Diferenças, campos extras, moedas não suportadas, arquivo trocado e ambiguidades
na trilhaISS impedem a gravação doXML e do relatório.

O relatório mantém a declaração literal e o cálculoISS completo e informa
`source_fields_extracted=false`, `source_authenticity_verified=false` e
`iss_legal_base_qualified=false`. A conferência não extrai campos dos bytes nem
autentica a declaração: o vínculo material entre conteúdo e campos declarados
continua dependendo da evidência de origem. Consistência aritmética e hash não
substituem qualificação fiscal ou documental. O campo `line_id` é a referência
declarada dessa única linha, não uma classificação obtida por leitura da fatura.

Sem as novas opções, o comando mantém o comportamento130 e não registra
reconciliação132. No perfil positivo, oXML com a conferência é idêntico ao
candidato130 preservado, inclusive os valores de serviço eCOMEX. Nenhum campo
NT009 ausente doXSD foi inventado. A decisão fiscal continua `ACTION_REQUIRED`,
`document_obligation_complete=false` e `production_ready=false`.

Validação final:57 testes/206 asserções, PHPStan sem erros ePint643 arquivos.
Uma decisão integrada com replay exato, doisXML determinísticos/XSD-válidos e
19 replays históricos exatos (um130 e18 do corte131). Auditoria independente de
242 conferências,108 hashes de execução correntes e verificação dos107 arquivos
oficiais de cada um dos bundles129/131 empregados. Logs, fonte sintética,
declaração, snapshot eXML preservados em
`D:/ProjetoSaasExports/phase4b-values132-20261022-v1`.

Recibo: `storage/app/phase4b-values132-development-20261022/values132-scoped-validation.json`.
SHA256: `1b9042995b355aa4fcc480a5d47b8cd218a28c3e12b8749851beabc55cd2e174`.
A matriz conserva as80 revisões anteriores e acrescenta evidência ao item4B.7.1.
Não houve suíte integral ou nova avaliação de liberação de produção neste corte;
122 continua o último aceite integral e0/8 grupos4B estão encerrados.

Permanecem pendentes a base legalISS por documento com classificação municipal
granular, composição de preço, moedas estrangeiras com contrato cambialISS
próprio, múltiplas linhas, demais perfis e o fechamento da obrigação documental.
A declaração de valores132 não resolve os limites documentados na fronteira113.
Os cenários e políticas históricos permanecem preservados.
