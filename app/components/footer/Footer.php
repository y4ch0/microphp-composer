<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class Footer extends Component
{
  /**
   * Render the site footer.
   *
   * @return string Rendered footer markup.
   */
  public function render(): string
  {
    $year = htmlspecialchars((string) date('Y'), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<footer>
    <div class="container">
        <p>&copy; {$year} Your Website. Powered by MicroPHP.</p>
    </div>
</footer>
HTML;
  }
}
