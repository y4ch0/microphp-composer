<?php
/**
 * MicroPHP Framework
 * Fluent query builder so day-to-day database access reads like plain
 * English instead of hand-typed SQL strings. Still goes through
 * Database::getInstance() underneath, so every value is bound as a
 * prepared-statement parameter — building queries this way is no less safe
 * than a raw Database::getInstance()->query() call, just harder to get wrong
 * by accident.
 *
 * Reading (fluent chain, start with Database::table()):
 *
 *   $admins = Database::table('users')
 *       ->select(['id', 'name', 'email'])
 *       ->where(['role' => 'admin', 'active' => 1])
 *       ->orderBy('name')
 *       ->get();
 *
 *   $user = Database::table('users')
 *       ->where(['id' => $id])
 *       ->first();
 *
 *   // Operators other than "=" — pass [operator, value]:
 *   $recent = Database::table('posts')
 *       ->where(['created_at' => ['>=', $since]])
 *       ->get();
 *
 * Writing (one-shot calls, no chain needed):
 *
 *   Database::insert('posts', ['title' => $title, 'content' => $content, 'created_at' => date('c')]);
 *   Database::update('posts', ['title' => $newTitle], ['id' => $id]);
 *   Database::delete('posts', ['id' => $id]);
 */

namespace MicroPHP;

use MicroPHP\Enums\DbDriver;

final class QueryBuilder
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';
    private const SIMPLE_IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    private const OPERATORS = ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    private array $columns = ['*'];

    /** @var array<int,array{boolean:string,sql:string,params:array<string,mixed>}> */
    private array $wheres = [];

    /** @var string[] */
    private array $orderClauses = [];

    /** @var string[] */
    private array $joinClauses = [];

    private ?int $limitCount = null;
    private ?int $offsetCount = null;

    private function __construct(private readonly string $table)
    {
        self::assertIdentifier($table, 'Table name');
    }

    public static function forTable(string $table): self
    {
        return new self($table);
    }

    /**
     * Choose which columns to fetch. Defaults to all columns ("*") when never called.
     *
     * @param string|string[] $columns A column name or list of column names.
     * @param string ...$moreColumns Optional additional column names.
     */
    public function select(string|array $columns, string ...$moreColumns): self
    {
        $selected = is_array($columns) ? array_values($columns) : [$columns];
        foreach (array_merge($selected, $moreColumns) as $column) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException('SELECT columns must be strings.');
            }
            self::assertSelectableColumn($column);
        }

        $this->columns = array_merge($selected, $moreColumns);
        return $this;
    }

    /**
     * Add WHERE conditions, AND-ed together (both across keys in one call,
     * and across repeated calls to this method).
     *
     * @param array<string,mixed> $conditions Column => value for equality, or
     *                                         column => [operator, value] for anything else
     *                                         (e.g. ['age' => ['>=', 18]]).
     */
    public function where(array $conditions): self
    {
        $compiled = self::compileConditions($conditions);
        if ($compiled['sql'] === '') {
            return $this;
        }

        $this->wheres[] = [
            'boolean' => $this->wheres === [] ? '' : 'AND',
            'sql' => $compiled['sql'],
            'params' => $compiled['params'],
        ];

        return $this;
    }

    /**
     * Join another table using a column-to-column condition.
     *
     * @param string $table Table to join.
     * @param string $firstColumn First table.column expression.
     * @param string $operator Comparison operator (normally "=").
     * @param string $secondColumn Second table.column expression.
     * @param string $type Join type: INNER, LEFT, RIGHT, or FULL.
     */
    public function join(
        string $table,
        string $firstColumn,
        string $operator,
        string $secondColumn,
        string $type = 'INNER'
    ): self {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'FULL'], true)) {
            throw new \InvalidArgumentException('Unsupported join type.');
        }

        self::assertIdentifier($table, 'Join table');
        self::assertIdentifier($firstColumn, 'Join column');
        self::assertIdentifier($secondColumn, 'Join column');
        $operator = self::normalizeOperator($operator, 'Join operator');

        $this->joinClauses[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $table,
            $firstColumn,
            $operator,
            $secondColumn
        );

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        self::assertIdentifier($column, 'ORDER BY column');

        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('ORDER BY direction must be ASC or DESC.');
        }

        $this->orderClauses[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $count, int $offset = 0): self
    {
        if ($count < 0 || $offset < 0) {
            throw new \InvalidArgumentException('Limit and offset must be zero or greater.');
        }

        $this->limitCount = $count;
        $this->offsetCount = $offset;
        return $this;
    }

    /** @return array<int,array<string,mixed>> */
    public function get(): array
    {
        [$sql, $params] = $this->buildSelect();
        $result = Database::getInstance()->query($sql, $params);
        return is_array($result) ? $result : [];
    }

    /** Fetch the first matching row, or null when there isn't one. */
    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** Count matching rows without pulling the full result set. */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) AS aggregate'];
        [$sql, $params] = $this->buildSelect(ignoreLimitAndOrder: true);
        $this->columns = $originalColumns;

        $result = Database::getInstance()->query($sql, $params);
        return (int) ($result[0]['aggregate'] ?? 0);
    }

    public function updateAll(array $data): int|false
    {
        return Database::updateAll($this->table, $data);
    }

    public function deleteAll(): int|false
    {
        return Database::deleteAll($this->table);
    }

    /**
     * Compile the SELECT statement without executing it.
     *
     * @return array{sql:string,params:array<string,mixed>}
     */
    public function toSql(?DbDriver $driver = null): array
    {
        [$sql, $params] = $this->buildSelect(driver: $driver);

        return ['sql' => $sql, 'params' => $params];
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildSelect(bool $ignoreLimitAndOrder = false, ?DbDriver $driver = null): array
    {
        $driver ??= Database::configuredDriver();
        $limit = $this->limitCount === null ? null : max(0, $this->limitCount);
        $offset = $this->offsetCount === null ? 0 : max(0, $this->offsetCount);
        $usesSqlServerTop = !$ignoreLimitAndOrder
            && $driver === DbDriver::SqlServer
            && $limit !== null
            && $offset === 0;

        $sql = 'SELECT ';
        if ($usesSqlServerTop) {
            $sql .= 'TOP (' . $limit . ') ';
        }
        $sql .= implode(', ', $this->columns) . ' FROM ' . $this->table;
        $params = [];

        if ($this->joinClauses !== []) {
            $sql .= ' ' . implode(' ', $this->joinClauses);
        }

        if ($this->wheres !== []) {
            $sql .= ' WHERE ';
            foreach ($this->wheres as $i => $where) {
                $sql .= ($i > 0 ? " {$where['boolean']} " : '') . $where['sql'];
                $params += $where['params'];
            }
        }

        if (!$ignoreLimitAndOrder) {
            $hasOrderBy = false;
            if ($this->orderClauses !== []) {
                $sql .= ' ORDER BY ' . implode(', ', $this->orderClauses);
                $hasOrderBy = true;
            }

            if ($limit !== null && $driver === DbDriver::SqlServer) {
                if (!$usesSqlServerTop) {
                    if (!$hasOrderBy) {
                        $sql .= ' ORDER BY (SELECT NULL)';
                    }
                    $sql .= ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $limit . ' ROWS ONLY';
                }
            } elseif ($limit !== null) {
                $sql .= ' LIMIT ' . $limit;
                if ($offset > 0) {
                    $sql .= ' OFFSET ' . $offset;
                }
            }
        }

        return [$sql, $params];
    }

    /**
     * Turn a conditions array into a SQL fragment + bound params. Shared by
     * where() and Database's insert/update/delete methods
     * so there's exactly one place that decides how conditions compile.
     *
     * @param array<string,mixed> $conditions
     * @return array{sql:string,params:array<string,mixed>}
     */
    public static function compileConditions(array $conditions): array
    {
        $parts = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $column = (string) $column;
            self::assertIdentifier($column, 'WHERE column');

            if (is_array($value) && array_key_exists(0, $value) && array_key_exists(1, $value) && is_string($value[0])) {
                [$operator, $boundValue] = $value;
            } else {
                $operator = '=';
                $boundValue = $value;
            }
            $operator = self::normalizeOperator($operator, 'WHERE operator');

            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $column);
            $placeholder = ':cond_' . $safeName . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

            $parts[] = "{$column} {$operator} {$placeholder}";
            $params[$placeholder] = $boundValue;
        }

        return ['sql' => implode(' AND ', $parts), 'params' => $params];
    }

    public static function assertIdentifier(string $identifier, string $label = 'SQL identifier'): void
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException("{$label} contains unsupported characters: {$identifier}");
        }
    }

    public static function assertSimpleIdentifier(string $identifier, string $label = 'SQL identifier'): void
    {
        if (!preg_match(self::SIMPLE_IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException("{$label} contains unsupported characters: {$identifier}");
        }
    }

    private static function assertSelectableColumn(string $column): void
    {
        if ($column === '*') {
            return;
        }

        if (preg_match('/^COUNT\(\*\)\s+AS\s+[A-Za-z_][A-Za-z0-9_]*$/i', $column)) {
            return;
        }

        self::assertIdentifier($column, 'SELECT column');
    }

    private static function normalizeOperator(string $operator, string $label): string
    {
        $operator = strtoupper(trim(preg_replace('/\s+/', ' ', $operator) ?? $operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("{$label} is not supported: {$operator}");
        }

        return $operator;
    }
}
