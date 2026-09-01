<?php
/**
 * Blog data access used by the demo pages and API routes.
 */

namespace App;

use MicroPHP\Database;

final class BlogRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function posts(): array
    {
        if (self::usesMongoDb()) {
            return array_map(
                static fn (array $post): array => self::normalizeMongoPost($post),
                Database::collection('posts')->find([], ['sort' => ['created_at' => -1]])
            );
        }

        $rows = Database::getInstance()->query(
            'SELECT *, (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS comments_count FROM posts ORDER BY created_at DESC'
        );

        if ($rows === false) {
            throw new \RuntimeException(Database::getInstance()->getError() ?? 'Unable to load posts.');
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public static function post(string $id): array
    {
        if (self::usesMongoDb()) {
            $post = Database::collection('posts')->first(self::mongoIdFilter($id));

            return $post === null ? [] : self::normalizeMongoPost($post);
        }

        return Database::table('posts')->where(['id' => $id])->first() ?? [];
    }

    public static function createPost(string $title, string $content): int|string|false
    {
        $data = [
            'title' => $title,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (self::usesMongoDb()) {
            return Database::collection('posts')->insert($data);
        }

        return Database::insert('posts', $data);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function commentsForPost(string $postId): array
    {
        if (self::usesMongoDb()) {
            return array_map(
                static fn (array $comment): array => self::normalizeMongoComment($comment, $postId),
                Database::collection('comments')->find(
                    ['post_id' => $postId],
                    ['sort' => ['created_at' => -1]]
                )
            );
        }

        return Database::table('comments')
            ->where(['post_id' => $postId])
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * @return array<string,mixed>
     */
    public static function comment(string $postId, string $commentId): array
    {
        if (self::usesMongoDb()) {
            $filter = self::mongoIdFilter($commentId);
            $filter['post_id'] = $postId;
            $comment = Database::collection('comments')->first($filter);

            return $comment === null ? [] : self::normalizeMongoComment($comment, $postId);
        }

        return Database::table('comments')
            ->where([
                'id' => $commentId,
                'post_id' => $postId,
            ])
            ->first() ?? [];
    }

    public static function createComment(string $postId, string $author, string $content): int|string|false
    {
        $data = [
            'post_id' => $postId,
            'author' => $author,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (self::usesMongoDb()) {
            return Database::collection('comments')->insert($data);
        }

        return Database::insert('comments', $data);
    }

    private static function usesMongoDb(): bool
    {
        return Database::configuredDriver()->isMongoDb();
    }

    /**
     * @return array<string,mixed>
     */
    private static function mongoIdFilter(string $id): array
    {
        if (class_exists('\MongoDB\BSON\ObjectId') && preg_match('/^[a-f0-9]{24}$/i', $id)) {
            return ['_id' => new \MongoDB\BSON\ObjectId($id)];
        }

        return ['_id' => $id];
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function normalizeMongoPost(array $post): array
    {
        $post['id'] = (string) ($post['_id'] ?? $post['id'] ?? '');
        $post['comments_count'] = count(Database::collection('comments')->find(
            ['post_id' => $post['id']],
            ['projection' => ['_id' => 1]]
        ));

        return $post;
    }

    /**
     * @param array<string,mixed> $comment
     * @return array<string,mixed>
     */
    private static function normalizeMongoComment(array $comment, string $postId): array
    {
        $comment['id'] = (string) ($comment['_id'] ?? $comment['id'] ?? '');
        $comment['post_id'] = (string) ($comment['post_id'] ?? $postId);

        return $comment;
    }
}
