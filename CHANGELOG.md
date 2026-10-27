# Changelog

All notable changes to this project are documented in this file.

Format: Keep a Changelog sections under dated headings `## YYYY-MM-DD` (implementation date).
No Semantic Versioning / `[Unreleased]`.

## 2026-10-27

### Changed

- Regenerated `openapi.yaml` with URL-safe `operationId`s, operation `summary`s, `ApiKeyAuth` security scheme, and documented `GLOBAL_ACL` / `X-Api-Key` denial responses (401 / 403).
- OpenAPI server URL is now relative (`/` or app base path) instead of `http://localhost`.
- Added agent / product docs (`AGENTS.md`, `documentation.md`).

## 2026-10-26

### Changed

- Switched Edge TTS client to Sec-MS-GEC **v2** token format (Microsoft requirement).

## 2026-10-23

### Fixed

- Edge TTS HTTP 429 handling with exponential backoff retries.

## 2026-09-22

### Added

- Symfony Messenger async speech synthesis end-to-end: PDO transport/store, `SpeakMessage` + handler, `messenger:setup` / `messenger:consume`, container wiring.
- `VoicePlayerService` for cross-OS local playback.
- `StringEncoder` helpers for encrypted web-session API-key override.

### Changed

- `speak` command / controller paths support Messenger-backed async processing.

## 2026-09-21

### Added

- Messenger design/spec docs; table setup; `SpeakMessage` DTO; PDO store/transport; handler; bus wiring; `messenger:consume` (PidLock); `speak --async`.

### Fixed

- Surface enqueue failures and missing messenger table errors.

## 2026-09-05

### Added

- Configurable speech synthesis socket timeout (`SPEECH_SYNTHESIS_SOCKET_TIMEOUT`).

### Fixed

- Usable socket timeout helper for Edge / network calls.
- Capture and display command output on success.

## 2026-09-04

### Fixed

- `withSilencedMissingWmic` helper for Windows environments without WMIC.

## 2026-08-25

### Added

- OpenAPI 3 attributes across synthesis and translation endpoints; committed `openapi.yaml`.

### Changed

- Migrated Tailwind configuration to CSS-based setup.

## 2026-08-24

### Added

- Initial HTTP client configuration and environment handling under `client/`.

## 2026-08-23

### Fixed

- WSL ffmpeg path detection.
- WSL replay / speak command behavior.

## 2026-05-26

### Added

- `speak` local playback support for Darwin (macOS).

## 2026-05-24

### Added

- `speak` console command playback on Windows.

### Fixed

- Dark mode toggle initial state.

## 2026-04-12

### Added

- DaisyUI themes and client configuration storage.

### Fixed

- OpenAPI config loading.

## 2026-04-11

### Added

- DaisyUI themes for the web UI.

## 2026-04-09

### Changed

- Web access control middleware (`WEB_ACL`).
- Audio file TTL / prune behavior.

## 2026-03-30

### Added

- Preline + typeahead selects in the UI.
- API-key / ACL hardening for API and `/api/doc`.

### Fixed

- OpenAPI config loader.

### Security

- Closed lock / authorization cookie path for same-origin bearer override of `X-Api-Key`.

## 2026-03-28

### Added

- Custom template engine for server-rendered pages.

## 2026-03-22

### Added

- ElevenLabs voice provider (`ELEVEN_API_KEY`).
- Generated audio TTL prune (`SPEECH_SYNTHESIS_TTL`).
- Translation route `POST /api/translate`.

### Fixed

- Checks for missing `ffmpeg` / `ffprobe` paths.

## 2026-03-21

### Added

- TTS HTTP routes (voices, speak, download, providers).
- Pitch / rate / volume options on utterances.

## 2026-03-20

### Changed

- Project cleanup / restart of the current architecture (remove dead Piper-era paths).

## 2025-05-22

### Added

- Voice duration response headers (`X-Media-Duration`, `X-Media-Time`).
- Env-based voice language filter (`LANG_FILTER`).
- `.htaccess` fix for basic auth + CORS.

## 2025-05-12

### Fixed

- Language codes normalized to ISO / BCP 47 style.
- CORS errors when basic auth is enabled.

## 2025-05-10

### Fixed

- Typo `voiceURI` / voice URI handling in the UI.

## 2025-05-09

### Added

- Tom Select for voice selection UI.

## 2025-05-08

### Added

- ffmpeg convert-to-PCM path.
- Download button in the player UI.

## 2025-05-07

### Added

- Env flag to prevent removing cached audio files.

## 2025-05-04

### Added

- CORS middleware / headers support.

## 2025-05-02

### Added

- Microsoft Edge TTS voice provider.

## 2025-04-01

### Added

- Missing UI event listener; favicon.

## 2025-03-31

### Added

- List / read voice routes.
- Vite frontend tooling.
- Voice selection UI.

## 2025-03-30

### Added

- Initial repository (early Piper Linux/Windows voice experiments; later superseded by Edge / ElevenLabs stack).
