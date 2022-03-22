<?php

namespace Drupal\relewise\DataTypes\Search\Facets\Queries;

class ContentDataDoubleValueFacet extends QueryDataType {

  protected string $key;

  protected string $field;

  protected array $selected = [];

  public function __construct($key, $field, array $selected) {
    $this->key = $key;
    $this->field = $field;
    $this->selected = $selected;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentDataDoubleValueFacet';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Key' => $this->key,
      'Selected' => $this->selected,
      'Field' => $this->field
    ];
  }

}
