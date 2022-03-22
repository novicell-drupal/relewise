<?php

namespace Drupal\relewise\DataTypes;

class Multilingual extends DataType {

  protected array $values = [];

  public function __construct($text = NULL, $langcode = NULL) {
    if (!is_null($text)) {
      $this->addValue($text, $langcode);
    }
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'Multilingual';
  }

  /**
   * @param string $text
   * @param null $langcode
   */
  public function addValue(string $text, $langcode = NULL) {
    if ($langcode == NULL) {
      $langcode = \Drupal::languageManager()->getDefaultLanguage();
    }
    $this->values[$langcode] = $text;
  }

  public function toArray(): array {
    $result = [];
    foreach ($this->values as $langcode => $value) {
      $result[] = [
        'Language' => ['Value' => $langcode],
        'Text' => $value
      ];
    }
    return [
      'Values' => $result
    ];
  }

}
