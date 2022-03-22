<?php

namespace Drupal\relewise\DataTypes\Search\Sorting\Content;

class ContentPopularitySorting extends ContentSorting {

  protected $order = "Descending";

  public function __construct($order = "Descending") {
    $this->order = $order;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentPopularitySorting';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Order' => $this->order
    ];
  }

}
