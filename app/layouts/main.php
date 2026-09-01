<?php
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

    <main class="container">
        <?php display_messages(); ?>
        
        <?php echo $content ?? ''; ?>
    </main>

    <?php echo $footer; ?>
</body>
</html>
