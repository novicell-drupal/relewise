<?php

namespace Drupal\relewise\DataTypes;

class MultiCurrency extends DataType {

  protected array $values = [];

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'MultiCurrency';
  }

  /**
   * @param float $amount
   * @param string $currency
   */
  public function addCurrency(float $amount, string $currency) {
    $this->values[$currency] = $amount;
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    $result = [];
    foreach ($this->values as $currency => $amount) {
      $result[] = [
        "Amount" => $amount,
        "Currency" => [
          "Value" => $currency,
        ]
      ];
    }
    return [
      'Values' => $result
    ];
  }

}
