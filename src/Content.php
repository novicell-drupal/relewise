<?php

namespace Drupal\relewise;

use Drupal\relewise\DataTypes\Multilingual;
use PhpParser\Node\Expr\AssignOp\Mul;

class Content {

  /**
   * @var string
   */
  protected string $id;

  /**
   * @var \Drupal\relewise\DataTypes\Multilingual
   */
  protected Multilingual $displayName;

  protected array $data = [];

  protected array $categoryPaths = [];

  public function __construct($id) {
    $this->id = $id;
    $this->displayName = new Multilingual();
  }

  public function DisplayName() {
    return $this->displayName;
  }

  public function addData($key, $value) {
    $this->data[$key] = $value;
  }

  public function addPath($id, $path, $langcode = NULL) {
    if (!isset($this->categoryPaths[$id])) {
      $this->categoryPaths[$id] = [];
    }
    $categories = explode('/', $path);
    $n = 1;
    foreach ($categories as $category) {
      if (!empty($category)) {
        if (empty($this->categoryPaths[$id][$n])) {
          $this->categoryPaths[$id][$n] = new Multilingual();
        }
        $this->categoryPaths[$id][$n]->addValue($category, $langcode);
        $n++;
      }
    }
  }

  /**
   * @return array
   */
  public function toArray(): array {
    return [
      'Id' => $this->id,
      'DisplayName' => $this->displayName->toArray(),
      'CategoryPaths' => $this->arrayToCategoryPaths($this->categoryPaths),
      'Data' => $this->arrayToValues($this->data)
    ];
  }

  /**
   * @param array $array
   *
   * @return array
   */
  protected function arrayToCategoryPaths(array $array): array {
    $result = [];
    foreach ($array as $id => $levels) {
      $categoryPath = [
        'BreadcrumbPathStartingFromRoot' => []
      ];
      /** @var Multilingual $value */
      foreach ($levels as $level => $value) {
        $categoryPath['BreadcrumbPathStartingFromRoot'][] = [
          'Id' => $id . '_' . $level,
          'DisplayName' => $value->toArray()
        ];
      }
      $result[] = $categoryPath;
    }
    return $result;
  }

  protected function arrayToValues(array $array) {
    $result = [];
    foreach ($array as $key => $value) {
      if (is_string($value)) {
        $result[$key] = [
          'Type' => 'String',
          'Value' => $value
        ];
      } elseif (is_int($value)) {
        $result[$key] = [
          'Type' => 'Double',
          'Value' => $value
        ];
      } elseif (is_float($value)) {
        $result[$key] = [
          'Type' => 'Double',
          'Value' => $value
        ];
      } elseif (is_bool($value)) {
        $result[$key] = [
          'Type' => 'Boolean',
          'Value' => $value
        ];
      } elseif (is_object($value)) {
        /** @var \Drupal\relewise\ClientItem $item */
        $item = $value;
        $result[$key] = [
          'Type' => $item->getTypeName(),
          'Value' => $item->toItemArray()
        ];
      }
    }
    return $result;
  }

}
