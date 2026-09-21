<?php

declare(strict_types=1);

namespace Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class PdoTransport implements TransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private PdoStore $store,
        private string $queueName = 'async',
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

        $id      = $this->store->insert(
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
        /** @var ?array<string,string> $headers */
        $headers  = json_decode((string) ($row['headers'] ?? '{}'), true);

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
