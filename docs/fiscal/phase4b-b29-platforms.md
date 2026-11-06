# B29: consequências do art. 22 para plataformas digitais (05/11/2026)

O Agente B, assumindo o trabalho do A, cobre os itens 4B.2.12 (intermediários e plataformas) e 4B.3.5 (plataforma).
Fonte: LC 214/2025 art. 22, na redação da LC 227/2026, conforme `lc214-camara-observed-20261001.html`.

O `PlatformResponsibilityEngine` já qualificava a plataforma (§§1º, 2º e 11) e a responsabilidade dos incisos I e II.
O componente novo `PlatformConsequencesB29`, atrás do gate `platform_consequences_b29` (bundle `2026.11.05-b29-draft1`),
acrescenta as consequências:

| Dispositivo | Resultado |
|---|---|
| §3º | Fornecedor estrangeiro que opera só pela plataforma inscrita no regime regular fica dispensado da inscrição |
| §7º | A plataforma não responde por diferenças se houver split disponível, se ela tiver dado as informações do §6º e se tiver reportado a operação (§5º). Hoje o split não está implantado, então essa isenção ainda não se aplica |
| §10 | Fornecedor nacional inscrito (regular ou favorecido): débito pelas regras dele. Demais casos: regras do regime regular |
| §12 | Emitir e pagar em nome do fornecedor, com evidência de anuência; o fornecedor mantém as diferenças |
| §13 | Substituta tributária por opção, com anuência |
| §14 | Se a plataforma regularizar em até 30 dias (documento e pagamento), acréscimos e multa ficam só com o fornecedor |
| §15 | As alíquotas de referência **nunca são inferidas**: sem as regras do fornecedor, `RTC_PLATFORM_REFERENCE_RATES_NOT_PUBLISHED` |

A decisão ganha `platform_b29`, com emissor, pagador, responsável pelas diferenças e regra de cálculo.
Nenhuma notificação, anuência ou pagamento é autenticado.

Teste: `tests/Fiscal/Integration/PlatformConsequencesB29Test.php`, 9 testes.

**Dependências externas:**
- o regulamento das opções dos §§12–13;
- a implantação do split, que é a condição do §7º;
- as alíquotas de referência do §15 (a CBS de 2027 aguarda o Senado);
- o informe do CGIBS/RFB sobre a condição de contribuinte (§4º), que segue declarado, não autenticado.
