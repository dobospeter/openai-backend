# OpenAI Backend

A REST API (Laravel 12, PHP 8.3+) for holding conversations with an OpenAI chat model. Clients create
**conversations** (model, temperature, system prompt) and post **messages** to them; each message is answered by
OpenAI and both sides are stored.

* CRUD REST API under `/api/v1`, errors in one format ([RFC 9457](https://www.rfc-editor.org/rfc/rfc9457))
* Domain-driven layout, PSR-4 / PSR-12 (checked with PHP_CodeSniffer)
* Docker stack: nginx + php-fpm + PostgreSQL
* The database is created entirely by migrations
* OpenAPI 3 documentation behind HTTP Basic auth at `/docs`, kept honest by contract tests

## Quick start (Docker)

```bash
cp .env.example .env
# edit .env: set OPENAI_API_KEY and DOCS_PASSWORD (and LOCAL_UID / LOCAL_GID to `id -u` / `id -g` on Linux)
docker compose up -d --build
```

On first start the `app` container installs the Composer dependencies, generates `APP_KEY` and runs the migrations
(set `RUN_MIGRATIONS=false` to skip that). The stack is then available on `http://localhost:8765`:

```bash
curl -X POST localhost:8765/api/v1/conversations -H 'Content-Type: application/json' \
     -d '{"title": "Trip planning", "model": "gpt-4"}'

curl -X POST localhost:8765/api/v1/conversations/<id>/messages -H 'Content-Type: application/json' \
     -d '{"content": "What should I see in Lisbon?"}'
```

Useful commands: `docker compose logs -f app`, `docker compose exec app php artisan migrate:fresh`,
`docker compose exec app composer check`.

### Without Docker

```bash
composer install
cp .env.example .env            # use DB_CONNECTION=sqlite, then: touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan serve
```

## API documentation

The OpenAPI 3 document lives in [`docs/openapi.yaml`](docs/openapi.yaml). The running application serves it
together with Swagger UI, protected by HTTP Basic authentication:

| URL                | Content                  |
|--------------------|--------------------------|
| `/docs`            | Swagger UI               |
| `/docs/openapi.yaml` | the raw OpenAPI document |

Credentials come from `DOCS_USERNAME` (default `docs`) and `DOCS_PASSWORD`. **While `DOCS_PASSWORD` is empty the
documentation stays locked.** Requests are rate limited (`DOCS_REQUESTS_PER_MINUTE`), which also slows down password
guessing. Swagger UI loads its JavaScript from the jsDelivr CDN, so the browser needs internet access.

"Public URL" simply means deploying the application where it can be reached from the internet: `/docs` is part of
it. Put it behind HTTPS in that case, as Basic auth sends the password in every request.

## Endpoints

| Method | Path                                           | Description                              | Success |
|--------|------------------------------------------------|------------------------------------------|---------|
| GET    | `/api/v1/conversations`                        | List (`?page=`, `?per_page=`)            | 200     |
| POST   | `/api/v1/conversations`                        | Create (all fields optional)             | 201     |
| GET    | `/api/v1/conversations/{id}`                   | Read                                     | 200     |
| PUT    | `/api/v1/conversations/{id}`                   | Replace the settings (all fields needed) | 200     |
| DELETE | `/api/v1/conversations/{id}`                   | Delete with its messages                 | 204     |
| GET    | `/api/v1/conversations/{id}/messages`          | List, oldest first                       | 200     |
| POST   | `/api/v1/conversations/{id}/messages`          | Send a message, get OpenAI's answer      | 201     |
| GET    | `/api/v1/conversations/{id}/messages/{id}`     | Read                                     | 200     |
| DELETE | `/api/v1/conversations/{id}/messages/{id}`     | Delete                                   | 204     |
| GET    | `/api/version`                                 | Application version                      | 200     |
| GET    | `/up`                                          | Health check                             | 200     |

Sending a message passes the conversation's model, temperature, system prompt and the latest
`OPENAI_HISTORY_LIMIT` messages to OpenAI. The question and the answer are stored together only when OpenAI
succeeded, so a failed call leaves nothing behind and can be retried. This endpoint has a tighter rate limit
(`OPENAI_MESSAGES_PER_MINUTE`).

### Errors

Every error from `/api/*` is an `application/problem+json` document:

```json
{
  "type": "about:blank",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "The request data is invalid.",
  "code": "validation_failed",
  "errors": { "temperature": ["The temperature field must be between 0 and 2."] }
}
```

`code` is the stable identifier to program against; `errors` only appears on validation failures.

| Status | Codes                                                                      |
|--------|----------------------------------------------------------------------------|
| 400    | `bad_request` (malformed JSON)                                             |
| 404    | `conversation_not_found`, `message_not_found`, `not_found` (no such route) |
| 405    | `method_not_allowed`                                                       |
| 422    | `validation_failed`, `invalid_value`                                       |
| 429    | `too_many_requests` (with `Retry-After`)                                   |
| 500    | `internal_error` (details hidden unless `APP_DEBUG=true`)                  |
| 502    | `chat_completion_failed` (OpenAI rejected the call or answered nonsense)   |
| 504    | `chat_completion_timeout`                                                  |

All of it is produced by one class, `ApiExceptionRenderer`. Domain code throws exceptions that say *what* went wrong
(`NotFound`, `InvalidValue`, `UpstreamFailure` ...) and know nothing about HTTP; the renderer maps them to statuses.

## Architecture

Everything lives under `app/`, in three parts:

```
app/
  Api/                         HTTP layer, one folder per API module
    Conversation/
      Controllers/  Requests/  Resources/  Routes/
    Version/                   GET /api/version
    Docs/                      Swagger UI + OpenAPI document behind Basic auth
      Controllers/  Middleware/  Routes/
  Core/                        Kernel shared by all modules
    Domain/                    Identifier (UUIDv7), Page / PageRequest, domain error categories
    Application/               TransactionManager port
    Infrastructure/            SystemClock (PSR-20), Laravel transaction manager, CoreServiceProvider
    Http/                      Problem details + ApiExceptionRenderer, pagination request, middleware
  Modules/                     Business logic, one folder per module (no HTTP in here)
    Conversation/
      Domain/                  Conversation + Message aggregates, value objects, repository and
                               ChatCompletionClient ports, domain exceptions
      Application/             One use case per folder: command/handler pairs (CreateConversation,
                               SendMessage, ...)
      Infrastructure/          Eloquent repositories, OpenAI HTTP client, ConversationServiceProvider
  Providers/                   Laravel's AppServiceProvider (rate limits, trusted proxies)
```

Each module's routes are in `app/Api/<Module>/Routes` and registered in `bootstrap/app.php`.

Dependencies point inwards: `Api` and `Modules/*/Infrastructure` depend on `Modules/*/Application` and `Domain`, never the
other way round. The domain is plain PHP (no Eloquent, no HTTP client); Eloquent models are private to the repositories.

* **SOLID**: handlers do one thing each; use cases depend on interfaces (`ConversationRepository`,
  `MessageRepository`, `ChatCompletionClient`, `ClockInterface`, `TransactionManager`) that the service provider binds
  to implementations.
* **DRY**: validation limits live as constants on the domain value objects and are reused by the form requests; the
  error format is built in one place; pagination and settings validation are shared.
* **Time** is injected through the PSR-20 `ClockInterface`, so tests are deterministic.
* Identifiers are UUIDv7, generated by the domain before saving.

## Configuration

Everything is configured through `.env` (see [`.env.example`](.env.example)).

| Variable                         | Meaning                                                              |
|----------------------------------|----------------------------------------------------------------------|
| `OPENAI_API_KEY`                 | OpenAI key. Without it messages fail with `chat_completion_failed`   |
| `OPENAI_ORGANIZATION`            | Optional organisation header                                         |
| `OPENAI_API_URL`                 | Base URL, change it to talk to a compatible server                   |
| `OPENAI_MODEL`, `OPENAI_TEMPERATURE` | Defaults for new conversations                                   |
| `OPENAI_ALLOWED_MODELS`          | Comma separated list of models clients may choose                    |
| `OPENAI_HISTORY_LIMIT`           | Messages sent along as context                                       |
| `OPENAI_TIMEOUT`                 | Seconds before a call counts as timed out (504)                      |
| `OPENAI_MESSAGES_PER_MINUTE`     | Rate limit of the message endpoint, per client address               |
| `DOCS_USERNAME`, `DOCS_PASSWORD` | Basic auth of `/docs`                                                |
| `TRUSTED_PROXIES`                | Load balancers whose `X-Forwarded-*` headers are believed (see below) |
| `WEB_PORT`, `DB_FORWARD_PORT`    | Host ports of nginx and PostgreSQL (the latter on 127.0.0.1 only)    |

Rate limits are keyed by client IP. By default no proxy is trusted, which is right when the bundled nginx is the edge.
If you put a load balancer in front, set `TRUSTED_PROXIES` to its address (or `*` if nothing else can reach the
container), otherwise all clients share the balancer's address.

## Tests and code style

```bash
composer test        # PHPUnit: unit tests, then HTTP and database tests (SQLite in memory)
composer pint        # code style check (Laravel Pint, PSR-12 preset + sorted imports, strict_types)
composer pint:fix    # apply the Pint fixes
composer cs          # PSR-1 / PSR-12 check (PHP_CodeSniffer, config: phpcs.xml.dist, 120 chars hard limit)
composer cs:fix      # let phpcbf fix what it can
composer stan        # static analysis (PHPStan level 6 + Larastan + phpstan-phpunit, config: phpstan.neon)
composer check       # pint + cs + stan + test, the same gate a CI job should run
```

PHPStan runs without an ignore list or baseline, so every finding is either fixed or a real decision.

### Git hook

```bash
composer hooks:install     # git config core.hooksPath .githooks
```

`.githooks/pre-commit` checks the **staged** PHP files (added, copied, modified and renamed ones) with `php -l`,
Pint, PHP_CodeSniffer and PHPStan, and blocks the commit when one of them fails. Skip it once with
`git commit --no-verify`.

* Tests follow Arrange / Act / Assert (marked by comments) and use data providers wherever a rule has several cases.
* `tests/Unit` covers domain and use cases with in-memory fakes (`tests/Support`), no framework boot.
* `tests/Feature` drives the real HTTP stack and database, and fakes OpenAI with `Http::fake()`.
* The API tests also validate every response against `docs/openapi.yaml`
  ([osteel/openapi-httpfoundation-testing](https://github.com/osteel/openapi-httpfoundation-testing)). If the
  documentation and the behaviour diverge, the build fails.
* The test environment is forced in `phpunit.xml` (in-memory SQLite, fake OpenAI URL), so running the suite inside
  the Docker container cannot touch the PostgreSQL development data.

## Notes

* The API has **no authentication**; anyone who can reach it can spend your OpenAI credit. Rate limiting only
  mitigates that. Put it behind a gateway or add authentication before exposing it publicly.
* The earlier `messages` table (with its `user_id` and JSON metadata) was replaced by `conversations` + `messages`
  tables. On a database created from the old migrations run `php artisan migrate:fresh`.
