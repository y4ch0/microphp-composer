<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/gh/y4ch0/Lumo.CSS/1.0.1/dist/lumo.min.js"></script>
    
    <!-- Page metadata will be injected here -->
    <title><?php echo htmlspecialchars($meta['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($meta['description'] ?? 'Welcome!'); ?>">

    <?php if (!empty($meta['icon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($meta['icon']); ?>">
    <?php endif; ?>

    <!-- All stylesheets (global and scoped) will be injected here -->
    <?php echo $styles ?? ''; ?>

    <!-- Scoped page scripts will be injected here -->
    <?php echo $scripts ?? ''; ?>
</head>