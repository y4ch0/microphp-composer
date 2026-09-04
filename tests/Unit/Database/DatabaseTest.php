<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use LogicException;
use MicroPHP\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'microphp-db-');
        $pdo = new PDO('sqlite:' . $this->file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        Database::usePdo($pdo);
    }

    protected function tearDown(): void { @unlink($this->file); }

    public function testBoundWritesProtectedMassOperationsAndExplicitFullTableOperations(): void
    {
        $id = Database::insert('users', ['name' => "O'Reilly"]);
        self::assertSame("O'Reilly", Database::table('users')->where(['id' => $id])->first()['name']);
        $this->expectException(\InvalidArgumentException::class);
        Database::update('users', ['name' => 'x'], []);
    }

    public function testExplicitFullTableOperations(): void
    {
        Database::insert('users', ['name' => 'one']);
        Database::insert('users', ['name' => 'two']);
        self::assertSame(2, Database::table('users')->updateAll(['name' => 'all']));
        self::assertSame(2, Database::table('users')->deleteAll());
    }

    public function testTransactionCommitRollbackAndNestedRejection(): void
    {
        Database::transaction(fn () => Database::insert('users', ['name' => 'committed']));
        self::assertSame(1, Database::table('users')->count());
        try {
            Database::transaction(function (): void {
                Database::insert('users', ['name' => 'rolled back']);
                throw new RuntimeException('original');
            });
            self::fail('Expected callback exception.');
        } catch (RuntimeException $e) {
            self::assertSame('original', $e->getMessage());
        }
        self::assertSame(1, Database::table('users')->count());

        $this->expectException(LogicException::class);
        Database::transaction(fn () => Database::transaction(fn () => null));
    }

    public function testSafeDefaultsAndRelativeSqlitePath(): void
    {
        self::assertFalse(DB_PERSISTENT);
        self::assertSame(ROOT_PATH . '/database/test.sqlite', Database::resolveSqlitePath('database/test.sqlite'));
        self::assertSame(':memory:', Database::resolveSqlitePath(':memory:'));
    }

    public function testSelectSupportsSafeQualifiedColumnAliases(): void
    {
        Database::insert('users', ['name' => 'Ada']);

        $row = Database::table('users')
            ->select(['users.id AS user_id', 'users.name AS display_name'])
            ->first();

        self::assertSame(1, $row['user_id']);
        self::assertSame('Ada', $row['display_name']);
    }

    public function testSelectRejectsUnsafeAliasExpressions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SELECT column');

        Database::table('users')->select('users.id AS id; DROP TABLE users')->toSql();
    }
}
