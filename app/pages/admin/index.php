<?php
// We only need to define the part of the title we want to add.
// The "Admin Panel" part will be inherited from the parent _meta.php file.
$layout = "admin";
$meta = [
    'title' => 'Test'
]
?>

<h2>Admin Dashboard</h2>
<p>Welcome to the admin dashboard.</p>
<p>This page automatically uses the "admin" layout and inherits its base metadata from the files in the <code>/Pages/admin/</code> directory.</p>

<p>Here is string of path with domain: <code><?php echo current_url(); ?></code></p>

<p>However you can just check current route without domain: <code><?php echo current_path(); ?></code></p>