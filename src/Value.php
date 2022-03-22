<?php

namespace Drupal\relewise;

use Drupal\relewise\DataTypes\BooleanList;
use Drupal\relewise\DataTypes\DoubleList;
use Drupal\relewise\DataTypes\StringList;

class Value {

  /**
   * @var mixed
   */
  protected $value = '';

  protected $listType = '';

  public function __construct($value, $listType = 'String') {
    $this->value = $value;
    $this->listType = $listType;
  }

  /**
   * @return mixed
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * @param mixed $value
   */
  public function setValue($value): void {
    $this->value = $value;
  }

  /**
   * @return array|null
   */
  public function toArray(): ?array {
    $result = NULL;
    if (is_string($this->value)) {
      $result = [
        'Type' => 'String',
        'Value' => $this->value
      ];
    } elseif (is_int($this->value)) {
      $result = [
        'Type' => 'Double',
        'Value' => $this->value
      ];
    } elseif (is_float($this->value)) {
      $result = [
        'Type' => 'Double',
        'Value' => $this->value
      ];
    } elseif (is_bool($this->value)) {
      $result = [
        'Type' => 'Boolean',
        'Value' => $this->value
      ];
    } elseif (is_array($this->value)) {
      switch($this->listType) {
        case 'Boolean':
          $list = new BooleanList($this->value);
          break;
        case 'Double':
          $list = new DoubleList($this->value);
          break;
        default:
          $list = new StringList($this->value);
      }
      $result = [
        'Type' => $list->getTypeName(),
        'Value' => $list->toItemArray()
      ];
    } elseif (is_object($this->value)) {
      /** @var \Drupal\relewise\ClientItem $item */
      $item = $this->value;
      $result = [
        'Type' => $item->getTypeName(),
        'Value' => $item->toItemArray()
      ];
    }
    return $result;
  }

}
