<?php

use MicroPHP\Api;
use App\BlogRepository;

/**
 * GET /api/v1/posts/:post_id/comments
 * Retrieves all comments for a given post.
 */
Api::get("/posts/:post_id/comments", function($params) {
    if (!isset($params['post_id'])) {
        throw new Exception("Post ID is required to retrieve comments.");
    }
    return BlogRepository::commentsForPost((string) $params['post_id']);
}, ['allowed_origins' => ['*']]);

/**
 * GET /api/v1/posts/:post_id/comments/:comment_id
 * Retrieves a single comment by its ID for a given post.
 */
Api::get("/posts/:post_id/comments/:comment_id", function($params) {
    if (!isset($params['post_id']) || !isset($params['comment_id'])) {
        throw new Exception("Post and comment IDs are required.");
    }
    return BlogRepository::comment((string) $params['post_id'], (string) $params['comment_id']);
});

/**
 * POST /api/v1/posts/:post_id/comments
 * Adds a new comment to a post.
 */
Api::post("/posts/:post_id/comments", function($params, $data) {
    if (!isset($params['post_id'])) {
        throw new Exception("Post ID is required to add a comment.");
    }
    if (empty($data['author']) || empty($data['content'])) {
        throw new Exception("Author and content are required fields.");
    }
    $id = BlogRepository::createComment((string) $params['post_id'], (string) $data['author'], (string) $data['content']);

    if ($id === false) {
        throw new Exception("Failed to add a new comment.");
    }

    return ['id' => $id, 'post_id' => $params['post_id'], 'author' => $data['author']];
});
