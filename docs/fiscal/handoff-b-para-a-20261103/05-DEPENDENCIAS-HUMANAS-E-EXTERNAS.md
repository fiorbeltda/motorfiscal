# Dependências humanas e externas (somente o que o agente não resolve sozinho)

## O que você precisa fazer pessoalmente

1. **Encaminhar este pacote ao seu colega** e acionar o Agente A com `02-HANDOFFS-PARA-O-AGENTE-A.md` (H1–H5).
2. **Recuperar as 34 pastas de evidências da máquina antiga.** A lista exata, com finalidade e testes afetados, está em
   `evidencias/b05-evidence-inventory.json` e em `docs/fiscal/phase4b-b05-evidence-and-xsd-request.md`.
   - Origem: `C:/xampp/htdocs/projeto-saas-motor/storage/app/<pasta>` ou `D:/ProjetoSaasExports/preserved-storage-app/<pasta>`.
   - Copie as pastas inteiras, sem converter fim de linha.
   - A reconstrução automática foi avaliada e **não é viável**: os geradores dependem de outras pastas ausentes e gravariam em `resources/`.
3. **Baixar manualmente o ZIP XSD NT004** (o download automatizado devolve HTML/403):
   `https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/producao-restrita/nfse-esquemas_xsd-rtc-v1-00-20251210.zip`.
   Hash da cópia já preservada, para comparar: `04662996e290add36f118c4457d8efed17848eb65d64e8b7ab56c6983c2429ad`.
4. **Autorizar** a integração A+B e, depois, a publicação.
5. **Opcional (ambiente):** ligar o VT-x na BIOS desta máquina, para o Docker e o runtime do contêiner. Sem isso, a validação final
   deve rodar em outra máquina com ambiente equivalente.
6. **Revisão jurídica (opcional, já sinalizada):**
   - acréscimos em registro tardio de estorno do art. 47, §6º (B11);
   - interpretação da "glosa de crédito" como anulação por apropriação indevida (B11).

## Dependências externas oficiais (situação em 03/11/2026)

| Fonte | Situação | O que depende dela |
|---|---|---|
| XSD oficial com os campos NT009 (`nFatura`, `vFatura`, `nContCambio`, `vAjusteBCIBSCBSComExt`) | Não publicado. Páginas RTC (atualizada em 26/10/2026 15h52) e Produção Restrita (20/10/2026 18h10) reconferidas | 4B.7.1, 4B.8.10; aceite XSD do 143 |
| Vigência e seleção por ambiente dos catálogos (cIndOp, Anexo VIII) | Não declaradas; o Anexo VIII consta "sem regras de negócio" | 4B.6.1, 4B.6.2 |
| Alíquota de referência da CBS de 2027 em diante (art. 347; Senado) e IBS de 2029 em diante | Não publicadas | 4B.8.5, 4B.8.12 (cálculo positivo futuro) |
| Série Selic oficial e data de pagamento | Externas | Valor dos acréscimos do estorno (art. 29, §2º) |
| Regulamento (proporção do ativo imobilizado, art. 47, §7º; percentuais dos arts. 168 e 169) | Não preservado ou publicado | B11 ativo imobilizado; B17 arts. 168, 169 e 171 |
