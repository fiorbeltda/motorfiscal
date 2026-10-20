# Limite adicional de timestamp — offset cambial

A geração69 valida datas reais e reprodução exata do timestamp, mas o parserPHP8.5 preserva offsets como `+99:00`. A execução de14/10/2026 confirmou que `2026-09-24T00:00:00.000000+99:00` é reproduzido literalmente. Portanto, essa igualdade não basta para provar um offset civil válido.

A migração de origem70 também deve exigir explicitamente minuto de offset entre00 e59 e hora civil dentro do intervalo suportado. Para os testemunhos fiscais contemporâneos desse caminho local, o contrato deverá declarar e testar o intervalo até±14:00, com minuto00 quando a hora for14. Não interpretar normalização silenciosa de offset como validade de fonte ou data.

Os seis contraexemplos de datas impossíveis corrigidos69 e seus18 replays permanecem evidência própria. A suíte69 em execução testa essa geração; não demonstra a nova guarda de offset, a origem material não USD ou conversão cruzada. A aceitação completa do escopo cambial permanece aberta até conferir esses requisitos. Os documentos/protótipos de origem70 previamente auditados permanecem preservados; qualquer evolução será arquivada e emitida como geração sucessora.

Critérios adicionais: testes de±14:00,±14:01, hora superior14, minuto superior59, ausência de offset e datas impossíveis; verificar política, testemunho e dados cambiais relevantes. O resultado de uma cotação numérica continua separado da prova legal da origem e do conhecimento. Produção permanece bloqueada.
