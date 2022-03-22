<?php

namespace Drupal\relewise\DataTypes;

class Money extends DataType {

  protected float $amount;

  protected string $currency;

  public function __construct(float $amount, string $currency) {
    $this->amount = $amount;
    $this->currency = $currency;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'Money';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      "Amount" => $this->amount,
      "Currency" => [
        "Value" => $this->currency,
      ]
    ];
  }

}
