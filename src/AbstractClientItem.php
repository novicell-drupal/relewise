<?php

namespace Drupal\relewise;

abstract class AbstractClientItem implements ClientItem {

  /**
   * {@inheritdoc}
   */
  public function getFullTypeId(): string {
    return $this->getNamespace() . '.' . $this->getTypeName() . ', Relewise.Client';
  }

  /**
   * {@inheritdoc}
   */
  public function toItemArray(): array {
    return [
      '$type' => $this->getFullTypeId(),
      'TypeName' => $this->getTypeName()
    ] + $this->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function toRootArray(): array {
    return [
      $this->getTypeName() => $this->toArray() + ['TypeName' => $this->getTypeName()]
    ];
  }

  /**
   * @param ClientItem[] $clientItems
   */
  protected function getItemsArray(array $clientItems): array {
    $items = [];
    foreach ($clientItems as $item) {
      $items[] = $item->toItemArray();
    }
    return [
      'Items' => $items
    ];
  }
}
