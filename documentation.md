# Documentation — Speech Synthesis API (`voice-generator`)

API HTTP et console locale de synthèse vocale (TTS), avec UI lecteur Svelte.

| Document                    | Rôle                                         |
|-----------------------------|----------------------------------------------|
| `documentation.md`          | Vue produit / ops / intégration (ce fichier) |
| `openapi.yaml` / `/api/doc` | Contrat machine (schémas requête/réponse)    |
| `CHANGELOG.md`              | Historique daté                              |
| `AGENTS.md`                 | Consignes pour agents de code                |

Remote : `git@github.com:ngsoft/voice-generator.git`

## Objectif

- Exposer une API JSON pour lister les voix, synthétiser du texte, télécharger l’audio et traduire des clés de catalogue.
- Fournir une UI web (formulaire lecteur) et des commandes CLI `speak` (lecture locale ou file async) / `speak:remote` (synthèse via une instance distante).
- Empiler plusieurs fournisseurs TTS derrière `SynthesisProviderStack` (Edge par défaut ; ElevenLabs si `ELEVEN_API_KEY` est défini).

Ce n’est **pas** une application Symfony full-stack : conteneur `ngsoft/container`, routage `ngsoft/routing`, composants Symfony à la carte (HttpFoundation, Console, Messenger, Cache, Dotenv, Translation, Yaml, Process). Persistance via PDO maison (`lib/libs-pdo-php82.php`) — pas de Doctrine.

## Architecture (blocs)

| Zone         | Emplacement                                                                                         |
|--------------|-----------------------------------------------------------------------------------------------------|
| Routes HTTP  | `config/router.php`                                                                                 |
| DI           | `config/dependency-injection.php`, `src/Services.php`, `src/init.php`                               |
| Synthèse API | `Controller\SynthesisController`                                                                    |
| Actions      | `Controller\Action\HomeAction`, `TranslateAction`                                                   |
| Providers    | `Provider\MicrosoftEdgeVoiceProvider`, `ElevenLabsVoiceProvider`, `SynthesisProviderStack`          |
| Edge TTS     | `EdgeTTS\EdgeTtsClient` (Sec-MS-GEC v2 par défaut ; v1 vendor via `SPEECH_SYNTHESIS_SEC_MS_GEC`)   |
| Middleware   | `Middleware\*` + CORS / JSON errors NGSOFT                                                          |
| Réponses     | `SuccessResponse` / `ErrorResponse` / `JsonResponseView` → `{ success, message?, … }`               |
| Messenger    | `Message\SpeakMessage`, `MessageHandler\SpeakMessageHandler`, `Messenger\PdoStore` / `PdoTransport` |
| Front        | `app/` (Svelte + Vite + Skeleton / DaisyUI)                                                         |
| OpenAPI      | attributs `OA\…`, scan `config/openapi.php`, UI Redoc `/api/doc`                                    |

### Comportements transverses

- **Logging** : préfixe `[IP] <uuid16>` sur chaque requête / commande (`src/init.php`).
- **TTL audio** : si `SPEECH_SYNTHESIS_TTL` > 0, prune des fichiers générés plus anciens au constructeur du contrôleur de synthèse.
- **CORS** : activé via `CORS_ENABLE` + `CORS_METHODS` (middleware sur les groupes API).
- **Async** : `speak --async` ou `SPEAK_COMMAND_USES_MESSENGER` enfile un `SpeakMessage` ; le worker `messenger:consume` génère le fichier (la lecture locale reste synchrone hors `--async`).

## Auth & ACL

Listes IP (virgules) : `*` = tout autoriser ; `^prefix` = préfixe ; `!…` = refus.

| Mécanisme          | Variable / en-tête                                       | Portée                                                                                                                    |
|--------------------|----------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| ACL IP globale     | `GLOBAL_ACL`                                             | `/api/voices`, `/api/voice/…`, `/api/speak`, `/api/providers`, `/api/speak/download/…`, `/api/translate`                  |
| Clé API            | `API_KEY` → header `X-Api-Key`                           | Routes synthèse ci-dessus **sauf** download + translate (si `API_KEY` vide, le header n’est pas exigé)                    |
| ACL docs           | `LOCAL_ACL` (défaut local : `^127.0`, `::1`, `^192.168`) | `/api/doc`, `/api/doc.json`, `/api/doc.yaml`                                                                              |
| ACL web            | `WEB_ACL`                                                | `/` (UI)                                                                                                                  |
| Bearer same-origin | `Authorization: Bearer <token>`                          | Déchiffre et pose `X-Api-Key` (`AuthorizationMiddleware`) ; cookie `{APP_ID}_session_authorization` sur réponses HTML 200 |

Refus :

- **401** — `X-Api-Key` manquant / invalide (`RequiredHeaderMiddleware`)
- **403** — ACL IP (`AccessControlMiddleware` → JSON ; `WebAccessControlMiddleware` → page HTML)

## Endpoints

Schémas détaillés : `openapi.yaml` ou `/api/doc` (pas de duplication ici).

| Méthode | Chemin                                | Auth                      | Description                                                                     |
|---------|---------------------------------------|---------------------------|---------------------------------------------------------------------------------|
| `GET`   | `/api/voices`                         | ACL + API key             | Liste / filtre / pagination des voix                                            |
| `GET`   | `/api/voice/{provider}/{lang}/{name}` | ACL + API key             | Détail d’une voix                                                               |
| `GET`   | `/api/providers`                      | ACL + API key             | Fournisseurs configurés                                                         |
| `POST`  | `/api/speak`                          | ACL + API key             | Synthèse ; `Accept: application/json` → métadonnées + URL ; sinon fichier audio |
| `GET`   | `/api/speak/download/{identifier}`    | ACL seule                 | Téléchargement audio généré                                                     |
| `POST`  | `/api/translate`                      | ACL seule                 | Traduction clé / texte catalogue                                                |
| `GET`   | `/api/doc`                            | `LOCAL_ACL`               | UI Redoc                                                                        |
| `GET`   | `/api/doc.json` / `/api/doc.yaml`     | `LOCAL_ACL`               | Export contrat                                                                  |
| `GET`   | `/`                                   | `WEB_ACL` + bearer cookie | UI lecteur                                                                      |

En-têtes utiles sur synthèse audio : `X-Media-Duration`, `X-Media-Time`.

## Fournisseurs TTS

| Provider     | Activation                   | Notes                                                                |
|--------------|------------------------------|----------------------------------------------------------------------|
| `edge`       | Toujours                     | Microsoft Edge TTS via `EdgeTtsClient` (Sec-MS-GEC v2/`1`) ; retries 429 |
| `elevenlabs` | Si `ELEVEN_API_KEY` non vide | Voix ElevenLabs                                                      |

Filtres optionnels : `LANG_FILTER` (sous-chaînes de locale, séparées par des virgules).

## Console

```bash
php bin/console speak "Hello" --lang=en-US --voice=en-US-AvaMultilingualNeural
php bin/console speak "Hello" --async          # file Messenger (génération)
php bin/console speak:remote "Hello" --lang=en-US --voice=en-US-AvaMultilingualNeural
php bin/console messenger:setup                # table PDO Messenger
php bin/console messenger:consume              # worker (PidLock, un process)
php bin/console cache:clear
php bin/console translator:generate
php bin/console translator:cache:clear
```

`speak:remote` appelle `POST {SPEAK_REMOTE_URL}/api/speak` avec `X-Api-Key: SPEAK_REMOTE_API_KEY`, enregistre l’audio dans `var/data/remote/`, puis le joue via `VoicePlayerService` (Windows / macOS ; sous WSL rejoue via `php.exe`).

Frontend / serveur local : `npm run serve` (port **8600**), `npm run watch` / `npm run build`.

## Variables d’environnement (ops)

Ne jamais committer de secrets. Placeholders uniquement.

| Variable                                                           | Rôle                                    |
|--------------------------------------------------------------------|-----------------------------------------|
| `APP_ENV` / `APP_DEBUG` / `APP_ID`                                 | Environnement, debug, id cookie / cache |
| `APP_LANG` / `APP_LANG_FORCED`                                     | Locale catalogue                        |
| `APP_TITLE` / `APP_DESCRIPTION` / `APP_VERSION`                    | Métadonnées app + OpenAPI info          |
| `DATABASE_URL` / `DATABASE_URL_SQLITE`                             | PDO applicatif                          |
| `MESSENGER_DB_CONNECTION`                                          | Connexion file Messenger                |
| `MESSENGER_REDELIVER_TIMEOUT`                                      | Timeout redelivery Messenger            |
| `SPEAK_COMMAND_USES_MESSENGER`                                     | Forcer Messenger même sans `--async`    |
| `SPEAK_MESSENGER_FAIL_RETRY_SECONDS`                               | Délai retry échec enqueue               |
| `SPEAK_REMOTE_URL`                                                 | Base URL HTTPS de l’instance distante (`speak:remote`) |
| `SPEAK_REMOTE_API_KEY`                                             | Clé `X-Api-Key` pour l’instance distante |
| `API_KEY`                                                          | Valeur attendue de `X-Api-Key`          |
| `GLOBAL_ACL` / `LOCAL_ACL` / `WEB_ACL`                             | Listes IP                               |
| `CORS_ENABLE` / `CORS_METHODS`                                     | CORS API                                |
| `SPEECH_SYNTHESIS_TTL`                                             | TTL (s) prune fichiers audio            |
| `SPEECH_SYNTHESIS_SOCKET_TIMEOUT`                                  | Timeout socket synthèse                 |
| `SPEECH_SYNTHESIS_429_RETRIES` / `SPEECH_SYNTHESIS_429_BASE_DELAY` | Backoff 429 Edge                        |
| `SPEECH_SYNTHESIS_SEC_MS_GEC`                                      | `2` (défaut, SecMsGecV2) ou `1` (vendor) |
| `LANG_FILTER`                                                      | Filtre locales voix                     |
| `ELEVEN_API_KEY`                                                   | Active le provider ElevenLabs           |
| `APP_VITE_ENDPOINTS` / `VITE_*`                                    | Build front / thèmes                    |

## OpenAPI — maintenance

Source de vérité : attributs `OpenApi\Attributes` sur contrôleurs / DTOs ; chemins scannés dans `config/openapi.php`.

```bash
php -r "require 'src/init.php'; file_put_contents('openapi.yaml', Services::getOpenApi()->toYaml());"
bunx @redocly/cli lint openapi.yaml   # ou npx
```

## Flux async (résumé)

```
speak --async  →  MessageBus  →  PdoTransport (INSERT)
messenger:consume  →  SpeakMessageHandler  →  SynthesisProviderStack  →  fichier audio
```

Sans `--async`, la synthèse (et la lecture locale) restent dans le process appelant.
