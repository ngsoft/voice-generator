# Symfony Messenger — Async Speech Synthesis Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire `symfony/messenger` into this custom micro-framework so speech-synthesis jobs can be queued to a PDO-backed transport and processed by a background worker, without changing the existing synchronous `speak` behaviour.

**Architecture:** A single `MessageBus` with `SendMessageMiddleware` + `HandleMessageMiddleware`. Messages carrying a `TransportNamesStamp(['async'])` are persisted by a hand-written `PdoTransport` (built on the project's `\Sql` / `\Sql\QueryHelper` helpers); messages without that stamp are handled inline (sync). A dedicated `messenger:consume` command runs `Symfony\Component\Messenger\Worker`, guarded by the existing `Worker\PidLock` so exactly one worker runs at a time — which is what makes the transport's claim logic safe without row locking.

**Tech Stack:** PHP 8.2, `symfony/messenger ^7.4` (already required), `symfony/event-dispatcher ^7` (already installed as a transitive dep — this plan declares it explicitly), the project's `\Sql`/`\SqlConnector`/`\Sql\QueryHelper` PDO library, `ngsoft/container`, `symfony/console`.

**Spec:** `docs/superpowers/specs/2026-09-21-symfony-messenger-async-speech-design.md`

## Global Constraints

- **PHP binary:** always run PHP with `C:\WebSdk\lib\php\8.2\php.exe` (never bare `php`). Console entry point: `bin/console`.
- **Shell:** PowerShell for all shell commands on this Windows host.
- **Runtime dependencies:** add **no** new runtime Composer package. `PhpSerializer` and `Worker` are native to messenger; `Worker`'s event dispatcher param is nullable. The **only** `composer.json` change allowed is declaring `symfony/event-dispatcher: ^7` in `require` (it is already installed, so `composer update` causes no download).
- **DB connection name:** this project does **not** configure a `default` connection. Configured connection names come from `.env` (`DATABASE_URL_0` → `0`, `DATABASE_URL_SQLITE` → `sqlite`). The transport reads its connection name from env `MESSENGER_DB_CONNECTION` (default `0`). Every `\Sql` call passes this name as the `$connectionName` argument.
- **Single-worker invariant:** `PdoTransport::get()` claims a row with a non-atomic SELECT-then-UPDATE. This is correct **only** because `messenger:consume` holds `Worker\PidLock::lock('messenger')` for the whole run. Do not remove that guard.
- **Sync stays default:** dispatching a message with no `TransportNamesStamp` MUST execute it inline. Only `--async` adds the stamp.
- **Table name:** `messenger_messages`, columns `id, body, headers, queue_name, created_at, available_at, delivered_at`. Queue name: `async`.
- **PSR-4:** `composer.json` maps `""` → `src/`, so a class `Message\SpeakMessage` lives at `src/Message/SpeakMessage.php`. Follow the existing namespace-as-folder convention.
- **Datetime format:** store timestamps as `date('Y-m-d H:i:s')` strings (sortable, works for both MySQL `DATETIME` and SQLite `TEXT`).
- **Commit style:** the repo uses gitmoji-prefixed messages. End every commit body with the attribution line `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

---

## File Structure

- **Create** `migrations/messenger.mysql.sql` — MySQL DDL for `messenger_messages`.
- **Create** `migrations/messenger.sqlite.sql` — SQLite DDL + index.
- **Create** `src/Message/SpeakMessage.php` — immutable DTO.
- **Create** `src/MessageHandler/SpeakMessageHandler.php` — invokable handler, reuses `SynthesisProviderStack`.
- **Create** `src/Messenger/PdoStore.php` — all SQL (send/get/ack/count/all/find/redeliver/setup) via `\Sql` helpers.
- **Create** `src/Messenger/PdoTransport.php` — Messenger transport wrapping `PdoStore` + serializer + stamps.
- **Create** `src/Command/MessengerSetupCommand.php` — creates the table via the existing `migrate_database()`.
- **Create** `src/Command/MessengerConsumeCommand.php` — runs `Worker` under `PidLock`.
- **Modify** `config/dependency-injection.php` — register serializer, store, transport, bus + locators, and the `async` sender container.
- **Modify** `config/command.php` — register the two new commands.
- **Modify** `src/Command/SpeakCommand.php` — add `--async` option + `MessageBusInterface` injection.
- **Modify** `composer.json` — declare `symfony/event-dispatcher: ^7` in `require`.
- **Modify** `.env` — document `MESSENGER_DB_CONNECTION` / `MESSENGER_REDELIVER_TIMEOUT`.

Reference facts (verified in the codebase):
- Commands extend `Symfony\Component\Console\Command\Command`, `use Traits\CommandTrait`, carry `#[AsCommand(name, description)]`, and implement `executeCommand(NGSOFT\Console\Profile\CommandHelper $io, InputInterface $input)`. `CommandTrait` injects `LoggerService $this->logger` via `#[Required] setLogger()`. Constructor args are autowired by the container (see `SpeakCommand`).
- `\Sql::easyQuery(string $sql, array $bindings = [], string $conn = 'default')` prepares+executes; for SELECT it returns an object with `->fetchOne($mode)` / `->fetchAll($mode)`, else a truthy result (null on failure — `SqlConnector::setQueryNullable(true)` is set at boot). Use `\Sql\FETCH_ASSOC` as the mode.
- `\Sql::getLastInsertId(string $conn = 'default')` returns the last insert id.
- `migrate_database(\Sql\QueryHelper|\Sql\Driver $driver, ?string $filter)` runs every `*.<type>.sql` migration whose basename contains `$filter`, using `$driver->type()` ('mysql'|'sqlite'). `\SqlConnector::getConnection($name)` returns the `QueryHelper`.
- `SynthesisProviderStack` (final) `->speak(SpeechSynthesisUtterance $u): SpeechSynthesisResult`; `SpeechSynthesisResult` has a public `string $path`. `SpeechSynthesisUtterance::make(['voice'=>,'text'=>,'lang'=>,'format'=>])`.
- Messenger signatures: `ReceiverInterface::get(): iterable`, `ack(Envelope): void`, `reject(Envelope): void`; `SenderInterface::send(Envelope): Envelope`; `MessageCountAwareInterface::getMessageCount(): int`; `ListableReceiverInterface::all(?int $limit = null): iterable`, `find(mixed $id): ?Envelope`. `PhpSerializer::encode(Envelope): array` → `['body' => string]`; `decode(array): Envelope` needs `['body' => string]`. `new TransportMessageIdStamp(mixed $id)` / `->getId()`. `new TransportNamesStamp(array|string)`. `new HandlersLocator(array $handlers)`; `new SendersLocator(array $sendersMap, Psr\Container\ContainerInterface $locator)`; `new MessageBus(iterable $middleware)`; `new SendMessageMiddleware(SendersLocatorInterface $l)`; `new HandleMessageMiddleware(HandlersLocatorInterface $l)`. `new Worker(array $receivers, MessageBusInterface $bus, ?Psr\EventDispatcher\EventDispatcherInterface $ed = null, ?Psr\Log\LoggerInterface $log = null)`; `->run(['sleep' => microseconds, 'time_limit' => seconds])`; `->stop()`. `new StopWorkerOnMessageLimitListener(int $max, ?LoggerInterface)` and `new StopWorkerOnTimeLimitListener(int $seconds, ?LoggerInterface)` are `EventSubscriberInterface`s registered via `Symfony\Component\EventDispatcher\EventDispatcher::addSubscriber()`.

---

### Task 1: Migrations + `messenger:setup` command + env docs

**Files:**
- Create: `migrations/messenger.mysql.sql`
- Create: `migrations/messenger.sqlite.sql`
- Create: `src/Messenger/PdoStore.php` (only the `setup()` + connection helpers in this task; the rest of the store is Task 3)
- Create: `src/Command/MessengerSetupCommand.php`
- Modify: `config/command.php`
- Modify: `.env`

**Interfaces:**
- Produces: `Messenger\PdoStore` with `__construct(string $connectionName = '0', string $tableName = 'messenger_messages', int $redeliverTimeout = 3600)`, `connectionName(): string`, `tableName(): string`, `driverType(): string`, `setup(): bool`.
- Produces: console command `messenger:setup`.

- [ ] **Step 1: Write the failing check**

Create `scratchpad/verify_task1.php` (run via the 8.2 binary). It boots the app, points a throwaway SQLite connection at a temp file, and asserts the table gets created:

```php
<?php
require __DIR__ . '/../src/init.php'; // adjust relative path to project: use the real scratchpad path
$tmp = sys_get_temp_dir() . '/messenger_t1_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
$store = new Messenger\PdoStore('mtest');
assert($store->driverType() === 'sqlite', 'driverType should be sqlite');
assert($store->setup() === true, 'setup() should return true');
// table exists → a COUNT query must not throw
$n = Sql::easyCount('messenger_messages', '', [], 'mtest');
assert($n === 0, 'fresh table should be empty');
echo "TASK1 OK\n";
@unlink($tmp);
```

- [ ] **Step 2: Run it to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task1.php`
Expected: fatal error — class `Messenger\PdoStore` not found.

- [ ] **Step 3: Write the MySQL migration**

`migrations/messenger.mysql.sql`:

```sql
CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_messenger_queue (queue_name, available_at, delivered_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

- [ ] **Step 4: Write the SQLite migration**

`migrations/messenger.sqlite.sql`:

```sql
CREATE TABLE IF NOT EXISTS messenger_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name TEXT NOT NULL,
    created_at TEXT NOT NULL,
    available_at TEXT NOT NULL,
    delivered_at TEXT DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_messenger_queue ON messenger_messages (queue_name, available_at, delivered_at);
```

- [ ] **Step 5: Create `PdoStore` with connection helpers + `setup()`**

`src/Messenger/PdoStore.php`:

```php
<?php

declare(strict_types=1);

namespace Messenger;

/**
 * Persists Messenger envelopes in a SQL table using the project's \Sql helpers.
 */
final class PdoStore
{
    public function __construct(
        private readonly string $connectionName = '0',
        private readonly string $tableName = 'messenger_messages',
        private readonly int $redeliverTimeout = 3600,
    ) {}

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    public function driverType(): string
    {
        return \SqlConnector::getConnection($this->connectionName)->type();
    }

    /**
     * Creates the messenger table (idempotent) by running the messenger migrations.
     */
    public function setup(): bool
    {
        return (bool) migrate_database(
            \SqlConnector::getConnection($this->connectionName),
            'messenger'
        );
    }
}
```

- [ ] **Step 6: Create the setup command**

`src/Command/MessengerSetupCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Command;

use Messenger\PdoStore;
use NGSOFT\Console\Profile\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Traits\CommandTrait;

#[AsCommand('messenger:setup', 'Create the messenger transport table')]
class MessengerSetupCommand extends Command
{
    use CommandTrait;

    public function __construct(private readonly PdoStore $store)
    {
        parent::__construct();
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        if ($this->store->setup())
        {
            $io->success(sprintf('Messenger table ready (%s connection).', $this->store->connectionName()));
            return self::SUCCESS;
        }

        $io->error('Messenger table setup failed.');
        return self::FAILURE;
    }
}
```

- [ ] **Step 7: Register the command**

In `config/command.php`, add the `use` and the registration line alongside the others:

```php
use Command\MessengerSetupCommand;
// ...
$app->add(MessengerSetupCommand::class);
```

- [ ] **Step 8: Document env vars**

In `.env`, under the DB section, add:

```dotenv
# Messenger transport (async speech synthesis)
MESSENGER_DB_CONNECTION=0
MESSENGER_REDELIVER_TIMEOUT=3600
```

- [ ] **Step 9: Run the check to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task1.php`
Expected: prints `TASK1 OK`, exit code 0.

- [ ] **Step 10: Commit**

```powershell
git add migrations/messenger.mysql.sql migrations/messenger.sqlite.sql src/Messenger/PdoStore.php src/Command/MessengerSetupCommand.php config/command.php .env
git commit -m ":sparkles: add messenger table migrations and messenger:setup command"
```

---

### Task 2: `SpeakMessage` DTO

**Files:**
- Create: `src/Message/SpeakMessage.php`

**Interfaces:**
- Produces: `Message\SpeakMessage` with `public readonly string $text, $voice, $lang, $format` and `__construct(string $text, string $voice = 'en-US-AvaMultilingualNeural', string $lang = 'en-US', string $format = 'mp3')`.

- [ ] **Step 1: Write the failing check**

Append to a fresh `scratchpad/verify_task2.php`:

```php
<?php
require __DIR__ . '/../src/init.php';
$m = new Message\SpeakMessage('hello', 'en-US-BrianMultilingualNeural');
assert($m->text === 'hello');
assert($m->voice === 'en-US-BrianMultilingualNeural');
assert($m->lang === 'en-US');
assert($m->format === 'mp3');
echo "TASK2 OK\n";
```

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task2.php`
Expected: fatal error — class `Message\SpeakMessage` not found.

- [ ] **Step 3: Implement the DTO**

`src/Message/SpeakMessage.php`:

```php
<?php

declare(strict_types=1);

namespace Message;

final readonly class SpeakMessage
{
    public function __construct(
        public string $text,
        public string $voice = 'en-US-AvaMultilingualNeural',
        public string $lang = 'en-US',
        public string $format = 'mp3',
    ) {}
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task2.php`
Expected: prints `TASK2 OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/Message/SpeakMessage.php
git commit -m ":sparkles: add SpeakMessage DTO for async synthesis"
```

---

### Task 3: `PdoStore` queue operations

**Files:**
- Modify: `src/Messenger/PdoStore.php`

**Interfaces:**
- Consumes: `\Sql::easyQuery`, `\Sql::getLastInsertId`, `\Sql\FETCH_ASSOC`.
- Produces on `Messenger\PdoStore`:
  - `insert(string $queueName, string $body, string $headers, ?string $availableAt = null): string` (returns id)
  - `get(string $queueName): ?array` (claims one row, sets `delivered_at`; returns assoc row with key `id`, or null)
  - `deleteById(string $id): void`
  - `count(string $queueName): int`
  - `all(string $queueName, ?int $limit = null): array` (assoc rows, undelivered)
  - `find(string $id): ?array`

- [ ] **Step 1: Write the failing check**

`scratchpad/verify_task3.php`:

```php
<?php
require __DIR__ . '/../src/init.php';
$tmp = sys_get_temp_dir() . '/messenger_t3_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
$store = new Messenger\PdoStore('mtest');
$store->setup();

$id = $store->insert('async', 'BODY-1', '[]');
assert($id !== '' && $id !== '0', 'insert returns an id');
assert($store->count('async') === 1, 'count is 1 after insert');

$row = $store->get('async');
assert($row !== null && $row['body'] === 'BODY-1', 'get returns the row');
assert($store->count('async') === 0, 'claimed row is excluded from count');
assert($store->get('async') === null, 'no second undelivered row');

$found = $store->find((string) $row['id']);
assert($found !== null && $found['id'] == $row['id'], 'find returns the row by id');

$store->deleteById((string) $row['id']);
assert($store->find((string) $row['id']) === null, 'deleted row is gone');

// all() lists only undelivered
$store->insert('async', 'BODY-2', '[]');
assert(count($store->all('async')) === 1, 'all() lists undelivered rows');
echo "TASK3 OK\n";
@unlink($tmp);
```

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task3.php`
Expected: error — `insert()` (or the other methods) not defined on `PdoStore`.

- [ ] **Step 3: Add the queue methods to `PdoStore`**

Add these methods inside the `PdoStore` class (after `setup()`), plus a private `now()` helper:

```php
    public function insert(string $queueName, string $body, string $headers, ?string $availableAt = null): string
    {
        $now = $this->now();
        $ok  = \Sql::easyQuery(
            "INSERT INTO {$this->tableName} (body, headers, queue_name, created_at, available_at, delivered_at)"
            . ' VALUES (?, ?, ?, ?, ?, NULL)',
            [$body, $headers, $queueName, $now, $availableAt ?? $now],
            $this->connectionName
        );

        return $ok ? (string) \Sql::getLastInsertId($this->connectionName) : '0';
    }

    public function get(string $queueName): ?array
    {
        $this->redeliverStale($queueName);

        $now  = $this->now();
        $stmt = \Sql::easyQuery(
            "SELECT * FROM {$this->tableName}"
            . ' WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?'
            . ' ORDER BY available_at ASC, id ASC LIMIT 1',
            [$queueName, $now],
            $this->connectionName
        );

        $row = $stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null;

        if ( ! $row)
        {
            return null;
        }

        \Sql::easyQuery(
            "UPDATE {$this->tableName} SET delivered_at = ? WHERE id = ?",
            [$now, $row['id']],
            $this->connectionName
        );

        return $row;
    }

    public function deleteById(string $id): void
    {
        \Sql::easyQuery(
            "DELETE FROM {$this->tableName} WHERE id = ?",
            [$id],
            $this->connectionName
        );
    }

    public function count(string $queueName): int
    {
        $stmt = \Sql::easyQuery(
            "SELECT COUNT(*) AS c FROM {$this->tableName} WHERE queue_name = ? AND delivered_at IS NULL",
            [$queueName],
            $this->connectionName
        );
        $row  = $stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null;

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function all(string $queueName, ?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE queue_name = ? AND delivered_at IS NULL"
            . ' ORDER BY available_at ASC, id ASC';

        if (null !== $limit)
        {
            $sql .= ' LIMIT ' . max(0, $limit);
        }

        $stmt = \Sql::easyQuery($sql, [$queueName], $this->connectionName);

        return $stmt ? $stmt->fetchAll(\Sql\FETCH_ASSOC) : [];
    }

    public function find(string $id): ?array
    {
        $stmt = \Sql::easyQuery(
            "SELECT * FROM {$this->tableName} WHERE id = ? LIMIT 1",
            [$id],
            $this->connectionName
        );

        return ($stmt ? $stmt->fetchOne(\Sql\FETCH_ASSOC) : null) ?: null;
    }

    /**
     * Releases rows a dead worker left marked as delivered past the redeliver timeout.
     */
    private function redeliverStale(string $queueName): void
    {
        $threshold = date('Y-m-d H:i:s', time() - $this->redeliverTimeout);

        \Sql::easyQuery(
            "UPDATE {$this->tableName} SET delivered_at = NULL"
            . ' WHERE queue_name = ? AND delivered_at IS NOT NULL AND delivered_at < ?',
            [$queueName, $threshold],
            $this->connectionName
        );
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task3.php`
Expected: prints `TASK3 OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/Messenger/PdoStore.php
git commit -m ":sparkles: add PdoStore queue operations for messenger transport"
```

---

### Task 4: `PdoTransport`

**Files:**
- Create: `src/Messenger/PdoTransport.php`

**Interfaces:**
- Consumes: `Messenger\PdoStore` (Task 3), `PhpSerializer`, messenger stamps/interfaces.
- Produces: `Messenger\PdoTransport implements TransportInterface, MessageCountAwareInterface, ListableReceiverInterface` with `__construct(SerializerInterface $serializer, PdoStore $store, string $queueName = 'async')`.

- [ ] **Step 1: Write the failing check**

`scratchpad/verify_task4.php`:

```php
<?php
require __DIR__ . '/../src/init.php';

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

$tmp = sys_get_temp_dir() . '/messenger_t4_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
$store = new Messenger\PdoStore('mtest');
$store->setup();

$transport = new Messenger\PdoTransport(new PhpSerializer(), $store, 'async');

$sent = $transport->send(new Envelope(new Message\SpeakMessage('roundtrip', 'v1', 'en-US')));
assert($sent->last(TransportMessageIdStamp::class) !== null, 'send() stamps a transport id');
assert($transport->getMessageCount() === 1, 'one message queued');

$received = iterator_to_array($transport->get());
assert(count($received) === 1, 'get() yields one envelope');
$env = $received[0];
assert($env->getMessage() instanceof Message\SpeakMessage, 'message decoded');
assert($env->getMessage()->text === 'roundtrip', 'payload preserved');
assert($env->last(TransportMessageIdStamp::class) !== null, 'received envelope carries id');

$transport->ack($env);
assert($transport->getMessageCount() === 0, 'ack removes the message');
assert($transport->find($sent->last(TransportMessageIdStamp::class)->getId()) === null, 'find after ack is null');
echo "TASK4 OK\n";
@unlink($tmp);
```

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task4.php`
Expected: fatal error — class `Messenger\PdoTransport` not found.

- [ ] **Step 3: Implement the transport**

`src/Messenger/PdoTransport.php`:

```php
<?php

declare(strict_types=1);

namespace Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class PdoTransport implements TransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly PdoStore $store,
        private readonly string $queueName = 'async',
    ) {}

    public function get(): iterable
    {
        $row = $this->store->get($this->queueName);

        return null === $row ? [] : [$this->hydrate($row)];
    }

    public function ack(Envelope $envelope): void
    {
        $this->store->deleteById($this->idOf($envelope));
    }

    public function reject(Envelope $envelope): void
    {
        $this->store->deleteById($this->idOf($envelope));
    }

    public function send(Envelope $envelope): Envelope
    {
        $encoded = $this->serializer->encode($envelope);

        $id = $this->store->insert(
            $this->queueName,
            $encoded['body'],
            json_encode($encoded['headers'] ?? [], JSON_UNESCAPED_SLASHES)
        );

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function getMessageCount(): int
    {
        return $this->store->count($this->queueName);
    }

    public function all(?int $limit = null): iterable
    {
        foreach ($this->store->all($this->queueName, $limit) as $row)
        {
            yield $this->hydrate($row);
        }
    }

    public function find(mixed $id): ?Envelope
    {
        $row = $this->store->find((string) $id);

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Envelope
    {
        $headers = json_decode((string) ($row['headers'] ?? '[]'), true);

        $envelope = $this->serializer->decode([
            'body'    => (string) $row['body'],
            'headers' => is_array($headers) ? $headers : [],
        ]);

        return $envelope->with(new TransportMessageIdStamp($row['id']));
    }

    private function idOf(Envelope $envelope): string
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return (string) ($stamp?->getId() ?? '');
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task4.php`
Expected: prints `TASK4 OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/Messenger/PdoTransport.php
git commit -m ":sparkles: add PDO-backed messenger transport"
```

---

### Task 5: `SpeakMessageHandler`

**Files:**
- Create: `src/MessageHandler/SpeakMessageHandler.php`

**Interfaces:**
- Consumes: `Provider\SynthesisProviderStack`, `Service\LoggerService`, `SpeechSynthesis\SpeechSynthesisUtterance`.
- Produces: `MessageHandler\SpeakMessageHandler` invokable `__invoke(Message\SpeakMessage $message): ?string` (returns the persisted audio path, or null on empty synthesis). Persists audio under `resolve_path('%data%/tts')`.

- [ ] **Step 1: Write the failing check**

`scratchpad/verify_task5.php` uses a stub provider (implements `SpeechSynthesisInterface`) that writes a temp mp3 and returns a `SpeechSynthesisResult`:

```php
<?php
require __DIR__ . '/../src/init.php';

use Provider\SynthesisProviderStack;
use Service\LoggerService;
use SpeechSynthesis\SpeechSynthesisInterface;
use SpeechSynthesis\SpeechSynthesisResult;
use SpeechSynthesis\SpeechSynthesisUtterance;

$stub = new class implements SpeechSynthesisInterface {
    public function getName(): string { return 'stub'; }
    public function getDescription(): string { return 'stub'; }
    public function speak(SpeechSynthesisUtterance $utterance): SpeechSynthesisResult {
        $path = sys_get_temp_dir() . '/stub_' . uniqid() . '.mp3';
        file_put_contents($path, 'ID3-FAKE');
        return new SpeechSynthesisResult('stub', $utterance->getVoice(), 'id', $path, 'audio/mpeg', 0.0);
    }
    public function getVoices(?string $lang = null): array { return []; }
    public function hasVoice(string $name): bool { return true; }
    public function getFile(string $identifier): ?\FileResponseView { return null; }
    public function prune(\DateTimeInterface $before) {}
};

$handler = new MessageHandler\SpeakMessageHandler(
    new SynthesisProviderStack([$stub]),
    Services::getLogger()
);

$dest = $handler(new Message\SpeakMessage('hello world', 'en-US-BrianMultilingualNeural', 'en-US'));
assert(is_string($dest) && is_file($dest), 'handler persisted an audio file');
assert(str_ends_with($dest, '.mp3'), 'audio saved as mp3');
echo "TASK5 OK\n";
@unlink($dest);
```

Note: `Services::getLogger()` returns the app logger (a `LoggerService`-compatible PSR logger). If its type does not match the `LoggerService` hint, construct the handler with `new LoggerService(ApplicationLogger::getLogger())` instead — mirror whatever `config/dependency-injection.php` aliases `LoggerInterface` to.

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task5.php`
Expected: fatal error — class `MessageHandler\SpeakMessageHandler` not found.

- [ ] **Step 3: Implement the handler**

`src/MessageHandler/SpeakMessageHandler.php`:

```php
<?php

declare(strict_types=1);

namespace MessageHandler;

use Message\SpeakMessage;
use Provider\SynthesisProviderStack;
use Service\LoggerService;
use SpeechSynthesis\SpeechSynthesisUtterance;

final class SpeakMessageHandler
{
    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
        private readonly LoggerService $logger,
    ) {}

    public function __invoke(SpeakMessage $message): ?string
    {
        $result = $this->synthesisProviderStack->speak(
            SpeechSynthesisUtterance::make([
                'voice'  => $message->voice,
                'text'   => $message->text,
                'lang'   => $message->lang,
                'format' => $message->format,
            ])
        );

        if ( ! $result->path || ! is_file($result->path))
        {
            $this->logger->log(LoggerService::ERR, sprintf('async synthesis produced no audio for: %s', $message->text));
            return null;
        }

        $dir = resolve_path('%data%/tts');

        if ( ! is_dir($dir))
        {
            @mkdir($dir, 0o775, true);
        }

        $dest = $dir . DIRECTORY_SEPARATOR
            . sha1(implode('|', [$message->voice, $message->lang, $message->text]))
            . '.' . $message->format;

        if ( ! @rename($result->path, $dest))
        {
            @copy($result->path, $dest);
            @unlink($result->path);
        }

        $this->logger->log(LoggerService::INFO, sprintf('async synthesis stored: %s', $dest));

        return $dest;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task5.php`
Expected: prints `TASK5 OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/MessageHandler/SpeakMessageHandler.php
git commit -m ":sparkles: add SpeakMessage handler for async synthesis"
```

---

### Task 6: Container wiring (bus, locators, transport) + composer

**Files:**
- Modify: `config/dependency-injection.php`
- Modify: `composer.json`

**Interfaces:**
- Consumes: `PdoStore`, `PdoTransport`, `PhpSerializer`, `SpeakMessage`, `SpeakMessageHandler`.
- Produces in the container: `Messenger\PdoStore`, `Messenger\PdoTransport`, `Symfony\Component\Messenger\Transport\Serialization\PhpSerializer`, and `Symfony\Component\Messenger\MessageBusInterface` (a `MessageBus` with sync-by-default routing, async via `TransportNamesStamp`).

- [ ] **Step 1: Declare event-dispatcher in composer.json**

In `composer.json` `require`, add (keep packages sorted):

```json
"symfony/event-dispatcher": "^7",
```

Then refresh the lock so `composer.lock` records the explicit requirement. Use whichever Composer this machine has, driven by PHP 8.2. Discover it first:

```powershell
Get-Command composer -ErrorAction SilentlyContinue   # global composer?
Test-Path D:\dev\php8\apis\voice\composer.phar        # local phar?
```

Then run one of:

```powershell
# if a global `composer` exists:
& 'C:\WebSdk\lib\php\8.2\php.exe' (Get-Command composer).Source update symfony/event-dispatcher --no-interaction
# or, if composer.phar is present in the project:
& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\composer.phar update symfony/event-dispatcher --no-interaction
```

Expected: the package is already installed, so this only updates `composer.lock` (no download). If no Composer is available on the machine, stop and ask the user how they run Composer here before proceeding.

- [ ] **Step 2: Add the messenger service registrations**

In `config/dependency-injection.php`, add these `use` statements at the top with the others:

```php
use Message\SpeakMessage;
use MessageHandler\SpeakMessageHandler;
use Messenger\PdoStore;
use Messenger\PdoTransport;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
```

Inside the `return function (Container $container) { ... }`, add a second `$container->setMany([...])` block (or extend the existing one) with:

```php
    $messengerSerializer = new PhpSerializer();
    $messengerStore      = new PdoStore(
        env_get('MESSENGER_DB_CONNECTION', '0', false),
        'messenger_messages',
        (int) env_get('MESSENGER_REDELIVER_TIMEOUT', 3600, false)
    );
    $messengerTransport  = new PdoTransport($messengerSerializer, $messengerStore, 'async');

    $container->setMany([
        PhpSerializer::class    => $messengerSerializer,
        PdoStore::class         => $messengerStore,
        PdoTransport::class     => $messengerTransport,
        MessageBusInterface::class => function (Container $container) use ($messengerTransport)
        {
            // Container exposing the single "async" sender to the SendersLocator.
            $senders = new class($messengerTransport) implements PsrContainerInterface
            {
                public function __construct(private readonly PdoTransport $transport) {}

                public function get(string $id): mixed
                {
                    return $this->transport;
                }

                public function has(string $id): bool
                {
                    return 'async' === $id;
                }
            };

            // Empty static map → no sender unless a TransportNamesStamp is present (sync by default).
            $sendersLocator  = new SendersLocator([], $senders);

            $handlersLocator = new HandlersLocator([
                SpeakMessage::class => [
                    static fn (SpeakMessage $message) => $container->get(SpeakMessageHandler::class)($message),
                ],
            ]);

            return new MessageBus([
                new SendMessageMiddleware($sendersLocator),
                new HandleMessageMiddleware($handlersLocator),
            ]);
        },
    ]);
```

- [ ] **Step 3: Write the wiring check**

`scratchpad/verify_task6.php`:

```php
<?php
require __DIR__ . '/../src/init.php';

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

// Point the messenger connection at a temp sqlite DB for the test.
$tmp = sys_get_temp_dir() . '/messenger_t6_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
putenv('MESSENGER_DB_CONNECTION=mtest');
$_ENV['MESSENGER_DB_CONNECTION'] = 'mtest';

$container = Services::getContainer();
$store     = $container->get(Messenger\PdoStore::class);
$store->setup();

$bus = $container->get(MessageBusInterface::class);
assert($bus instanceof MessageBusInterface, 'bus resolved from container');

// async dispatch → row persisted, NOT handled inline
$bus->dispatch(new Envelope(new Message\SpeakMessage('queued'), [new TransportNamesStamp(['async'])]));
assert($container->get(Messenger\PdoTransport::class)->getMessageCount() === 1, 'async dispatch persisted one row');
echo "TASK6 OK\n";
@unlink($tmp);
```

Note on the connection override: the container builds `PdoStore` from `env_get('MESSENGER_DB_CONNECTION', ...)` when first resolved. Set the env var *before* the first `Services::getContainer()->get(...)` call (as above). If `Services::getContainer()` has already been built at boot with the default connection, adapt the check to instead construct a fresh `PdoStore('mtest')` and a `PdoTransport` around it and assert the count — the goal is only to prove the bus routes async vs sync correctly.

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task6.php`
Expected: prints `TASK6 OK`.

- [ ] **Step 5: Commit**

```powershell
git add config/dependency-injection.php composer.json composer.lock
git commit -m ":sparkles: wire messenger bus, transport and handler into the container"
```

---

### Task 7: `messenger:consume` command

**Files:**
- Create: `src/Command/MessengerConsumeCommand.php`
- Modify: `config/command.php`

**Interfaces:**
- Consumes: `MessageBusInterface`, `Messenger\PdoTransport`, `Worker\PidLock`, `Symfony\Component\Messenger\Worker`, `Symfony\Component\EventDispatcher\EventDispatcher`, the two `StopWorkerOn*Listener`s.
- Produces: console command `messenger:consume` with options `--limit`, `--time-limit`, `--sleep`.

- [ ] **Step 1: Write the failing check (end-to-end, one message)**

`scratchpad/verify_task7.php` drives the command through the console `Application`:

```php
<?php
require __DIR__ . '/../src/init.php';

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

$tmp = sys_get_temp_dir() . '/messenger_t7_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
$_ENV['MESSENGER_DB_CONNECTION'] = 'mtest';
putenv('MESSENGER_DB_CONNECTION=mtest');

$container = Services::getContainer();
$container->get(Messenger\PdoStore::class)->setup();
$container->get(Symfony\Component\Messenger\MessageBusInterface::class)
    ->dispatch(new Envelope(new Message\SpeakMessage('consume me', 'en-US-BrianMultilingualNeural'), [new TransportNamesStamp(['async'])]));

assert($container->get(Messenger\PdoTransport::class)->getMessageCount() === 1, 'one message queued');

/** @var Symfony\Component\Console\Application $app */
$app = $container->get(NGSOFT\Console\ConsoleApplication::class);
require_secure(resolve_path('%config%/command.php'))($app);
$app->setAutoExit(false);
$code = $app->run(new ArrayInput(['command' => 'messenger:consume', '--limit' => '1', '--sleep' => '10000']), new BufferedOutput());

assert($code === 0, 'consume exits 0');
assert($container->get(Messenger\PdoTransport::class)->getMessageCount() === 0, 'message consumed');
echo "TASK7 OK\n";
@unlink($tmp);
```

Note: synthesis will actually run for the handler. On Windows this reaches the real Microsoft Edge provider; if network/synthesis is unavailable in the test environment, the handler logs an error and returns null but the worker still acks the message (count → 0), so the assertion holds. To assert the exit code and consumption without real synthesis, temporarily queue a message type with a no-op handler, or run this check on a machine with synthesis available (the manual verification below is the real end-to-end proof).

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task7.php`
Expected: error — command `messenger:consume` is not defined.

- [ ] **Step 3: Implement the consume command**

`src/Command/MessengerConsumeCommand.php`:

```php
<?php

declare(strict_types=1);

namespace Command;

use Messenger\PdoTransport;
use NGSOFT\Console\Profile\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Traits\CommandTrait;
use Worker\PidLock;

#[AsCommand('messenger:consume', 'Consume queued async messages (e.g. speech synthesis)')]
class MessengerConsumeCommand extends Command
{
    use CommandTrait;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly PdoTransport $transport,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Stop after N messages (0 = unlimited)', '0');
        $this->addOption('time-limit', null, InputOption::VALUE_OPTIONAL, 'Stop after N seconds (0 = unlimited)', '0');
        $this->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Microseconds to sleep when the queue is empty', '1000000');
    }

    protected function executeCommand(CommandHelper $io, InputInterface $input)
    {
        if ( ! PidLock::lock('messenger', 1))
        {
            $io->warning('A messenger worker is already running.');
            return self::SUCCESS;
        }

        try
        {
            $limit     = (int) $input->getOption('limit');
            $timeLimit = (int) $input->getOption('time-limit');
            $sleep     = (int) $input->getOption('sleep');

            $dispatcher = new EventDispatcher();

            if ($limit > 0)
            {
                $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
            }

            if ($timeLimit > 0)
            {
                $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));
            }

            $worker = new Worker(['async' => $this->transport], $this->bus, $dispatcher, $this->logger);

            $io->info('Consuming messages. Press CTRL+C to stop.');

            $worker->run(['sleep' => max(0, $sleep)]);
        } finally
        {
            PidLock::unlock('messenger');
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register the command**

In `config/command.php`, add:

```php
use Command\MessengerConsumeCommand;
// ...
$app->add(MessengerConsumeCommand::class);
```

- [ ] **Step 5: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task7.php`
Expected: prints `TASK7 OK` (on a machine with synthesis available; otherwise see the Step 1 note).

- [ ] **Step 6: Commit**

```powershell
git add src/Command/MessengerConsumeCommand.php config/command.php
git commit -m ":sparkles: add messenger:consume worker command guarded by PidLock"
```

---

### Task 8: `speak --async` option

**Files:**
- Modify: `src/Command/SpeakCommand.php`

**Interfaces:**
- Consumes: `MessageBusInterface`, `Message\SpeakMessage`, `TransportNamesStamp`.
- Produces: `speak` gains `--async`; with it, the command enqueues a `SpeakMessage` and returns without local playback.

- [ ] **Step 1: Write the failing check**

`scratchpad/verify_task8.php`:

```php
<?php
require __DIR__ . '/../src/init.php';

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$tmp = sys_get_temp_dir() . '/messenger_t8_' . uniqid() . '.sqlite';
SqlConnector::setDatabaseConfigurationUrl('sqlite:' . $tmp, 'mtest');
$_ENV['MESSENGER_DB_CONNECTION'] = 'mtest';
putenv('MESSENGER_DB_CONNECTION=mtest');

$container = Services::getContainer();
$container->get(Messenger\PdoStore::class)->setup();

/** @var Symfony\Component\Console\Application $app */
$app = $container->get(NGSOFT\Console\ConsoleApplication::class);
require_secure(resolve_path('%config%/command.php'))($app);
$app->setAutoExit(false);
$code = $app->run(
    new ArrayInput(['command' => 'speak', 'text' => 'async hi', '--async' => true, '--voice' => 'en-US-BrianMultilingualNeural']),
    new BufferedOutput()
);

assert($code === 0, 'speak --async exits 0');
assert($container->get(Messenger\PdoTransport::class)->getMessageCount() === 1, 'speak --async enqueued one message (no playback)');
echo "TASK8 OK\n";
@unlink($tmp);
```

- [ ] **Step 2: Run to verify it fails**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task8.php`
Expected: the message count is 0 (the `--async` option does not exist yet, so `speak` runs synchronously / errors on the unknown option).

- [ ] **Step 3: Add the option and the async branch**

In `src/Command/SpeakCommand.php`:

1. Add imports:

```php
use Message\SpeakMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
```

2. Inject the bus in the constructor (extend the existing signature):

```php
    public function __construct(
        private readonly SynthesisProviderStack $synthesisProviderStack,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }
```

3. Register the option in `configure()` (after the `voice` option):

```php
        $this->addOption('async', null, InputOption::VALUE_NONE, 'Queue the synthesis for a background worker instead of playing locally');
```

4. At the very start of `executeCommand()` (right after `$text = $input->getArgument('text');` and the empty-text guard), add:

```php
        if ($input->getOption('async'))
        {
            $this->bus->dispatch(new Envelope(
                new SpeakMessage(
                    $text,
                    (string) $input->getOption('voice'),
                    (string) $input->getOption('lang')
                ),
                [new TransportNamesStamp(['async'])]
            ));

            $this->log('queued async synthesis: %s', [$text]);
            $io->success('Queued for async synthesis.');

            return self::SUCCESS;
        }
```

Add the `use Symfony\Component\Messenger\Envelope;` import as well.

- [ ] **Step 4: Run to verify it passes**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' -d zend.assertions=1 -d assert.exception=1 D:\dev\php8\apis\voice\scratchpad\verify_task8.php`
Expected: prints `TASK8 OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/Command/SpeakCommand.php
git commit -m ":sparkles: add --async option to speak command"
```

---

### Task 9: Manual end-to-end verification + cleanup

**Files:**
- Delete: the `scratchpad/verify_task*.php` files (throwaway).

- [ ] **Step 1: Setup the real table**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\bin\console messenger:setup`
Expected: "Messenger table ready (0 connection)." (or your `MESSENGER_DB_CONNECTION`). Requires the configured DB (MySQL for connection `0`) to be reachable; otherwise set `MESSENGER_DB_CONNECTION=sqlite` in `.env.local` first.

- [ ] **Step 2: Enqueue a job**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\bin\console speak "bonjour asynchrone" --voice "en-US-BrianMultilingualNeural" --async`
Expected: "Queued for async synthesis." and no audio plays.

- [ ] **Step 3: Consume it**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\bin\console messenger:consume --limit 1`
Expected: the worker starts, handles one message, an mp3 appears under `var/data/tts/`, exit code 0.

- [ ] **Step 4: Confirm no sync regression**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\bin\console speak "test synchrone" --voice "en-US-BrianMultilingualNeural"`
Expected: audio plays locally exactly as before (unchanged behaviour). This is the same path the global CLAUDE.md vocalisation rule uses — it MUST still work.

- [ ] **Step 5: Static analysis**

Run: `& 'C:\WebSdk\lib\php\8.2\php.exe' D:\dev\php8\apis\voice\vendor\bin\phan` (or the project's configured phan entry point).
Expected: no new errors introduced by the added files.

- [ ] **Step 6: Remove throwaway verify scripts**

```powershell
Remove-Item D:\dev\php8\apis\voice\scratchpad\verify_task*.php -ErrorAction SilentlyContinue
```

- [ ] **Step 7: Final commit (docs if any)**

```powershell
git add -A
git commit -m ":white_check_mark: verify messenger async speech end-to-end"
```

---

## Notes / deviations from the spec

1. **Serializer & event-dispatcher:** the spec aimed for "zero event-dispatcher". In reality `symfony/event-dispatcher` is already installed, and using it lets us reuse Messenger's stock `StopWorkerOn*Listener`s for `--limit`/`--time-limit` instead of a hand-rolled loop. The plan declares it explicitly in `require`; no download occurs. Runtime dependency footprint is unchanged in practice.
2. **No `PdoTransportFactory`:** the spec listed a DSN factory. Since there is no Symfony Flex/DSN pipeline here, the transport is constructed directly in the container. Dropped as YAGNI. If a DSN-style config is later wanted, wrap `PdoStore` construction in a factory that parses a `pdo://<connection>/<queue>` string.
3. **Connection name:** the project has no `default` DB connection; the transport uses `MESSENGER_DB_CONNECTION` (default `0`). Reviewers should confirm connection `0` (MySQL) is intended for production, or set `sqlite` for a file-based queue.
4. **Testing:** no PHPUnit in the repo, so each task ships a throwaway `scratchpad/verify_taskN.php` run under `zend.assertions=1`. If the team later adopts PHPUnit (spec §5 option B), port these into `tests/` unchanged in spirit.
