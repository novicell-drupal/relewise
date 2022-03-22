<?php

namespace Drupal\relewise\DataTypes;

class MultilingualCollection extends DataType {

  protected array $values = [];

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'MultilingualCollection';
  }

  /**
   * @param string $text
   * @param null $langcode
   */
  public function addValue(string $text, $langcode = NULL) {
    if ($langcode == NULL) {
      $langcode = \Drupal::languageManager()->getDefaultLanguage();
    }
    if (!isset($this->values[$langcode])) {
      $this->values[$langcode] = [];
    }
    $this->values[$langcode][] = $text;
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    $result = [];
    foreach ($this->values as $langcode => $value) {
      $result[] = [
        'Language' => ['Value' => $langcode],
        'Values' => $value
      ];
    }
    return [
      'Values' => $result
    ];
  }

}
