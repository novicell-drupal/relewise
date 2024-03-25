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
 *  id = "bundle",
 *  title = "Bundle",
 * )
 */
class Bundle extends CategoryPathProviderBase {

  public function getCategoryPaths(EntityInterface $entity): ?CategoryPath {
    $categoryPath = CategoryPath::create();
    $categories = [];
    $bundle_type = $entity->getEntityType()->getBundleEntityType();
    $bundle = \Drupal::entityTypeManager()->getStorage($bundle_type)->load($entity->bundle());
    $categories[$entity->bundle()] = $bundle->label();

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
