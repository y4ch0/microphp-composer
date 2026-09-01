<?php

namespace MicroPHP\Components;

use MicroPHP\Component;

class DataTable extends Component
{
  /**
   * Create a data table component.
   *
   * @param array<int,array<string,mixed>> $data Rows displayed when the user opens the table.
   * @param string $buttonText Label for the toggle button.
   */
  public function __construct(
    protected array $data = [],
    protected string $buttonText = 'Show Data',
  ) {}

  /**
   * Render the data table shell.
   *
   * @return string Rendered component markup.
   */
  public function render(): string
  {
    $instanceId = 'dt_' . bin2hex(random_bytes(4));
    $json = json_encode(
      $this->data,
      JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
    $encodedData = htmlspecialchars($json ?: '[]', ENT_QUOTES, 'UTF-8');
    $buttonText = htmlspecialchars($this->buttonText, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div class="data-table-component" id="{$instanceId}" data-component-data="{$encodedData}" data-button-text="{$buttonText}">
    <button class="show-data-btn">{$buttonText}</button>
    <div class="table-container" style="margin-top: 10px;"></div>
</div>
HTML;
  }
}
