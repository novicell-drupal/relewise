<?php

namespace Drupal\relewise\DataTypes\Search\Sorting\Content;

class ContentAttributeSorting extends ContentSorting {

  protected string $attribute;

  protected string $order = "Descending";

  public function __construct($attribute, $order = "Descending") {
    $this->attribute = $attribute;
    $this->order = $order;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentAttributeSorting';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Attribute' => $this->attribute,
      'Order' => $this->order
    ];
  }

}
