<?php

require dirname(__DIR__) . '/_content.php';

$layout = 'main';
$slug = $request->route('page', '');

if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    $slug = '';
}

$pages = microphp_docs_pages();

if (isset($pages[$slug])) {
    $meta['title'] = $pages[$slug]['title'] . ' - MicroPHP Documentation';
    $meta['description'] = $pages[$slug]['description'];
} else {
    $meta['title'] = 'Documentation Page Not Found - MicroPHP';
    $meta['description'] = 'The requested MicroPHP documentation page does not exist.';
}

microphp_docs_render_page($slug);
