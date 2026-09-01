<?php
/**
 * Page displaying a list of posts.
 * Data is loaded in two ways:
 * 1. The post list is loaded in PHP on the server side through Database.
 * 2. Comments are loaded in JavaScript on the client side through fetch().
 */

use App\BlogRepository;
use MicroPHP\Database;

$meta['title'] = 'Lista postów';
$posts = [];
$error = null;

// Load data on the server side with the canonical database API.
try {
    $posts = BlogRepository::posts();
} catch (Exception $e) {
    $error = $e->getMessage();
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
    if(isset($_POST["title"]) && isset($_POST["content"]) && strlen($_POST["title"]) && strlen($_POST["content"])) {
        try {
            $id = BlogRepository::createPost((string) $_POST["title"], (string) $_POST["content"]);

            if ($id === false) {
                throw new Exception(Database::getInstance()->getError() ?? 'Unable to create post.');
            }

            redirect("/posts");
        } catch (Exception $e) {
            echo "Błąd: ".$e;
        }
    }
}

?>

<h1>Wpisy na blogu</h1>
<p>Poniższa lista postów została pobrana po stronie serwera (PHP). Komentarze są doładowywane dynamicznie (JavaScript) po kliknięciu przycisku.</p>

<form action="/posts" method="post">
    <h4>Tworzenie nowego postu</h4>
    <label>Tytuł<input type="text" name="title" required></label>
    <label>Treść<textarea name="content" placeholder="Lorem ipsum dolor..." required></textarea></label>
    <button type="submit">Dodaj post</button>
</form>

<hr>

<?php if ($error): ?>
    <div class="error-box">
        <strong>Wystąpił błąd podczas pobierania danych:</strong>
        <p><?= htmlspecialchars($error) ?></p>
    </div>
<?php elseif (empty($posts)): ?>
    <p>Brak postów do wyświetlenia.</p>
<?php else: ?>
    <div class="posts-container">
        <?php foreach ($posts as $post): ?>
            <article class="post">
                <h2><?= htmlspecialchars($post['title']) ?></h2>
                <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                <small>Opublikowano: <?= date('d.m.Y', strtotime($post['created_at'])) ?></small>
                <small><?= $post["comments_count"] ?> komentarzy</small>
                <div class="comments-section">
                    <button class="load-comments-btn" data-post-id="<?= $post['id'] ?>">
                        Pokaż komentarze
                    </button>
                    <div class="comments-container" id="comments-for-<?= $post['id'] ?>">
                        <!-- Comments will be inserted here by JavaScript. -->
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
