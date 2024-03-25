<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "timestamp",
 *  label = @Translation("Timestamp"),
 *  field_types = {
 *    "date",
 *    "datetime"
 *  }
 * )
 */
class TimestampDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    $dateformat = new \Drupal\Core\Datetime\DrupalDateTime($items
      ->first()
      ->getValue()['value']);
    return DataValueFactory::float($dateformat->getTimestamp());
  }

}
