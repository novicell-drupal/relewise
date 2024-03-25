<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Relewise\Models\DataValue;

interface DataFieldInterface extends PluginSettingsFormInterface, ContainerFactoryPluginInterface, DependentPluginInterface {

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue;

  /**
   * Returns if the transformer can be used for the provided field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition that should be checked.
   *
   * @return bool
   *   TRUE if the transformer can be used, FALSE otherwise.
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition);

}
