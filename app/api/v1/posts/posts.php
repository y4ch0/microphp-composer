<?php

use MicroPHP\Api;
use App\BlogRepository;

/**
 * GET /api/v1/posts
 * Retrieves a list of all posts.
 */
Api::get("/posts", function($params, $data) {
    return BlogRepository::posts();
});

/**
 * GET /api/v1/posts/:id
 * Retrieves a single post by its ID.
 */
Api::get("/posts/:id", function($params) {
    if (!isset($params['id'])) {
        throw new Exception("Post ID is required.");
    }
    return BlogRepository::post((string) $params['id']);
});

/**
 * POST /api/v1/posts
 * Creates a new post.
 */


Api::post("/posts", function($params, $data) {
    if (empty($data['title']) || empty($data['content'])) {
        throw new Exception("Title and content are required fields.");
    }

    $id = BlogRepository::createPost((string) $data['title'], (string) $data['content']);

    if ($id === false) {
        throw new Exception("Failed to create a new post.");
    }

    return ['id' => $id];
});
