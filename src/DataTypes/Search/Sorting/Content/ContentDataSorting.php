<?php

namespace Drupal\relewise\DataTypes\Search\Sorting\Content;

class ContentDataSorting extends ContentSorting {

  protected string $key;

  protected string $order = "Descending";

  public function __construct($key, $order = "Descending") {
    $this->key = $key;
    $this->order = $order;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentDataSorting';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Key' => $this->key,
      'Order' => $this->order
    ];
  }

}
