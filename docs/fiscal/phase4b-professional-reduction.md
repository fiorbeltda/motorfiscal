# Redução profissional de 30% e integração local

Bundle histórico deste corte: `CONSUMPTION-PHASE4B-2026.10.01-4`; código/facts/perguntas profissionais: `2026.10.01-3`. Fonte: LC 214 consolidada observada em 01/10/2026, identificada por hash no manifesto. Estado: Sandbox candidato; fase integral ainda incompleta. O candidato corrente está indicado em `phase4b-status.md`.

## Elegibilidade

`ProfessionalReductionQualifier` verifica profissão listada no art.127, conselho profissional e suas evidências. Pessoa física precisa de vínculo do serviço à habilitação. A sociedade precisa cumprir simultaneamente as cinco condições do §1º, II, comprovadas por evidência estrutural. Auxiliares ou colaboradores não são proibidos: a condição exige prestação direta da atividade-fim pelos sócios. A forma societária e a distribuição dos lucros não são condições adicionais. Para pessoa jurídica de educação física aplica-se a exceção do §3º, com fiscalização profissional obrigatória.

Prova ausente retorna `ACTION_REQUIRED` com o fato exato. Condição comprovadamente descumprida invalida a solicitação do benefício. Regra diferente, classe fornecida incompatível, vigência/publicação indisponível ou supersessão conhecida impedem o benefício. A regra não escolhe um código tributário a partir de uma declaração genérica de redução.

## Cálculo e documento

O motor usa alíquotas padrão comprovadas do destino e as multiplica exatamente por 70%; arredonda os valores monetários em `HALF_UP`, duas casas. O layout nacional observado possui percentuais efetivos com duas casas; se a taxa calculada não puder ser representada sem perda nessa precisão, retorna `RTC_EFFECTIVE_RATE_DOCUMENT_PRECISION_UNPROVEN` e exige prova da política documental aplicável. Não inventa CBS de 2027 ou taxas futuras de IBS.

No cenário de 2026 com base de 1.000,00: IBS estadual padrão 0,10%, efetivo 0,07%, nominal 0,70; IBS municipal 0%; CBS padrão 0,90%, efetiva 0,63%, nominal 6,30. A dispensa de recolhimento depende separadamente do cumprimento das obrigações acessórias. A redução não equivale a não incidência ou extinção do débito.

As regras interpretativas de advocacia e contabilidade domésticas remotas/digitais mapeiam `cIndOp=100301` e, no benefício, `CST=200 / cClassTrib=200052`. Exigem profissão coerente com o serviço canônico. Há também regras padrão. Revisão jurídica permanece necessária. Demais profissões têm qualificador, mas ainda precisam das regras documentais específicas de serviço/destino; não se promete cobertura positiva de toda profissão.

Na integração, ISS e retenções federais são avaliados separadamente. Os exemplos profissionais usam taxa municipal **sintética** de 5%, identificada como fixture, e resultam em ISS 50,00 e retenções federais 61,50. Não constituem aprovação de operação real. O XML preserva a classe do benefício; percentuais de redução e taxas efetivas ficam no enriquecimento nacional previsto. A validação retorna o conflito conhecido da série no XSD oficial, sem alterar a fonte ou substituir a série operacional por texto sintético.

## Reprodução

```powershell
docker compose exec -T app php artisan fiscal:rtc:corpus resources/fiscal/scenarios/consumption-phase4b-2026.10.01-4 --expected=resources/fiscal/expected/consumption-phase4b-2026.10.01-4 --bundle-manifest=bundles/consumption-phase4b-2026.10.01-4.json
docker compose exec -T app php artisan fiscal:corpus:run resources/fiscal/scenarios/consumption-integrated-2026.10.01-4 --expected=resources/fiscal/expected/consumption-integrated-2026.10.01-4 --bundle-manifest=bundles/consumption-phase4b-2026.10.01-4.json
docker compose exec -T app php artisan fiscal:decision:replay storage/app/phase4b-integrated-lawyer-20261001-4-snapshot.json
```

Resultados verificados: corpus RTC 7/7, integrado 2/2 e histórico `-1` 53/53; replay integrado `746edc2b2bb1b2fe27422bb10ecfd2cddf86f4477dcedeff8de4329e4c3439dc`; suíte completa 266 testes / 1.129 asserções; PHPStan sem erros, Pint nos oito arquivos PHP deste corte e `git diff --check` aprovados. Os cinco snapshots históricos de RTC, integrado, pós-ISS, NT004 e 4A também mantiveram seus resultados. Gate: 18/18 hashes válidos; elegível local; `ready=false`; 33 bloqueios; nenhum controle marcado como aprovado.
