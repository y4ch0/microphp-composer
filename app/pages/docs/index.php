<?php

require __DIR__ . '/_content.php';

$layout = 'main';
$page = microphp_docs_pages()['introduction'];
$meta['title'] = 'MicroPHP Documentation';
$meta['description'] = $page['description'];

microphp_docs_render_page('introduction');

