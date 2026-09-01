<?php
/**
 * Usage in templates: @component("button", ["text" => "Save", "type" => "secondary", "link" => "/save"])
 * Usage in PHP pages: \MicroPHP\View::component('button', [...]);
 */

namespace MicroPHP\Components;

use MicroPHP\Component;

class Button extends Component
{
  /**
   * Create a button component.
   *
   * @param string $text Button label.
   * @param string $link Button destination URL.
   * @param string $type Visual variant, either "primary" or "secondary".
   */
  public function __construct(
    protected string $text = 'Click Me',
    protected string $link = '#',
    protected string $type = 'primary',
  ) {}

  /**
   * Render the button link.
   *
   * @return string Rendered button markup.
   */
  public function render(): string
  {
    $variant = $this->type === 'secondary' ? 'btn-secondary' : 'btn-primary';
    $class = 'btn ' . $variant;

    return sprintf(
      '<a href="%s" class="%s" role="button">%s</a>',
      htmlspecialchars($this->link, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($this->text, ENT_QUOTES, 'UTF-8')
    );
  }
}
