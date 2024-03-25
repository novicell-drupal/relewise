<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\transform_api\Transform\TransformInterface;
use Relewise\Models\Multilingual;

interface DisplayNameProviderInterface extends PluginSettingsFormInterface, DependentPluginInterface {

  public function getMultilingualDisplayName(EntityInterface $entity): ?Multilingual;

  public function getStringDisplayName(EntityInterface $entity): ?string;

}
