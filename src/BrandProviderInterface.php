<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Relewise\Models\Brand;

interface BrandProviderInterface extends PluginSettingsFormInterface, DependentPluginInterface {

  public function getBrand(EntityInterface $entity): ?Brand;

}
