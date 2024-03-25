<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "boolean",
 *  label = @Translation("Boolean"),
 *  field_types = {
 *    "boolean"
 *  }
 * )
 */
class BooleanDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    return DataValueFactory::string($items->first()->getValue()['value']);
  }

}
