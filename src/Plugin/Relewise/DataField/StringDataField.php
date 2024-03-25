<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "string",
 *  label = @Translation("String"),
 *  field_types = {
 *    "string",
 *    "string_long",
 *    "text",
 *    "text_long",
 *    "text_with_summary",
 *    "list_string",
 *    "language",
 *    "telephone",
 *    "email"
 *  }
 * )
 */
class StringDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    return DataValueFactory::string($items->first()->getValue()['value'] ?? '');
  }

}
