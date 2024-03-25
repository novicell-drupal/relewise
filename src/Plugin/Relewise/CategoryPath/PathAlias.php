<?php

namespace Drupal\relewise\Plugin\Relewise\CategoryPath;

use Drupal\Core\Entity\EntityInterface;
use Drupal\relewise\Annotation\CategoryPathProvider;
use Drupal\relewise\CategoryPathProviderBase;
use Relewise\Models\CategoryNameAndId;
use Relewise\Models\CategoryPath;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;

/**
 * @CategoryPathProvider(
 *  id = "path_alias",
 *  title = "Path alias",
 * )
 */
class PathAlias extends CategoryPathProviderBase {

  public function getCategoryPaths(EntityInterface $entity): ?CategoryPath {
    $categoryPath = CategoryPath::create();
    $categories = [];
    /** @var \Drupal\path_alias\AliasManagerInterface $aliasManager */
    $aliasManager = \Drupal::service('path_alias.manager');
    $alias = $aliasManager->getAliasByPath($entity->toUrl()->toString());
    $paths = explode('/', $alias);
    foreach ($paths as $path) {
      if (!empty($path)) {
        $categories[$path] = $path;
      }
    }
    unset($categories[array_key_last($categories)]);

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
