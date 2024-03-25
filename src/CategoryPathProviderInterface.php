<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Relewise\Models\CategoryPath;

interface CategoryPathProviderInterface extends PluginSettingsFormInterface, DependentPluginInterface {

  public function getCategoryPaths(EntityInterface $entity): ?CategoryPath;

}
