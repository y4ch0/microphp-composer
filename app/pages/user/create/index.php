<?php

use MicroPHP\View;

if ($request->isMethod('POST')) {
    $_SESSION['user'] = ['id' => 6772356, 'name' => 'admin', 'role' => 'Admin'];
    echo 'User created';
    return;
}
?>
<form method="post" action="/user/create">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(View::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <button type="submit">Create demo user</button>
</form>
