<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class Header extends Component
{
  /**
   * Render the site header.
   *
   * @return string Rendered header markup.
   */
  public function render(): string
  {
    $logoUrl = htmlspecialchars(rtrim(APP_ASSETS_URL, '/') . '/img/microphp_logo.svg', ENT_QUOTES, 'UTF-8');

    ob_start();
    ?>
<header>
    <div class="container">
        <a href="/"><img src="<?php echo $logoUrl; ?>" alt="Logo of MicroPHP framework" height="55" id="page-logo"></a>
        <nav>
            <ul>
                <button role="menu" aria-label="close"></button>
                <li><a href="/">Home</a></li>
                <li><a href="/article/101">Sample Article</a></li>
                <li><a href="/admin">Admin panel</a></li>
                <li><a href="/library">Library</a></li>
                <li><a href="/about">About</a></li>
            </ul>
            <?php echo $this->component('theme-change'); ?>
            <button role="menu"></button>
        </nav>
    </div>
</header>
    <?php
    return ob_get_clean();
  }
}
