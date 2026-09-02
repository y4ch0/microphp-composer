<h2>Echo article ID from URL</h2>
<h1><?php echo htmlspecialchars((string) $request->route('articleId'), ENT_QUOTES, 'UTF-8'); ?></h1>

<h2>Component Props Example</h2>

<p>Here are some buttons created with the same component but different props:</p>

<?php
    // A standard primary button.
    $view->renderComponent('button', [
        'text' => 'Learn More',
        'link' => '/about'
    ]);

    // A secondary button with a different type.
    $view->renderComponent('button', [
        'text' => 'Contact Us',
        'link' => '/contact',
        'type' => 'secondary'
    ]);

    // A button with no props, using its defaults.
    $view->renderComponent('button');
    ?>

<h2>Interactive Data Table Component</h2>

<?php
// Example data for the table.
$userData = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
];

// Render the component and pass the data and a custom button text as props.
$view->renderComponent('data-table', [
    'data' => $userData,
    'buttonText' => 'Show User List'
]);
?>
