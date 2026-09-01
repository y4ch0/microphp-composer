<?php
/**
 * MicroPHP Framework
 * Supported database drivers. Backed by the same strings used in config/app.php
 * and .env, so existing config keeps working while newer aliases can be used.
 */

namespace MicroPHP\Enums;

enum DbDriver: string
{
    case MySql = 'mysql';
    case MariaDb = 'mariadb';
    case PgSql = 'pgsql';
    case SqlServer = 'sqlsrv';
    case Sqlite = 'sqlite';
    case MongoDb = 'mongodb';

    /**
     * Resolve supported driver aliases from config/.env values.
     */
    public static function fromName(string $driver): self
    {
        return match (strtolower(trim($driver))) {
            'mysql' => self::MySql,
            'mariadb', 'maria' => self::MariaDb,
            'pgsql', 'postgres', 'postgresql' => self::PgSql,
            'sqlsrv', 'sqlserver', 'mssql' => self::SqlServer,
            'sqlite', 'sqlite3' => self::Sqlite,
            'mongodb', 'mongo' => self::MongoDb,
            default => throw new \ValueError("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * @return string[]
     */
    public static function supportedValues(): array
    {
        return [
            'sqlite',
            'mysql',
            'mariadb',
            'pgsql',
            'sqlsrv',
            'mongodb',
        ];
    }

    public function isPdo(): bool
    {
        return $this !== self::MongoDb;
    }

    public function isMongoDb(): bool
    {
        return $this === self::MongoDb;
    }

    public function pdoDriverName(): ?string
    {
        return match ($this) {
            self::MySql, self::MariaDb => 'mysql',
            self::PgSql => 'pgsql',
            self::SqlServer => 'sqlsrv',
            self::Sqlite => 'sqlite',
            self::MongoDb => null,
        };
    }

    public function buildDsn(
        string $host,
        string $name,
        ?string $path = null,
        ?string $port = null,
        ?string $explicitDsn = null
    ): string
    {
        if ($explicitDsn !== null && trim($explicitDsn) !== '') {
            return $explicitDsn;
        }

        return match ($this) {
            self::MySql, self::MariaDb => self::pdoDsn('mysql', $host, $name, $port) . ';charset=utf8mb4',
            self::PgSql => self::pdoDsn('pgsql', $host, $name, $port),
            self::SqlServer => 'sqlsrv:Server=' . self::hostWithPort($host, $port, ',') . ";Database={$name}",
            self::Sqlite => 'sqlite:' . ($path ?? $name),
            self::MongoDb => self::mongoUri($host, $port),
        };
    }

    private static function pdoDsn(string $scheme, string $host, string $name, ?string $port): string
    {
        $dsn = "{$scheme}:host={$host};dbname={$name}";
        if ($port !== null && trim($port) !== '') {
            $dsn .= ';port=' . trim($port);
        }

        return $dsn;
    }

    private static function hostWithPort(string $host, ?string $port, string $separator): string
    {
        if ($port === null || trim($port) === '') {
            return $host;
        }

        return $host . $separator . trim($port);
    }

    private static function mongoUri(string $host, ?string $port): string
    {
        if (str_starts_with($host, 'mongodb://') || str_starts_with($host, 'mongodb+srv://')) {
            return $host;
        }

        return 'mongodb://' . self::hostWithPort($host, $port, ':');
    }
}
