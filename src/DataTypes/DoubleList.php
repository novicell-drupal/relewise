<?php

namespace Drupal\relewise\DataTypes;

class DoubleList extends DataType {

  /**
   * @var float[]
   */
  protected array $values = [];

  public function __construct(array $values) {
    $this->values = $values;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'DoubleList';
  }

  /**
   * @param float $value
   */
  public function addValue(float $value) {
    $this->values[] = floatval($value);
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      '$values' => $this->values
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFullTypeId(): string {
    return 'System.Collections.Generic.List`1[[System.Double, System.Private.CoreLib]], System.Private.CoreLib';
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
