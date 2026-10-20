# Motor Fiscal Sandbox

Motor de decisão fiscal headless, determinístico e auditável, construído com Laravel 13 e PHP 8.5.

O bundle atual é um `SANDBOX_CANDIDATE`. Os parâmetros municipais embarcados são fixtures sintéticas e toda decisão baseada neles retorna aviso; o projeto ainda não está aprovado para uso fiscal em produção.

## Runtime

- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Docker Compose

O MVP não possui frontend. Cenários, validação e publicação de regras, importações, replay e diff são executados por comandos Artisan com entrada e saída JSON.

## Ambiente oficial

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan fiscal:corpus:run
```

O PostgreSQL 18 é criado pelo Compose; não é necessário instalá-lo diretamente no Windows. `http://localhost:8080` serve apenas para o health check do Laravel. A interface do motor é a linha de comando.

## Gates de qualidade

```bash
composer lint
composer analyse
composer test
composer audit
```

## Comandos fiscais

```bash
php artisan fiscal:rules:validate
php artisan fiscal:source:register SOURCE.CHAVE VERSAO caminho/artefato --authority=OFFICIAL_FILE
php artisan fiscal:rules:publish MVP-ISS 2026.09.16-1
php artisan fiscal:catalog:import municipal-rates caminho/arquivo.zip --dry-run
php artisan fiscal:catalog:import municipal-parameters caminho/snapshot.json --dry-run
php artisan fiscal:catalog:materialize-iss-services caminho/anexo-b.xlsx caminho/anexo-i.md caminho/catalogo.json --catalog-version=2026.09.17-1
php artisan fiscal:catalog:import lc116 caminho/lc116.html --dry-run
php artisan fiscal:catalog:import ctribnac caminho/anexo-b.xlsx --dry-run
php artisan fiscal:catalog:reconcile caminho/lc116.html caminho/anexo-b.xlsx caminho/catalogo-territorial.md
php artisan fiscal:scenario:run resources/fiscal/scenarios/software/valid.json --snapshot=storage/software-snapshot.json
php artisan fiscal:corpus:run
php artisan fiscal:decision:replay storage/software-snapshot.json
php artisan fiscal:decision:diff storage/resultado-a.json storage/resultado-b.json
```

O bundle da Fase 3 é selecionado com `--bundle-manifest=resources/fiscal/bundles/iss-phase3-2026.09.17-1.json`. Suas regras também estão publicadas no PostgreSQL como `ISS-PHASE3@2026.09.17-1`.

O bundle em desenvolvimento da Fase 4A é selecionado com `--bundle-manifest=resources/fiscal/bundles/federal-phase4a-2026.09.17-1.json`. Ele acrescenta ao ISS da Fase 3 a avaliação segregada de IRRF, PIS, Cofins, CSLL e INSS. A decisão de avaliar retenções deve ser informada pelo fato `federal.evaluate_withholdings`; perfis ainda não incorporados retornam ação requerida, sem aplicar regra privada por aproximação.

Após importar um catálogo municipal no banco, use juntos `--municipal-source-key=CHAVE_DA_FONTE` e `--municipal-source-version=VERSAO_EXATA` em `fiscal:scenario:run` ou `fiscal:corpus:run`. O snapshot captura somente as respostas efetivamente usadas, permitindo replay sem consultar novamente o banco.

Para executar regras já publicadas no banco, use juntos `--rule-bundle-key=CHAVE` e `--rule-bundle-version=VERSAO` nos mesmos comandos. A seleção é explícita e o `rule_bundle_id` do cenário deve corresponder a `CHAVE-VERSAO`. Apenas as regras são substituídas: facts, catálogos, perguntas, invariantes e schemas continuam vinculados ao manifesto local. Hash divergente, versão não publicada, bundle homônimo incompatível ou referência a catálogo local indisponível bloqueiam a execução.

Importações persistentes de catálogo sem `--effective-from` são registradas como `CANDIDATE`. O identificador de data presente no nome de um arquivo não é aceito como prova automática de vigência.

## Documentação

- [Estado da implementação](docs/fiscal/implementation-status.md)
- [Fechamento da Fase 3](docs/fiscal/phase3-closure.md)
- [Andamento da Fase 4A](docs/fiscal/phase4a-status.md)
- [Cobertura do MVP](docs/fiscal/mvp-coverage.md)
- [Avaliação dos importadores](docs/fiscal/import-assessment-2026-09-16.md)
- [Semântica de precedência](docs/fiscal/rule-precedence-semantics.md)
- [Decisões arquiteturais](docs/adr/README.md)
