<?php
$links = [
    "/admin" => "Index",
    "/admin/users" => "Users",
    "/admin/users/1" => "Check user with id 1",
    "/" => "Back to main site"
];

ob_start();
\MicroPHP\View::component('header');
$header = ob_get_clean();

ob_start();
\MicroPHP\View::component('footer');
$footer = ob_get_clean();

foreach (\MicroPHP\Component::styles() as $componentCssPath) {
    if (strpos($styles, 'href="' . $componentCssPath . '"') === false) {
        $styles .= '<link rel="stylesheet" href="' . $componentCssPath . '">' . "\n    ";
    }
}

foreach (\MicroPHP\Component::scripts() as $componentJsPath) {
    if (strpos($scripts, 'src="' . $componentJsPath . '"') === false) {
        $scripts .= '<script src="' . $componentJsPath . '" defer></script>' . "\n    ";
    }
}

require_once("_root.php");
?>
<body>

    <?php echo $header; ?>

    <main>
        
        <div class="container">
            <aside id="navigation">
                <nav>
                    <ul>
                        <?php foreach($links as $link => $label): ?>
                        <li <?php if($link == current_path()) {echo "aria-current='true'";} ?>><a href="<?php echo $link ?>"><?php echo $label ?></a>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </aside>
            <div id="page"><?php echo $content ?? ''; ?></div>
        </div> <?php display_messages(); // Display any success or error messages. ?>
        
        <!-- Page-specific content will be injected here. -->
        
       
    </main>

    <?php echo $footer; ?>
</body>
</html>
