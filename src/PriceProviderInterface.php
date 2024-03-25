<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Relewise\Models\MultiCurrency;

interface PriceProviderInterface extends PluginSettingsFormInterface, DependentPluginInterface {

  public function getPrice(EntityInterface $entity): ?MultiCurrency;

}
