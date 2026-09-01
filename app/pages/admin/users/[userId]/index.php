<?php
// This page uses the admin layout
$layout = 'admin';

$userId = $request->route('userId');

?>

<h2>Manage User</h2>
<p>This route was matched by the file at <code>/Pages/admin/users/[userId]/</code>.</p>

<p>Also! This page has scoped styling. You can do the same thing with JS code.</p>

<p><strong>User ID:</strong> <?php echo $userId; ?></p>
