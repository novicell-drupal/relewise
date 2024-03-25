<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class CategoryPathProviderManager extends DefaultPluginManager {

  /**
   * Constructs a CategoryPathProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/Relewise/CategoryPath',
      $namespaces,
      $module_handler,
      'Drupal\relewise\CategoryPathProviderInterface',
      'Drupal\relewise\Annotation\CategoryPathProvider'
    );
    $this->alterInfo('relewise_category_path_info');
    $this->setCacheBackend($cache_backend, 'relewise_category_path_plugins');
  }

  /**
   * @param $plugin_id
   * @param array $configuration
   *
   * @return CategoryPathProviderInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\relewise\CategoryPathProviderInterface $plugin */
    $plugin = parent::createInstance($plugin_id, $configuration);
    return $plugin;
  }

  /**
   * Overrides PluginManagerBase::getInstance().
   *
   * @param array $options
   *   An array with the following key/value pairs:
   *   - plugin: (string) The plugin id for the provider.
   *   - entity_type_definition: (EntityTypeInterface) The entity type definition.
   *   - bundle_entity: (EntityInterface) The bundle config entity.
   *   - settings: (array) Settings specific to the provider. Each setting
   *     defaults to the default value specified in the provider definition.
   *
   * @return \Drupal\relewise\CategoryPathProviderInterface|null
   *   A brand provider object or NULL when plugin is not found.
   */
  public function getInstance(array $options) {
    if (empty($options['plugin'] ?? '')) {
      return NULL;
    }
    $plugin_id = $options['plugin'];
    unset($options['plugin']);
    try {
      return $this->createInstance($plugin_id, $options);
    } catch (PluginNotFoundException $exception) {
      return NULL;
    }
  }

  public function buildConfiguration($configuration, EntityTypeInterface $entity_type_definition, EntityInterface $bundle_entity) {
    $configuration['entity_type_definition'] = $entity_type_definition;
    $configuration['bundle_entity'] = $bundle_entity;
    return $configuration;
  }

}
