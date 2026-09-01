<?php
/**
 * MicroPHP Framework
 * Small MongoDB collection adapter used by Database::collection().
 */

namespace MicroPHP;

final class MongoCollection
{
    private ?string $error = null;

    public function __construct(
        private readonly Database $database,
        private readonly string $collection
    ) {
        if (trim($collection) === '') {
            throw new \InvalidArgumentException('MongoDB collection name cannot be empty.');
        }
    }

    /**
     * Find documents in the collection.
     *
     * @param array<string,mixed> $filter MongoDB filter document.
     * @param array<string,mixed> $options MongoDB query options such as sort, limit, or projection.
     * @return array<int,array<string,mixed>>
     */
    public function find(array $filter = [], array $options = []): array
    {
        try {
            $query = new \MongoDB\Driver\Query($filter, $options);
            $cursor = $this->database->mongoManager()->executeQuery($this->namespace(), $query);
            $documents = [];

            foreach ($cursor as $document) {
                $documents[] = $this->normalizeDocument($document);
            }

            return $documents;
        } catch (\Throwable $e) {
            return $this->fail($e, []);
        }
    }

    /**
     * Find the first matching document.
     *
     * @param array<string,mixed> $filter MongoDB filter document.
     * @param array<string,mixed> $options MongoDB query options.
     * @return array<string,mixed>|null
     */
    public function first(array $filter = [], array $options = []): ?array
    {
        $options['limit'] = 1;
        $documents = $this->find($filter, $options);

        return $documents[0] ?? null;
    }

    /**
     * Insert one document and return its generated _id.
     *
     * @param array<string,mixed> $document
     * @return string|false
     */
    public function insert(array $document): string|false
    {
        try {
            $bulk = new \MongoDB\Driver\BulkWrite();
            $id = $bulk->insert($document);
            $this->database->mongoManager()->executeBulkWrite($this->namespace(), $bulk);

            return self::idToString($id);
        } catch (\Throwable $e) {
            return $this->fail($e, false);
        }
    }

    /**
     * Update matching documents. Plain data is wrapped in $set; update operator
     * documents such as ['$inc' => ['views' => 1]] are passed through.
     *
     * @param array<string,mixed> $filter
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options Supports multi and upsert.
     * @return int|false Modified document count, or false on error.
     */
    public function update(array $filter, array $data, array $options = []): int|false
    {
        if ($data === []) {
            return false;
        }

        try {
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->update($filter, $this->updateDocument($data), [
                'multi' => (bool) ($options['multi'] ?? true),
                'upsert' => (bool) ($options['upsert'] ?? false),
            ]);

            $result = $this->database->mongoManager()->executeBulkWrite($this->namespace(), $bulk);

            return $result->getModifiedCount();
        } catch (\Throwable $e) {
            return $this->fail($e, false);
        }
    }

    /**
     * Delete matching documents.
     *
     * @param array<string,mixed> $filter
     * @param array<string,mixed> $options Set limit to 1 to delete only the first match.
     * @return int|false Deleted document count, or false on error.
     */
    public function delete(array $filter, array $options = []): int|false
    {
        try {
            $limit = (int) ($options['limit'] ?? 0);
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->delete($filter, [
                'limit' => $limit === 1 ? 1 : 0,
            ]);

            $result = $this->database->mongoManager()->executeBulkWrite($this->namespace(), $bulk);

            return $result->getDeletedCount();
        } catch (\Throwable $e) {
            return $this->fail($e, false);
        }
    }

    /**
     * Execute a database command, for example ['ping' => 1].
     *
     * @param array<string,mixed> $command
     * @return array<int,array<string,mixed>>
     */
    public function command(array $command): array
    {
        try {
            $cursor = $this->database->mongoManager()->executeCommand(
                $this->database->mongoDatabaseName(),
                new \MongoDB\Driver\Command($command)
            );

            $documents = [];
            foreach ($cursor as $document) {
                $documents[] = $this->normalizeDocument($document);
            }

            return $documents;
        } catch (\Throwable $e) {
            return $this->fail($e, []);
        }
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    private function namespace(): string
    {
        return $this->database->mongoDatabaseName() . '.' . $this->collection;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function updateDocument(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (is_string($key) && str_starts_with($key, '$')) {
                return $data;
            }
        }

        return ['$set' => $data];
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeDocument(mixed $document): array
    {
        $normalized = self::normalizeValue($document);

        return is_array($normalized) ? $normalized : [];
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \MongoDB\BSON\ObjectId) {
            return (string) $value;
        }

        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }

        if ($value instanceof \MongoDB\BSON\Decimal128) {
            return (string) $value;
        }

        if ($value instanceof \MongoDB\BSON\Binary) {
            return base64_encode($value->getData());
        }

        if ($value instanceof \JsonSerializable) {
            return self::normalizeValue($value->jsonSerialize());
        }

        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalizeValue($item), $value);
        }

        if (is_object($value)) {
            return self::normalizeValue(get_object_vars($value));
        }

        return $value;
    }

    private static function idToString(mixed $id): string
    {
        if (is_scalar($id) || $id instanceof \Stringable) {
            return (string) $id;
        }

        $encoded = json_encode(self::normalizeValue($id), JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '' : $encoded;
    }

    private function fail(\Throwable $e, mixed $fallback): mixed
    {
        $this->error = $e->getMessage();
        $this->database->recordError($this->error);

        return $fallback;
    }
}
