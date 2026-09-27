# CLASSCONT.RHFOLHA

Sistema administrativo de **gestão de pessoal**: folha de ponto eletrônica, banco de horas, justificativas com aprovação da chefia e **auxílio-transporte** calculado a partir do ponto.

- **API REST** em **Symfony 7.4 LTS** (PHP 8.4, Doctrine, PostgreSQL, JWT)
- **Front separado** em **React + TypeScript + Tailwind CSS**, para servidores e chefias
- **Twig** no painel administrativo do RH, nos PDFs (espelho de ponto e demonstrativo) e nos e-mails

![CI](https://github.com/duanjesus/CLASSCONT.RHFOLHA/actions/workflows/ci.yml/badge.svg)

---

## Arquitetura

```
┌──────────────────────────┐        ┌──────────────────────────────────────────────┐
│  frontend/ (React + TS)  │  JWT   │  backend/ (Symfony)                          │
│  servidor · chefia       │ ─────► │  /api/*    Controllers → Services → Domain   │
│  Vite + Tailwind         │  JSON  │  /admin/*  Controllers → Forms → Twig        │
└──────────────────────────┘        │  PDFs      Twig → Dompdf                     │
                                    │  E-mails   Twig → Mailer (Mailpit em dev)    │
   RH (navegador) ────────────────► │                                              │
   sessão + form login              │  Doctrine ORM ─► PostgreSQL 16               │
                                    └──────────────────────────────────────────────┘
```

| Camada | Onde | Responsabilidade |
|---|---|---|
| **Domínio** | `backend/src/Domain` | Regras puras, sem framework: `CalculadoraEspelho`, `CalculadoraAuxilio`, `Competencia` (value object). São 100% testáveis. |
| **Serviços** | `backend/src/Service` | Casos de uso: buscam os dados nos repositórios, aplicam as regras e persistem. |
| **API** | `backend/src/Controller/Api` | Controllers finos, DTOs com `#[MapRequestPayload]` e validação, e JSON montado explicitamente em `Api/Representacao`. |
| **Painel** | `backend/src/Controller/Admin` + `templates/admin` | CRUDs com Symfony Forms e Twig, para uso do RH. |
| **Segurança** | `config/packages/security.yaml`, `src/Security/Voter` | Dois firewalls (JWT *stateless* para `/api` e sessão para `/admin`) e Voters para as regras de acesso. |

## Regras de negócio (bons tópicos de conversa)

**Ponto** (`CalculadoraEspelho`)
- As batidas são pareadas na ordem (entrada→saída, retorno→saída). Com número ímpar de batidas o dia fica **INCOMPLETO** e o período aberto não conta.
- Dia útil sem batida e sem abono é **falta**, com saldo igual a −jornada.
- **Tolerância de 10 min/dia** (CLT, art. 58 §1º): diferenças até esse limite são zeradas.
- Feriado e fim de semana não têm jornada prevista; trabalho nesses dias vira crédito.
- O dia de hoje fica "em andamento" e os dias futuros não entram no saldo.
- Dias anteriores à admissão não geram jornada nem falta.

**Justificativas**
- O servidor pede o abono de um dia, e a **chefia imediata** do setor dele ou o **RH** avaliam (`AvaliacaoVoter`). **Ninguém avalia o próprio pedido.**
- Só se justificam **dias úteis** que já passaram. O prazo é de 30 dias, e só vale uma justificativa ativa por dia.
- A recusa exige motivo. O resultado vai por e-mail, com template Twig.
- Uma justificativa aprovada **abona** o dia no espelho.

**Fechamento mensal**
- O RH fecha a competência (mês). A partir daí, as justificativas e avaliações daquele mês ficam **bloqueadas**.

**Auxílio-transporte** (`CalculadoraAuxilio`)
```
bruto    = valor diário das conduções × dias efetivamente trabalhados (vem do PONTO)
desconto = salário-base × 6% × (dias trabalhados / dias úteis)   ← proporcional
líquido  = bruto − desconto   (nunca negativo)
```
- Os valores monetários são calculados em **centavos (int)**, nunca em float. O `DECIMAL` do banco trafega como string.
- Um novo itinerário fica **pendente**, e o auxílio vigente continua valendo até a aprovação. Quando o novo é aprovado, o anterior passa a "substituído".

**Perfis**
- `ROLE_CHEFIA` **não é gravado** no banco: é derivado de o funcionário "ser chefe de algum setor" (`Funcionario::getRoles()`). Assim o papel nunca fica dessincronizado do cadastro.
- Funcionários não são excluídos, só desativados, porque o histórico de ponto é documento funcional.

**Segurança**
- Um funcionário desativado perde o acesso na hora, **inclusive com um JWT já emitido**. O `UserChecker` roda em toda autenticação.
- **Proteção contra força bruta**: `login_throttling` na API e no painel permite 5 tentativas erradas por e-mail/IP por minuto.
- Na API, só erros de negócio ou de entrada (`RegraNegocioException`, `CompetenciaInvalidaException`) devolvem a mensagem ao cliente (422). Qualquer outra exceção vira 500, sem expor detalhes internos.
- As ações destrutivas do painel exigem POST com token CSRF.

---

## Guia de estudo do Twig neste projeto

| Conceito | Onde ver |
|---|---|
| Herança (`extends` / `block`) em 3 níveis | `templates/base.html.twig` → `admin/layout.html.twig` → `admin/*/index.html.twig` |
| `block()` para reaproveitar o conteúdo de um bloco | `templates/pdf/_layout.html.twig` (título repetido no cabeçalho) |
| Macros (`import` / `macro`) com parâmetros padrão | `templates/admin/_macros.html.twig` (cabeçalho, badge de status, botão de exclusão com CSRF, seletor de mês) |
| `include ... with {...} only` (partial isolado) | `templates/shared/_tabela_espelho.html.twig`, **o mesmo partial** usado na tela do painel e no PDF |
| Form theme (sobrescrever blocos do `form_div_layout`) | `templates/admin/form/tema_tailwind.html.twig` |
| Formulário genérico reaproveitado por todos os CRUDs | `templates/admin/crud/form.html.twig` |
| **Extensão Twig própria** (filtros `horas`, `centavos`, `competencia`, `matricula`) | `src/Twig/RhExtension.php` (atributos `#[AsTwigFilter]`) |
| Filtros do `twig/intl-extra` (`format_currency`, `format_date`) | `admin/cargo/index.html.twig`, `admin/feriado/index.html.twig` |
| Arrow functions: `map`, `reduce`, `join` | `shared/_tabela_espelho.html.twig`, `admin/relatorio/auxilio.html.twig` |
| `for ... else`, `loop.first`, iterar hash `for chave, valor in {...}` | `admin/funcionario/espelho.html.twig`, `admin/setor/index.html.twig` |
| `set` com bloco (`{% set x %}...{% endset %}`) | `admin/dashboard.html.twig` |
| Variáveis globais `app.user`, `app.flashes`, `app.request` | `admin/layout.html.twig` |
| `csrf_token()`, `path()`, `asset()`, `importmap()` | macros e layouts do painel |
| Twig gerando **PDF** (Dompdf) | `templates/pdf/*` + `src/Service/GeradorPdf.php` |
| Twig gerando **e-mail** (`TemplatedEmail`) | `templates/emails/resultado_avaliacao.html.twig` + `src/Service/Notificador.php` |
| Tailwind no Twig (sem Node, via `symfonycasts/tailwind-bundle`) | `assets/styles/app.css` |

---

## Como rodar

Pré-requisito: **Docker**. Não é preciso ter PHP, Composer nem Node instalados.

```bash
docker compose up -d
```

Na primeira subida o container `php` instala as dependências, gera as chaves JWT, aplica as migrations e carrega os dados de demonstração. Acompanhe com `docker compose logs -f php`.

| Serviço | URL |
|---|---|
| App do servidor/chefia (React) | http://localhost:5173 |
| Painel do RH (Twig) | http://localhost:8081/admin |
| API | http://localhost:8081/api |
| Caixa de e-mails (Mailpit) | http://localhost:8025 |

### Usuários de demonstração (senha `senha123`)

| E-mail | Perfil |
|---|---|
| `rh@classcont.local` | RH: painel administrativo, avalia qualquer setor, chefe da SGP |
| `chefe.ti@classcont.local` | Chefia da STI (equipe: Ana e Bruno) |
| `ana@classcont.local` | Servidora da STI, com auxílio aprovado e um dia com batida incompleta |
| `bruno@classcont.local` | Servidor da STI, com banco de horas positivo, uma falta com justificativa pendente e um auxílio pendente |
| `chefe.financeiro@classcont.local` | Chefia da SOF (equipe: Carla) |
| `carla@classcont.local` | Servidora da SOF, jornada de 6h, com uma justificativa recusada |

Os dados são gerados **relativos à data atual** (mês anterior + mês corrente).

### Comandos úteis

```bash
# Recarregar os dados de demonstração
docker compose exec php php bin/console doctrine:fixtures:load -n --purge-with-truncate

# Testes (cria o banco de teste na primeira vez)
docker compose exec php sh -c "php bin/console -e test doctrine:database:create --if-not-exists && php bin/console -e test doctrine:migrations:migrate -n && php bin/console -e test doctrine:fixtures:load -n --purge-with-truncate && php bin/phpunit"

# Qualidade
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run

# Instalar um pacote PHP (vendor/ fica num volume do Docker)
docker compose exec php composer require <pacote>
```

> **Por que `vendor/` e `var/` ficam em volumes nomeados?** No Windows e no macOS, o bind mount dessas pastas deixava cada request com cerca de 6 s. Com volumes, cai para cerca de 0,3 s.

---

## Qualidade

- **65 testes / 238 asserções**:
  - *Unitários*: cálculo do espelho (tolerância, faltas, abonos, feriados), auxílio (proporcionalidade, arredondamento, teto), `Competencia` e os filtros Twig.
  - *Funcionais*: login JWT, bloqueio de desativados, força bruta, permissões (colega × chefia × outro setor × RH), o fluxo completo de aprovação com e-mail, PDF, validação e o smoke test de todas as telas Twig do painel.
- O **DAMA DoctrineTestBundle** isola cada teste numa transação.
- **PHPStan nível 6** sem erros, **PHP-CS-Fixer** (regras @Symfony), `lint:twig` e `lint:container`.
- O **GitHub Actions** roda backend (com Postgres) e frontend (lint e build) a cada push.

## Estrutura

```
backend/
  src/Domain/          regras puras (calculadoras, value objects)
  src/Service/         casos de uso
  src/Controller/Api/  API JSON (JWT)
  src/Controller/Admin painel Twig (sessão)
  src/Security/Voter/  quem pode ver/avaliar o quê
  src/Twig/            extensão com filtros próprios
  templates/           admin/, pdf/, emails/, shared/
  tests/Unit|Functional
frontend/
  src/pages/           Início (bater ponto), Espelho, Justificativas, Auxílio, Aprovações, Equipe
  src/api/             cliente axios + tipos do contrato da API
docker/                Dockerfile PHP-FPM 8.4, Nginx
```
