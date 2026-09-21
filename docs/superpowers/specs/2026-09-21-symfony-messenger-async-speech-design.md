# Design — Intégration de `symfony/messenger` (synthèse vocale asynchrone)

- **Date** : 2026-09-21
- **Auteur** : Aymeric Anger (avec Claude Code)
- **Statut** : proposé (en attente de relecture)

## 1. Contexte

Le projet `voice` est un micro-framework maison bâti sur des composants Symfony
« à la carte » (console, http-foundation, cache, translation…), câblés
manuellement dans `config/dependency-injection.php` via le conteneur PSR-11
`NGSOFT\Container\Container`. Il n'y a **ni Symfony Flex, ni bundle, ni
Doctrine** : la persistance passe par une librairie PDO maison
(`lib/libs-pdo-php82.php`) exposant `\Sql\QueryHelper` (`getConnection()`,
support MySQL **et** SQLite), et les migrations sont de simples fichiers `.sql`
dans `migrations/`.

`symfony/messenger` (`^7.4`) est **déjà déclaré** dans `composer.json` mais
n'est pas branché : pas de bus, pas de transport, pas de handler. Le dossier
`src/Worker/` ne contient qu'un `PidLock.php` (mutex fichier basé sur
l'extension `Mutex`).

## 2. Objectif

Permettre de **déporter la synthèse vocale hors du cycle requête/commande**,
en la traitant en tâche de fond via un bus Messenger, avec :

- une **file d'attente persistée en base** au travers d'un **transport PDO
  maison** (réutilisant `\Sql\QueryHelper`, aucune nouvelle dépendance) ;
- un **worker** consommant les messages, borné à un seul process concurrent
  grâce au `PidLock` existant ;
- **aucune régression** pour l'appel synchrone existant.

### 2.1 Non-objectifs (YAGNI)

- Pas de retry/delay avancé, pas de dead-letter queue, pas de `failed`
  transport dédié dans cette première itération (rejet = suppression + log).
- Pas de routage multi-transports : un seul transport `async`.
- Pas d'ajout de `symfony/serializer`, `symfony/event-dispatcher` ni
  `doctrine/dbal`.

### 2.2 Point d'attention — lecture audio locale

La commande `speak` actuelle **joue** le son localement (`cmdmp3.exe` sous
Windows, `afplay` sous macOS). En mode asynchrone le handler s'exécute dans le
process *worker*, pas dans le terminal appelant : il peut **générer et mettre
en cache le fichier audio**, mais **la lecture locale reste synchrone**.

Conséquence de conception : le mode async cible la **génération de fichiers
audio** (usage API / batch), et **l'appel direct `speak` sans `--async`
demeure strictement inchangé** (notamment pour la règle de vocalisation
globale de l'utilisateur).

## 3. Architecture

### 3.1 Flux

```
speak --async ──► MessageBus ──► SendMessageMiddleware ──► PdoTransport::send()  (INSERT)
                                                                     │
messenger:consume ──► Worker ──► PdoTransport::get() ──► HandleMessageMiddleware ──► SpeakMessageHandler
                                       │  ack() → DELETE
                                       │  reject() → DELETE + log
```

Sans `--async`, `SendersLocator` ne route `SpeakMessage` vers aucun transport :
`HandleMessageMiddleware` l'exécute dans le process courant (comportement
synchrone).

### 3.2 Composants et arborescence

| Fichier | Rôle |
|---|---|
| `src/Message/SpeakMessage.php` | DTO immuable : `text`, `voice`, `lang`, `format`. |
| `src/MessageHandler/SpeakMessageHandler.php` | Traite `SpeakMessage` ; réutilise `SynthesisProviderStack`. |
| `src/Messenger/PdoTransport.php` | `TransportInterface` + `MessageCountAwareInterface` + `ListableReceiverInterface`. Stockage `\Sql\QueryHelper`. |
| `src/Messenger/PdoTransportFactory.php` | `TransportFactoryInterface` : reconnaît le DSN `pdo://`, fabrique le transport. |
| `src/Messenger/PdoStore.php` | Encapsule les requêtes SQL (send/get/ack/reject/count/setup). |
| `src/Command/MessengerConsumeCommand.php` | Commande maison : instancie `Symfony\Component\Messenger\Worker`, appelle `run()`, protégée par `PidLock`. |
| `src/Command/MessengerSetupCommand.php` | Crée la table si absente (pattern `.sql`). |
| `migrations/messenger.mysql.sql` | DDL MySQL de `messenger_messages`. |
| `migrations/messenger.sqlite.sql` | DDL SQLite de `messenger_messages`. |

### 3.3 Sérialisation

`Symfony\Component\Messenger\Transport\Serialization\PhpSerializer` (natif,
aucune dépendance). Les enveloppes sont sérialisées via `serialize()` PHP ;
`SpeakMessage` étant un simple DTO scalaire, c'est suffisant et robuste.

### 3.4 Schéma de la table `messenger_messages`

Colonnes (compatibles MySQL et SQLite) :

| Colonne | Type MySQL | Type SQLite | Notes |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PK` | `INTEGER PRIMARY KEY AUTOINCREMENT` | |
| `body` | `LONGTEXT` | `TEXT` | enveloppe sérialisée |
| `headers` | `LONGTEXT` | `TEXT` | en-têtes JSON |
| `queue_name` | `VARCHAR(190)` | `TEXT` | index |
| `created_at` | `DATETIME` | `TEXT` | |
| `available_at` | `DATETIME` | `TEXT` | index ; support d'un délai éventuel |
| `delivered_at` | `DATETIME NULL` | `TEXT NULL` | marqueur « en cours de traitement » |

Index sur `(queue_name, available_at, delivered_at)`.

### 3.5 Concurrence

Un **seul worker concurrent** est garanti par `Worker\PidLock::lock('messenger')`
au démarrage de `messenger:consume`. Cela permet d'éviter le
`SELECT … FOR UPDATE` (non supporté par SQLite) : `get()` sélectionne la plus
ancienne ligne `delivered_at IS NULL AND available_at <= now`, la marque
`delivered_at = now`, et la renvoie. Les messages restés `delivered_at` non
nul au-delà d'un seuil (worker tué) pourront être « réenfilés » par une remise
à zéro au démarrage du worker (redelivery simple).

### 3.6 Câblage du conteneur (`config/dependency-injection.php`)

Enregistrer :

- `PdoStore` (via `\Sql\QueryHelper::getConnection()`),
- `PdoTransport` (nommé `async`),
- `HandlersLocator` : `SpeakMessage::class => [SpeakMessageHandler]`,
- `SendersLocator` : `SpeakMessage::class => ['async']` **uniquement quand
  `--async`** — voir §3.7,
- `MessageBus` avec la pile `[SendMessageMiddleware(SendersLocator),
  HandleMessageMiddleware(HandlersLocator)]`,
- alias `MessageBusInterface` → `MessageBus`.

Ajouter les commandes dans `config/command.php` :
`MessengerSetupCommand`, `MessengerConsumeCommand`.

### 3.7 Basculement sync / async

Le flag `--async` de `speak` ne peut pas changer le `SendersLocator` déjà
construit. Deux options d'implémentation, tranchées à l'écriture du plan :

1. **Stamp de routage** (recommandé) : en async, dispatcher
   `new Envelope($msg, [new TransportNamesStamp(['async'])])` ; en sync,
   dispatcher le message nu → aucun sender → exécution locale. Le
   `SendersLocator` mappe alors sur les stamps.
2. **Deux bus** distincts (`bus.sync`, `bus.async`) sélectionnés par le flag.

L'option 1 est retenue par défaut (un seul bus, plus simple).

## 4. Gestion des erreurs

- Échec de synthèse dans le handler → l'exception remonte au `Worker` ; le
  message est **rejeté** (supprimé) et l'erreur **journalisée** via
  `LoggerService`. (Pas de retry automatique en v1 ; documenté comme évolution
  possible.)
- Table absente → `messenger:consume` échoue avec un message clair invitant à
  lancer `messenger:setup`.
- `PidLock` déjà pris → `messenger:consume` sort proprement (un worker tourne
  déjà) avec un code de sortie 0 et un message.

## 5. Vérification

Le dépôt **n'a aujourd'hui aucun framework de test** (pas de PHPUnit, pas de
dossier `tests/`) — seul `phan` (analyse statique) est présent en dev. La
stratégie de vérification en tient compte :

**Option A (retenue par défaut) — script de vérification maison.**
Un script CLI jetable dans le répertoire scratchpad exerce, sur une **base
SQLite temporaire** :

- cycle `send → get → ack` et `send → get → reject` du transport ;
- comptage (`MessageCountAwareInterface`) et remise à zéro des messages
  « delivered » orphelins ;
- `SpeakMessageHandler` avec un `SynthesisProviderStack` bouchonné (double de
  test manuel), sans lecture audio ;
- un aller-retour bout-en-bout : dispatch avec stamp async → présence en base
  → un tour de `Worker` → handler appelé → message acquitté.

Chaque assertion échoue en s'arrêtant avec un code de sortie non nul et un
message explicite. `phan` doit rester vert sur le code ajouté.

**Option B (si l'utilisateur veut des tests pérennes)** — introduire
`phpunit/phpunit` en `require-dev` et un dossier `tests/`. C'est **la seule
nouvelle dépendance** que le projet ait à décider ; à trancher explicitement,
car elle n'existe pas encore dans l'outillage. À défaut, on reste sur
l'option A.

**Vérification manuelle finale** (obligatoire dans les deux cas) :
`messenger:setup`, puis `speak "..." --async` (INSERT visible en base), puis
`messenger:consume` (fichier audio généré, message acquitté), puis
`speak "..."` sans flag pour confirmer l'absence de régression synchrone.

## 6. Étapes d'implémentation (aperçu, détaillé dans le plan)

1. DDL `.sql` + `MessengerSetupCommand`.
2. `PdoStore` + tests.
3. `PdoTransport` + `PdoTransportFactory` + tests.
4. `SpeakMessage` + `SpeakMessageHandler` (extraction de la logique de
   synthèse depuis `SpeakCommand`) + tests.
5. Câblage conteneur (bus, locators) + alias.
6. `MessengerConsumeCommand` (Worker + PidLock) + enregistrement des commandes.
7. Option `--async` sur `SpeakCommand` (stamp de routage).
8. Test d'intégration bout-en-bout + mise à jour de la doc/OpenAPI si un
   endpoint d'enfilement est exposé.

## 7. Dépendances

- **Aucune nouvelle dépendance d'exécution.** `symfony/messenger ^7.4` est
  déjà requis ; `PhpSerializer` et `Worker` sont natifs ; le `Worker` accepte
  un `EventDispatcherInterface` **null**, ce qui évite
  `symfony/event-dispatcher`.
- **Seule dépendance de dev éventuelle** : `phpunit/phpunit` (require-dev),
  uniquement si l'option B de §5 est choisie. Par défaut, aucune.
