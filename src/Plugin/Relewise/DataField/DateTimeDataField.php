<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "datetime",
 *  label = @Translation("Datetime"),
 *  field_types = {
 *    "date",
 *    "datetime"
 *  }
 * )
 */
class DateTimeDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    $dateformat = new \Drupal\Core\Datetime\DrupalDateTime($items
      ->first()
      ->getValue()['value']);
    return DataValueFactory::string($dateformat->format('html_date'));
  }

}
