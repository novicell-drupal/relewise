<?php

namespace Drupal\relewise\DataTypes\Search\Facets\Queries;

use Drupal\relewise\DataTypes\DataType;

abstract class QueryDataType extends DataType {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.DataTypes.Search.Facets.Queries';
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
