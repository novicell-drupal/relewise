<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class DataFieldManager extends DefaultPluginManager {

  /**
   * An array of data field options for each field type.
   *
   * @var array
   */
  protected $dataFieldOptions;

  /**
   * The field type manager to define field.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Constructs a DataFieldManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, FieldTypePluginManagerInterface $field_type_manager) {
    parent::__construct(
      'Plugin/Relewise/DataField',
      $namespaces,
      $module_handler,
      'Drupal\relewise\DataFieldInterface',
      'Drupal\relewise\Annotation\DataField'
    );
    $this->alterInfo('relewise_field_info');
    $this->setCacheBackend($cache_backend, 'relewise_field_plugins');
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * @param $plugin_id
   * @param array $configuration
   *
   * @return \Drupal\relewise\DataFieldInterface
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\relewise\DataFieldInterface $plugin */
    $plugin = parent::createInstance($plugin_id, $configuration);
    return $plugin;
  }

  /**
   * Overrides PluginManagerBase::getInstance().
   *
   * @param array $options
   *   An array with the following key/value pairs:
   *   - plugin: (string) The plugin id for the provider.
   *   - field_definition: (FieldDefinitionInterface) The field definition.
   *   - settings: (array) Settings specific to the provider. Each setting
   *     defaults to the default value specified in the provider definition.
   *
   * @return \Drupal\relewise\DataFieldInterface|null
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

  public function buildConfiguration($configuration, FieldDefinitionInterface $fieldDefinition) {
    $configuration['field_definition'] = $fieldDefinition;
    return $configuration;
  }

  /**
   * Returns an array of formatter options for a field type.
   *
   * @param string|null $field_type
   *   (optional) The name of a field type, or NULL to retrieve all formatters.
   *
   * @return array
   *   If no field type is provided, returns a nested array of all formatters,
   *   keyed by field type.
   */
  public function getApplicableOptions(FieldDefinitionInterface $field_definition) {
    $options = [];
    $data_field_types = $this->getDefinitions();
    uasort($data_field_types, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    foreach ($data_field_types as $plugin_id => $definition) {
      if (isset($definition['class']) && in_array($field_definition->getType(), $definition['field_types']) && $definition['class']::isApplicable($field_definition)) {
        $options[$plugin_id] = $definition['label'];
      }
    }
    return $options;
  }

}
