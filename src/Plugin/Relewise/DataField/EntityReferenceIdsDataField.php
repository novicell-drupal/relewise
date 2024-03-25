<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;

/**
 * @DataField(
 *  id = "entity_reference_ids",
 *  label = @Translation("Entity Reference IDs"),
 *  field_types = {
 *    "entity_reference"
 *  }
 * )
 */
class EntityReferenceIdsDataField extends DataFieldBase {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      $values = [];
      if (!$items->isEmpty()) {
        if ($items instanceof EntityReferenceFieldItemListInterface) {
          $entities = $items->referencedEntities();
          foreach ($entities as $entity) {
            $values[] = $entity->id();
          }
        }
      }
      return DataValueFactory::stringListFromArray($values);
    } else {
      if ($items->isEmpty() || empty($items->first()) || empty($items->first()
          ->getValue())) {
        return NULL;
      }
      else {
        return DataValueFactory::string($items->first()
          ->getValue()['target_id']);
      }

    }
  }

}
