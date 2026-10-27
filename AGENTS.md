# AGENTS.md

Instructions for AI coding agents working in this repository (`voice-generator` — speech synthesis HTTP API
and local speak console).

## Project overview

PHP **>=8.2** speech-synthesis API (not a Symfony full-stack app). It uses a custom DI / routing stack
(`ngsoft/container`, `ngsoft/routing`) plus selected Symfony components (HttpFoundation, Console, Messenger,
Dotenv, Cache, Process, Yaml, Translation).

Domain:

- **TTS providers** — Microsoft Edge (`MicrosoftEdgeVoiceProvider` / `EdgeTTS`) and ElevenLabs
  (`ElevenLabsVoiceProvider`), orchestrated by `SynthesisProviderStack`
- **HTTP API** under `/api/*` — list voices/providers, synthesize (`POST /api/speak`), download audio, translate
- **CLI** — `php bin/console speak` (sync playback or async queue via Messenger); `speak:remote` (remote instance via `SPEAK_REMOTE_URL` / `SPEAK_REMOTE_API_KEY`)
- **Web UI** — Svelte + Vite + Skeleton (`app/`), served with a lightweight PHP front controller
- **OpenAPI** — `zircote/swagger-php` attributes; live Redoc UI at `/api/doc`

Remote: `git@github.com:ngsoft/voice-generator.git`

- Live OpenAPI UI: `/api/doc` (Redoc), JSON: `/api/doc.json`, YAML download: `/api/doc.yaml`
- Committed machine-readable contract: `openapi.yaml` (snapshot dumped from the same generator)
- Source of truth for the contract: OpenAPI attributes on controllers / views / models / speech DTOs
  (`OpenApi\Attributes as OA`), scanned paths in `config/openapi.php`
- Runtime generator: `Services::getOpenApi()` (swagger-php `Generator` + generated `OpenApiMetadata` for
  Info / Server / License)

Keep existing security middleware, response envelopes, and OpenAPI attributes when extending the API — do not
weaken ACL / API-key checks.

## Mandatory maintenance

When you change the product surface, keep docs in sync in the **same change set**:

1. **OpenAPI** — Update swagger-php attributes (`#[OA\…]`) whenever API routes are added, removed, renamed, or their
   request/response/query/path contracts change (methods, params, schemas, security, status codes). Keep scanned
   directories in sync in `config/openapi.php` → `openapi.paths` when new DTO / controller locations are introduced.

   Then regenerate the committed contract:

   ```bash
   php -r "require 'src/init.php'; file_put_contents('openapi.yaml', Services::getOpenApi()->toYaml());"
   ```

   Alternatives when a local server is running (port `8600` by default via `npm run serve`):

   - `curl -fsS http://127.0.0.1:8600/api/doc.yaml -o openapi.yaml`
   - Browser / client download of `/api/doc.yaml`

   Then validate the exported schema (prefer **`bunx` over `npx`** when available; fall back to `npx`):

   ```bash
   bunx @redocly/cli lint openapi.yaml
   # or: npx @redocly/cli lint openapi.yaml
   ```

   If errors or warnings are reported, fix the corresponding OpenAPI attributes and regenerate until the schema is
   clean. Do not invent `npm run openapi:*` scripts unless asked — this repo has none.

   **ACL / auth in OpenAPI** — Whenever a route’s access control changes (or a new protected prefix is added),
   document it on the operation attributes in the same change set:

    - Which check applies (wired in `config/router.php`):
      - **`GLOBAL_ACL`** + **`X-Api-Key` (`API_KEY`)** — protected synthesis routes (`/api/voices`, `/api/voice/…`,
        `/api/speak`, `/api/providers`) via `AccessControlMiddleware` + `RequiredHeaderMiddleware` +
        `AuthorizationMiddleware`
      - **`GLOBAL_ACL` only** — `/api/speak/download/{identifier}`, `/api/translate`
      - **`LOCAL_ACL`** — `/api/doc*` (HTML 404 page when denied)
      - **`WEB_ACL`** — `/` home / player UI
    - Mention ACL / API-key requirements in the operation `summary` or `description` when non-obvious.
    - Declare denial responses accurately:
      - **401** — missing/invalid `X-Api-Key` (`RequiredHeaderMiddleware`)
      - **403** — IP ACL denial (`AccessControlMiddleware` → `JsonResponseView::newForbidden()`)
    - Declare the `X-Api-Key` header (and `Authorization: Bearer …` when documenting the web-session override from
      `AuthorizationMiddleware`) with a `schema` (type string).
    - Never leave new protected prefixes undocumented in the schema or in `config/router.php` middleware chains.

2. **Changelog** — Update `CHANGELOG.md` whenever a feature is added or modified, or a bug is fixed. Use Keep a
   Changelog sections (`Added` / `Changed` / `Fixed` / `Deprecated` / `Removed` / `Security`) under a dated heading
   `## YYYY-MM-DD` (implementation date). Do **not** use Semantic Versioning or `[Unreleased]`; append to today’s date
   section, or create it if missing.

3. **`documentation.md`** — Human-oriented product / architecture doc (complement to install/env notes,
   `openapi.yaml` / `/api/doc` for the machine contract, and `CHANGELOG.md` for history). Update it in the **same
   change set** whenever any of the following change in a way that operators or integrators need to know:

    - High-level purpose, TTS providers, or shared building blocks of the API
    - Auth / ACL model (`API_KEY` / `X-Api-Key`, `GLOBAL_ACL` / `LOCAL_ACL` / `WEB_ACL`, bearer cookie override) or
      which prefixes they apply to
    - Public route prefixes or endpoint tables (method, path, auth, short description) — keep tables aligned with
      `config/router.php` / controllers; point to `openapi.yaml` / `/api/doc` for request/response schemas instead of
      duplicating them
    - Cross-cutting runtime behavior (request UUID logging, response envelope `{ success, message?, … }`, Messenger
      async speak, audio TTL / prune, CORS)
    - Notable env vars that affect ops or integration, console commands (`speak`, `messenger:*`), or ops flows

   Keep the tone concise and factual; prefer tables for routes and env excerpts. Do **not** paste secrets or real
   credentials — use placeholders. If `documentation.md` is empty or missing sections that the change touches, add or
   fill those sections rather than leaving the file stale. Do not invent separate new markdown files to satisfy this
   rule unless the user asks.

Do not leave route or behavior changes without matching OpenAPI attributes, regenerated `openapi.yaml`,
`documentation.md` updates when the human overview is affected, and changelog entries.

## Coding principles

- Follow existing code style, naming, and architecture in the touched area; prefer extending nearby patterns over
  introducing new abstractions.
- Only modify code required by the task. No drive-by refactors, unrelated cleanup, or speculative files.
- Do not add comments or docblocks that merely narrate what the code does; comments only for non-obvious intent or
  constraints.
- Do not create docs (README, markdown notes, etc.) unless explicitly asked — exception: `CHANGELOG.md` / OpenAPI
  attributes / `openapi.yaml` / `documentation.md` updates required above.
- Prefer reuse of existing services, providers, views, traits, and helpers (`SynthesisProviderStack`,
  `AccessService`, `LoggerService`, `LocaleService`, `MimeService`, `VoicePlayerService`, `AudioConverter`,
  `StringEncoderService`, `WorkerService`, `CommandTrait`, `ErrorLoggerTrait`, `OpenApiHelperTrait`,
  `SynthesisProviderTrait`, `JsonResponseView`, …).
- Match logging patterns already used (`LoggerService`, request UUID prefix from `src/init.php`,
  `ErrorLoggerTrait` / `CommandTrait::log` / `logAndPrint` / `logError`).
- Keep response shapes consistent: `{ success, message?, … }` via `SuccessResponse` / `ErrorResponse` /
  `OpenApiResponseView` / `JsonResponseView`.
- Keep diffs focused and reviewable; avoid large formatting-only churn.

## PHP / stack conventions

- PHP `>=8.2`, PSR-4 root namespace from `src/` (empty prefix in `composer.json`), constructor property promotion
  where already used.
- **Routing** — declare routes in `config/router.php` (`NGSOFT\Routing\Router` / `RouteGroup`), not Symfony
  `#[Route]`. Controllers are invokable / method callables or `ActionInterface` (`execute()` → `ResponseView`).
- Controllers stay thin: validate input, call providers/services, return `JsonResponse` / `ResponseView` with
  consistent shapes (`SuccessResponse::make()`, `VoiceListResponse`, `JsonResponseView::newBadRequest()`, …).
- Respect existing auth layers — do not weaken them:
    - **`X-Api-Key` (`API_KEY`)** — required on protected synthesis routes when `API_KEY` is non-empty
      (`RequiredHeaderMiddleware`).
    - **`Authorization: Bearer`** — same-origin web override: `AuthorizationMiddleware` decrypts the token and
      sets `X-Api-Key` (cookie `{APP_ID}_session_authorization` on HTML 200 responses).
    - **IP ACL** — `AccessService::doCheckAcl` with env lists: `GLOBAL_ACL`, `LOCAL_ACL`, `WEB_ACL` (comma-separated;
      `*` allow-all, `^prefix` prefix match, `!…` deny). Enforced by `AccessControlMiddleware` (JSON 403) or
      `WebAccessControlMiddleware` (HTML error page).
- Prefer typed enums under `src/Enum/` (`AudioFormat`, `Permission`, `Role`, `AccessGroup`, …) over magic strings
  when the domain already uses them.
- New SQLite / PDO schema for Messenger: use `messenger:setup` / `PdoStore` patterns already in the repo — do not
  introduce Doctrine unless asked (this project is not Doctrine-based).
- **Console commands** — register in `config/command.php`. Use `CommandTrait` (`src/Traits/CommandTrait.php`):

    - `use CommandTrait;` on the command class (`extends Command` / `#[AsCommand]`).
    - Implement `executeCommand(CommandHelper $io, InputInterface $input)` (not a raw `execute()` override —
      `CommandTrait::execute` is `final`).
    - Prefer `$io` / `logAndPrint()` / `log()` / `logError()` over `echo` or unlogged prints.
    - `LoggerService` is injected via `#[Required] setLogger` on the trait — do not re-inject it in the constructor
      unless needed for something else.

  Example:

  ```php
  protected function executeCommand(CommandHelper $io, InputInterface $input)
  {
      try {
          // use $io for output; logAndPrint() / log() for audit + console
          return Command::SUCCESS;
      } catch (\Throwable $e) {
          return $this->logError($e);
      }
  }
  ```

## Shared building blocks (keep in mind)

| Area              | Location / notes                                                                |
|-------------------|---------------------------------------------------------------------------------|
| HTTP routes       | `config/router.php`                                                             |
| OpenAPI scan/meta | `config/openapi.php`, `Services::getOpenApi()`, `Controller\OpenApiController`  |
| Synthesis API     | `Controller\SynthesisController`                                                |
| Actions           | `Controller\Action\*` (`HomeAction`, `TranslateAction`)                         |
| Providers         | `Provider\*`, `SpeechSynthesis\*`, `EdgeTTS\*`                                  |
| Middleware        | `Middleware\*` + `NGSOFT\Routing\Middleware\CorsMiddleware`                     |
| Views / DTOs      | `src/View/*`, `src/Model/*` (`SuccessResponse`, `ErrorResponse`, …)             |
| Messenger         | `Message\SpeakMessage`, `MessageHandler\SpeakMessageHandler`, `Messenger\Pdo*`  |
| DI wiring         | `config/dependency-injection.php`, `src/Services.php`, `src/init.php`           |
| Console           | `bin/console`, `config/command.php`, `CommandTrait`                             |
| Frontend          | `app/` (Svelte), Vite config under `config/vite/`                               |

Notable commands: `speak`, `speak:remote`, `messenger:setup`, `messenger:consume`, `cache:clear`, `translator:generate`,
`translator:clear-cache`.

## API & security

- Never commit secrets (`.env`, API keys, ElevenLabs keys, encryption passphrases). Use placeholders in examples.
- Do not write exploit PoCs, attack scripts, or bypass procedures.
- For hardening/fixes, patch the maintained code and document the fix in `CHANGELOG.md`.
- Sensitive operations must keep API-key / ACL checks as in sibling endpoints.

Typical env vars: `APP_ENV`, `APP_DEBUG`, `APP_ID`, `APP_LANG`, `APP_TITLE`, `APP_DESCRIPTION`, `APP_VERSION`,
`DATABASE_URL` / `DATABASE_URL_SQLITE`, `MESSENGER_DB_CONNECTION`, `SPEAK_COMMAND_USES_MESSENGER`,
`SPEAK_REMOTE_URL`, `SPEAK_REMOTE_API_KEY`, `API_KEY`, `GLOBAL_ACL`, `LOCAL_ACL`, `WEB_ACL`, `CORS_ENABLE`,
`CORS_METHODS`, `SPEECH_SYNTHESIS_TTL`, `SPEECH_SYNTHESIS_SOCKET_TIMEOUT`, `SPEECH_SYNTHESIS_SEC_MS_GEC`,
`LANG_FILTER`, `ELEVEN_API_KEY`.

## Git

- Only create commits when the user explicitly asks.
- Do not push, force-push, amend shared history, or skip hooks unless the user explicitly requests it.
- Commit messages: concise, focus on **why**; follow recent repo style when committing (emoji prefixes like
  `:sparkles:` / `:bug:` / `:wrench:` are used historically).

## Pull requests

When asked to open a PR: status + full diff vs base, then push if needed and create the PR with a short summary and test
plan. Return the PR URL.

## Verification

- After substantive changes, run the lightest relevant checks available (e.g. `npm run php:fix`, `npm run php:check`,
  targeted manual `php bin/console …`, OpenAPI dump + Redocly lint as above).
- When calling package runners ad hoc, prefer `bunx` if available, else `npx`.
- Prefer fixing root causes over band-aids when the failure is clear in-repo.

## Communication

- Prefer French if the user writes in French; otherwise match the user’s language.
- Be direct and concise; lead with the outcome.
- Cite code with `startLine:endLine:filepath` fences when referencing existing code.
