<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\relewise\PluginSettingsInterface;

/**
 * Base class for the Relewise plugins that is based on entity type bundle information.
 */
abstract class BundleBasedPluginSettingsBase extends PluginSettingsBase {

  /**
   * The entity type definition.
   *
   * @var EntityTypeInterface
   */
  protected EntityTypeInterface $entity_type_definition;

  /**
   * The bundle configuration entity.
   *
   * @var ConfigEntityInterface
   */
  protected EntityInterface $bundle_entity;

  function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setSettings($configuration['settings'] ?? []);
    $this->entity_type_definition = $configuration['entity_type_definition'];
    $this->bundle_entity = $configuration['bundle_entity'];
  }

  /**
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  public function getEntityTypeDefinition(): EntityTypeInterface {
    return $this->entity_type_definition;
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getBundleEntity(): EntityInterface {
    return $this->bundle_entity;
  }

}
