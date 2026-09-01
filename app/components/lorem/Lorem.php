<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class Lorem extends Component
{
  /**
   * Create the lorem demo component.
   *
   * @param string $title Text displayed inside the component.
   */
  public function __construct(
    protected string $title = '',
  ) {}

  /**
   * Render the lorem demo component.
   *
   * @return string Rendered component markup.
   */
  public function render(): string
  {
    return $this->view('view.micro.php', ['title' => $this->title]);
  }
}
