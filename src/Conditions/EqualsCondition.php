<?php

namespace Drupal\relewise\Conditions;

use Drupal\relewise\Value;

class EqualsCondition extends Condition {

  protected Value $value;

  protected bool $negated = FALSE;

  public function __construct(Value $value, $negated = FALSE) {
    $this->value = $value;
    $this->negated = $negated;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'EqualsCondition';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Value' => $this->value->toArray(),
      'Negated' => $this->negated
    ];
  }

}
