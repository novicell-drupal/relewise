<?php

namespace Drupal\relewise\Plugin\Relewise\CategoryPath;

use Drupal\Core\Entity\EntityInterface;
use Drupal\relewise\CategoryPathProviderBase;
use Relewise\Models\CategoryNameAndId;
use Relewise\Models\CategoryPath;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;

/**
 * @CategoryPathProvider(
 *  id = "entity_type",
 *  title = "Entity type",
 * )
 */
class EntityType extends CategoryPathProviderBase {

  public function getCategoryPaths(EntityInterface $entity): ?CategoryPath {
    $categoryPath = CategoryPath::create();
    $categories = [];
    $categories[$entity->getEntityTypeId()] = $entity->getEntityType()->getLabel();

    foreach ($categories as $id => $label) {
      $categoryPath->addToBreadcrumbPathStartingFromRoot(
        CategoryNameAndId::create(
          $id,
          Multilingual::create(
            MultilingualValue::create(
              $this->relewise->languageFromEntity($entity),
              $label
            )
          )
        )
      );
    }
    return $categoryPath;
  }

}
