<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class ThemeChange extends Component
{
  /**
   * Render the theme toggle button.
   *
   * @return string Rendered component markup.
   */
  public function render(): string
  {
    $assetsUrl = rtrim(APP_ASSETS_URL, '/');
    $lightLogo = htmlspecialchars($assetsUrl . '/img/microphp_logo.svg', ENT_QUOTES, 'UTF-8');
    $darkLogo = htmlspecialchars($assetsUrl . '/img/microphp_logo_darkmode.svg', ENT_QUOTES, 'UTF-8');

    return '<button id="theme-change" data-logo-light="' . $lightLogo . '" data-logo-dark="' . $darkLogo . '"></button>';
  }
}
