<?php
ob_start();
$view->renderComponent('header');
$header = ob_get_clean();

ob_start();
$view->renderComponent('footer');
$footer = ob_get_clean();

$styles = $assets->stylesHtml();
$scripts = $assets->scriptsHtml();

require __DIR__ . "/_root.php";
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
