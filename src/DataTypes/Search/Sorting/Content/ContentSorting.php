<?php

namespace Drupal\relewise\DataTypes\Search\Sorting\Content;

use Drupal\relewise\DataTypes\DataType;

abstract class ContentSorting extends DataType {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.DataTypes.Search.Sorting.Content';
  }

  /**
   * {@inheritdoc}
   */
  public function toItemArray(): array {
    return [
        '$type' => $this->getFullTypeId(),
      ] + $this->toArray();
  }
}
