<?php

namespace Drupal\relewise\DataTypes;

class StringList extends DataType {

  /**
   * @var string[]
   */
  protected array $values = [];

  public function __construct(array $values) {
    $this->values = $values;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'StringList';
  }

  /**
   * @param string $value
   */
  public function addValue(string $value) {
    $this->values[] = $value;
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
    return 'System.Collections.Generic.List`1[[System.String, System.Private.CoreLib]], System.Private.CoreLib';
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
