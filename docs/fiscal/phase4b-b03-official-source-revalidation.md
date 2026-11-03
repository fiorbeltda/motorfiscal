# B03 — Revalidação das fontes oficiais de 4B.7 e qualificação da NT 010 (Agente B, 03/11/2026)

Base `87d22e7` + B01/B02 locais, sem commit. Corte somente de fontes: nenhuma regra ou código fiscal foi alterado.

## Requisitos

4B.7.2 (documentação técnica oficial), 4B.7.4 (versionamento de schema) e 4B.6.2 (cIndOp). Nenhum encerrado.

## Resultado por fonte (portal https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc, consultado em 03/11/2026)

| Fonte | SHA-256 obtido em 03/11 | Comparação |
|---|---|---|
| AnexoVI-LeiautesRN_RTC_IBSCBS v1.04.01 (NT009) | `103a150d…df39` | igual ao registrado no 143 |
| NT SE/CGNFS-e 009 v1.01 (01/10/2026) | `cc86c940…a58e` | igual ao registrado no 143 |
| AnexoVII-IndOp v1.03.00 (NT009) | `95f30a44…e5aa` | igual à fonte do cIndOp 126 |
| `nfse-esquemas_xsd-rtc-v1-00-20251210.zip` | não obtido | o portal devolveu HTML (200) e depois `403 Forbidden`; resposta guardada como `.HTML-RESPONSE-NOT-ZIP.html` |
| NT SE/CGNFS-e 010 v1.00 (11/09/2026) | `18cd169f…f104` | nova; preservada em `resources/fiscal/sources/official/` |

- O XSD usado pelo 143 é a cópia preservada `dps127-restricted-xsd-observed20261022-20261020.zip` (`25fdcecc…a267`).
  Não houve como comparar o ZIP atual do portal; **nenhuma mudança é alegada**. A página continua listando apenas
  esse pacote XSD (NT004), sem XSD com os campos NT009.

## NT 010

- Trata exclusivamente da NFS-e Via (exploração de via): domínio `modPagamento` "6. Sem cobrança" (leiaute 1.01)
  e Evento de Manifestação do Adquirente (aceite `203202`, rejeição `203204`, motivos 1–8 e 99), com manual 1.02 e
  Anexo IV de leiautes do ADN.
- O motor só usa a NFS-e Via como indicador de modelo (`indNFSe Via` no cClassTrib 100) e no cronograma do Ato 4
  (`NFSE_VIA`, a partir de 2026-08-03). Ele não projeta NFS-e Via nem eventos do ADN. **Sem impacto nos perfis DPS atuais.**
- Inconsistências internas registradas, não resolvidas: `CNPJCPF` com tamanho "11 ou 14" (aceite) e "11-14"
  (rejeição); `cMotivo` com tamanho 1 e domínio `99`.
- Se o produto passar a emitir NFS-e Via, o evento de manifestação deverá ser qualificado como fonte para 4B.5.9
  (vínculo documento/crédito do adquirente). Nada foi implementado por inferência.
