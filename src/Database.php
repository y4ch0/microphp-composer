<?php
/**
 * MicroPHP Framework
 * Database management core file.
 */

namespace MicroPHP;

use MicroPHP\Enums\DbDriver;
use PDOException;
use ValueError;

class Database {
    private static $instance = null;
    private $pdo;
    private mixed $mongoManager = null;
    private ?DbDriver $driver = null;
    private string $mongoDatabaseName = '';
    private $error;

    /**
     * Create the configured database connection.
     */
    private function __construct(?\PDO $pdo = null, ?DbDriver $driver = null) {
        if ($pdo !== null) {
            $this->pdo = $pdo;
            $this->driver = $driver ?? DbDriver::Sqlite;
            return;
        }
        try {
            $this->driver = self::configuredDriver();
        } catch (ValueError) {
            throw new PDOException(
                "Unsupported database driver: " . self::configString('DB_DRIVER', 'sqlite')
                . ". Supported drivers: " . implode(', ', DbDriver::supportedValues())
            );
        }

        if ($this->driver->isMongoDb()) {
            $this->connectMongoDb();
            return;
        }

        $this->connectPdo();
    }

    private function connectPdo(): void {
        $dsn = $this->driver->buildDsn(
            host: self::configString('DB_HOST', 'localhost'),
            name: self::configString('DB_NAME', 'microphp'),
            path: $this->driver === DbDriver::Sqlite
                ? self::resolveSqlitePath(self::configString('DB_PATH', 'database/storage.sqlite'))
                : self::configString('DB_PATH'),
            port: self::configString('DB_PORT'),
            explicitDsn: self::configString('DB_DSN'),
        );

        $options = [
            \PDO::ATTR_PERSISTENT => self::configBool('DB_PERSISTENT', false),
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        // pdo_sqlsrv uses its own direct-query option and does not consistently
        // support PDO::ATTR_EMULATE_PREPARES. Other supported PDO drivers do.
        if ($this->driver !== DbDriver::SqlServer) {
            $options[\PDO::ATTR_EMULATE_PREPARES] = false;
        }

        if ($this->driver === DbDriver::SqlServer && defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_UTF8')) {
            $options[constant('PDO::SQLSRV_ATTR_ENCODING')] = constant('PDO::SQLSRV_ENCODING_UTF8');
        }

        try {
            $pdoDriver = $this->driver->pdoDriverName();
            if ($pdoDriver !== null && !in_array($pdoDriver, \PDO::getAvailableDrivers(), true)) {
                throw new PDOException("PDO driver '{$pdoDriver}' is not installed.");
            }

            $this->pdo = new \PDO(
                $dsn,
                self::configString('DB_USER'),
                self::configString('DB_PASS'),
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException("Database Connection Error: " . $e->getMessage(), 0, $e);
        }
    }

    private function connectMongoDb(): void {
        if (!class_exists('\MongoDB\Driver\Manager')) {
            throw new PDOException('Database Connection Error: MongoDB support requires the ext-mongodb PHP extension.');
        }

        $this->mongoDatabaseName = self::configString('DB_NAME', 'microphp');
        $uri = $this->driver->buildDsn(
            host: self::configString('DB_HOST', 'localhost'),
            name: $this->mongoDatabaseName,
            path: self::configString('DB_PATH'),
            port: self::configString('DB_PORT'),
            explicitDsn: self::configString('DB_DSN'),
        );

        $options = [];
        if (self::configString('DB_USER') !== null && self::configString('DB_USER') !== '') {
            $options['username'] = self::configString('DB_USER');
        }
        if (self::configString('DB_PASS') !== null && self::configString('DB_PASS') !== '') {
            $options['password'] = self::configString('DB_PASS');
        }
        if (self::configString('DB_AUTH_SOURCE') !== null && self::configString('DB_AUTH_SOURCE') !== '') {
            $options['authSource'] = self::configString('DB_AUTH_SOURCE');
        }

        try {
            $this->mongoManager = new \MongoDB\Driver\Manager($uri, $options);
            $this->mongoManager->executeCommand($this->mongoDatabaseName, new \MongoDB\Driver\Command(['ping' => 1]));
        } catch (\Throwable $e) {
            throw new PDOException("Database Connection Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the shared database instance.
     *
     * @return self Shared database instance.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Install an isolated PDO connection, intended for application bootstraps and tests. */
    public static function usePdo(\PDO $pdo, ?DbDriver $driver = null): self {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        try {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException) {
            // Some PDO drivers do not expose this attribute after connection.
        }
        return self::$instance = new self($pdo, $driver ?? DbDriver::Sqlite);
    }

    public static function resolveSqlitePath(?string $path): string {
        $path = trim((string) $path);
        if ($path === '' || $path === ':memory:' || str_starts_with($path, 'file:')) {
            return $path === '' ? ROOT_PATH . '/database/storage.sqlite' : $path;
        }
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
        return $isAbsolute ? $path : rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . $path;
    }

    public static function configuredDriver(): DbDriver {
        return DbDriver::fromName(self::configString('DB_DRIVER', 'sqlite'));
    }

    public function getDriver(): DbDriver {
        return $this->driver;
    }

    /**
     * Execute a prepared SQL query.
     *
     * @param string $sql SQL statement to execute.
     * @param array<int|string,mixed> $params Parameters bound to the prepared statement.
     * @return array<int,array<string,mixed>>|bool Result rows for SELECT queries, true for successful write queries, or false on error.
     */
    public function query($sql, $params = []) {
        $pdo = $this->pdoConnection('raw SQL queries');
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare($sql);

            if ($this->shouldConvertAssociativeParamsToList($sql, $params)) {
                $params = array_values($params);
            }

            $stmt->execute($params);

            if (str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
                return $stmt->fetchAll();
            }

            return true;

        } catch (PDOException $e) {
            $this->recordError($e->getMessage());
            return false;
        }
    }

    /**
     * Determine whether associative params should be passed as a positional list.
     *
     * @param string $sql SQL statement to inspect.
     * @param array<int|string,mixed> $params Parameters passed by the caller.
     * @return bool True when associative params should be converted to values only.
     */
    private function shouldConvertAssociativeParamsToList(string $sql, array $params): bool {
        if ($params === []) {
            return false;
        }

        $isAssoc = array_keys($params) !== range(0, count($params) - 1);
        $usesPositionalPlaceholders = strpos($sql, '?') !== false && strpos($sql, ':') === false;

        return $isAssoc && $usesPositionalPlaceholders;
    }

    /**
     * Execute a write statement and return the number of affected rows,
     * instead of the ambiguous true/false that query() returns for writes.
     * Used internally by insert()/update()/delete().
     *
     * @param string $sql SQL statement to execute.
     * @param array<int|string,mixed> $params Parameters bound to the prepared statement.
     * @return int|false Number of affected rows, or false on error.
     */
    public function execute(string $sql, array $params = []): int|false {
        $pdo = $this->pdoConnection('SQL write statements');
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare($sql);

            if ($this->shouldConvertAssociativeParamsToList($sql, $params)) {
                $params = array_values($params);
            }

            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->recordError($e->getMessage());
            return false;
        }
    }

    /**
     * Get the ID generated by the most recent INSERT.
     *
     * @return string Last insert ID.
     */
    public function lastInsertId(): string {
        $pdo = $this->pdoConnection('lastInsertId()');
        if ($pdo === null) {
            return '';
        }

        if ($this->driver === DbDriver::SqlServer) {
            $stmt = $pdo->query('SELECT SCOPE_IDENTITY() AS id');
            if ($stmt === false) {
                return '';
            }

            return (string) $stmt->fetchColumn();
        }

        return $pdo->lastInsertId();
    }

    /**
     * Get the last recorded database error.
     *
     * @return string|null Last database error message, or null when no error has been recorded.
     */
    public function getError() {
        return $this->error;
    }

    public function recordError(string $message): void {
        $this->error = $message;
        error_log($this->error);
    }

    public function mongoManager(): mixed {
        if (!$this->driver?->isMongoDb() || $this->mongoManager === null) {
            throw new \LogicException('The configured database driver is not MongoDB.');
        }

        return $this->mongoManager;
    }

    public function mongoDatabaseName(): string {
        if (!$this->driver?->isMongoDb()) {
            throw new \LogicException('The configured database driver is not MongoDB.');
        }

        return $this->mongoDatabaseName;
    }

    private function pdoConnection(string $operation): ?\PDO {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $driver = $this->driver?->value ?? self::configString('DB_DRIVER', 'unknown');
        $this->recordError("Database driver '{$driver}' does not support {$operation}. Use Database::collection() for MongoDB collections.");

        return null;
    }

    private static function configString(string $constant, ?string $default = null): ?string {
        if (!defined($constant)) {
            return $default;
        }

        $value = constant($constant);

        return $value === null ? null : (string) $value;
    }

    private static function configBool(string $constant, bool $default): bool {
        if (!defined($constant)) {
            return $default;
        }
        return filter_var(constant($constant), FILTER_VALIDATE_BOOLEAN);
    }

    public static function transaction(callable $callback): mixed {
        $pdo = self::getInstance()->pdoConnection('transactions');
        if (!$pdo instanceof \PDO) {
            throw new \LogicException('Transactions require a PDO database driver.');
        }
        if ($pdo->inTransaction()) {
            throw new \LogicException('Nested transactions are not supported.');
        }
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // -- Query builder entry points -------------------------------------------------
    //
    // These sit on top of query()/execute() and QueryBuilder; see
    // src/QueryBuilder.php for the fluent chain (select(), where(), join(),
    // orderBy(), limit(), get(), first(), count()).

    /**
     * Start a fluent, readable query against a table.
     *
     * @param string $table Table name.
     * @return QueryBuilder Fluent query builder for the given table.
     */
    public static function table(string $table): QueryBuilder {
        return QueryBuilder::forTable($table);
    }

    /**
     * Start a MongoDB collection operation.
     *
     * MongoDB is not SQL/PDO based, so use collection() instead of query(),
     * table(), insert(), update(), or delete() when DB_DRIVER=mongodb.
     *
     * @param string $collection Collection name.
     * @return MongoCollection Collection adapter.
     */
    public static function collection(string $collection): MongoCollection {
        $database = self::getInstance();
        if (!$database->getDriver()->isMongoDb()) {
            throw new \LogicException('Database::collection() requires DB_DRIVER=mongodb.');
        }

        return new MongoCollection($database, $collection);
    }

    /**
     * Start a query joining two tables on matching columns.
     * Additional tables can be joined with QueryBuilder::join().
     *
     * @param string $table Starting table.
     * @param string $joinTable Table to join.
     * @param string $firstColumn First table.column expression.
     * @param string $secondColumn Second table.column expression.
     * @param string $type Join type: INNER, LEFT, RIGHT, or FULL.
     * @return QueryBuilder Fluent query builder.
     */
    public static function join(
        string $table,
        string $joinTable,
        string $firstColumn,
        string $secondColumn,
        string $type = 'INNER'
    ): QueryBuilder {
        return self::table($table)->join($joinTable, $firstColumn, '=', $secondColumn, $type);
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table Table name.
     * @param array<string,mixed> $data Column => value pairs to insert.
     * @return int|false The new row's ID, or false on error.
     */
    public static function insert(string $table, array $data): int|false {
        if ($data === []) {
            return false;
        }

        QueryBuilder::assertIdentifier($table, 'Table name');

        $columns = array_keys($data);
        foreach ($columns as $column) {
            QueryBuilder::assertSimpleIdentifier((string) $column, 'INSERT column');
        }

        $placeholders = array_map(static fn (string $c): string => ':set_' . $c, $columns);
        $params = [];
        foreach ($data as $column => $value) {
            $params[':set_' . $column] = $value;
        }

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $affected = self::getInstance()->execute($sql, $params);
        return $affected === false ? false : (int) self::getInstance()->lastInsertId();
    }

    /**
     * Update rows in a table that match the given conditions.
     *
     * @param string $table Table name.
     * @param array<string,mixed> $data Column => new value pairs to set.
     * @param array<string,mixed> $conditions Column => value (or column => [operator, value]) to match, AND-ed together.
     *                                         Empty conditions are rejected to prevent accidental mass updates.
     * @return int|false Number of updated rows, or false on error.
     */
    public static function update(string $table, array $data, array $conditions): int|false {
        if ($data === []) {
            return false;
        }

        if ($conditions === []) {
            throw new \InvalidArgumentException('Database::update() requires at least one condition.');
        }

        QueryBuilder::assertIdentifier($table, 'Table name');

        $setSql = [];
        $params = [];
        foreach ($data as $column => $value) {
            QueryBuilder::assertSimpleIdentifier((string) $column, 'UPDATE column');
            $setSql[] = "{$column} = :set_{$column}";
            $params[':set_' . $column] = $value;
        }

        $where = QueryBuilder::compileConditions($conditions);
        $sql = "UPDATE {$table} SET " . implode(', ', $setSql);
        if ($where['sql'] !== '') {
            $sql .= ' WHERE ' . $where['sql'];
        }

        return self::getInstance()->execute($sql, $params + $where['params']);
    }

    /**
     * Delete rows from a table that match the given conditions.
     *
     * @param string $table Table name.
     * @param array<string,mixed> $conditions Column => value (or column => [operator, value]) to match, AND-ed together.
     *                                         Empty conditions are rejected to prevent accidental mass deletes.
     * @return int|false Number of deleted rows, or false on error.
     */
    public static function delete(string $table, array $conditions): int|false {
        if ($conditions === []) {
            throw new \InvalidArgumentException('Database::delete() requires at least one condition.');
        }

        QueryBuilder::assertIdentifier($table, 'Table name');

        $where = QueryBuilder::compileConditions($conditions);
        $sql = "DELETE FROM {$table}";
        if ($where['sql'] !== '') {
            $sql .= ' WHERE ' . $where['sql'];
        }

        return self::getInstance()->execute($sql, $where['params']);
    }

    public static function updateAll(string $table, array $data): int|false {
        if ($data === []) {
            return false;
        }
        QueryBuilder::assertIdentifier($table, 'Table name');
        $set = [];
        $params = [];
        foreach ($data as $column => $value) {
            QueryBuilder::assertSimpleIdentifier((string) $column, 'UPDATE column');
            $set[] = "{$column} = :set_{$column}";
            $params[':set_' . $column] = $value;
        }
        return self::getInstance()->execute("UPDATE {$table} SET " . implode(', ', $set), $params);
    }

    public static function deleteAll(string $table): int|false {
        QueryBuilder::assertIdentifier($table, 'Table name');
        return self::getInstance()->execute("DELETE FROM {$table}");
    }

}
