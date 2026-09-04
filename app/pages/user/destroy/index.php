<?php

use MicroPHP\View;

if ($request->isMethod('POST')) {
    unset($_SESSION['user']);
    session_regenerate_id(true);
    app(\MicroPHP\Security\Csrf::class)->rotate();
    echo 'User destroyed';
    return;
}
?>
<form method="post" action="/user/destroy">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(View::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <button type="submit">Destroy demo user</button>
</form>
