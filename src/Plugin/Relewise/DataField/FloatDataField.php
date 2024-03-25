<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "float",
 *  label = @Translation("Float"),
 *  field_types = {
 *    "integer",
 *    "float",
 *    "timestamp"
 *  }
 * )
 */
class FloatDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    return DataValueFactory::float($items->first()->getValue()['value']);
  }

}
