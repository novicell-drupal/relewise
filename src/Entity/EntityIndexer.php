<?php

namespace Drupal\relewise\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relewise\BrandProviderInterface;
use Drupal\relewise\CategoryPathProviderInterface;
use Drupal\relewise\DisplayNameProviderInterface;
use Drupal\relewise\EntityIndexerInterface;
use Drupal\relewise\PriceProviderInterface;

/**
 * Defines the entity indexer config entity.
 *
 * @ConfigEntityType(
 *   id = "entity_indexer",
 *   label = @Translation("Entity indexer"),
 *   handlers = {
 *     "list_builder" = "Drupal\relewise\EntityIndexerListBuilder",
 *     "form" = {
 *       "default" = "Drupal\relewise\Form\EntityIndexerEditForm",
 *       "add" = "Drupal\relewise\Form\EntityIndexerEditForm",
 *       "edit" = "Drupal\relewise\Form\EntityIndexerEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "entity_indexer",
 *   admin_permission = "configure relewise",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "type",
 *     "target_entity_type",
 *     "target_bundle",
 *     "display_name",
 *     "category_path",
 *     "brand",
 *     "list_price",
 *     "sales_price",
 *     "data_fields",
 *     "exclude_field",
 *     "tracking"
 *   },
 *   lookup_keys = {
 *     "type",
 *     "target_entity_type",
 *     "target_bundle"
 *   },
 *   links = {
 *     "add-page" = "/admin/config/search/relewise/entity_indexer/add",
 *     "add-form" = "/admin/config/search/relewise/entity_indexer/add/{entity_type_id}/{bundle}",
 *     "collection" = "/admin/config/search/relewise/entity_indexer",
 *     "edit-form" = "/admin/config/search/relewise/entity_indexer/{entity_indexer}",
 *     "delete-form" = "/admin/config/search/relewise/entity_indexer/{entity_indexer}/delete"
 *   }
 * )
 */
class EntityIndexer extends ConfigEntityBase implements EntityIndexerInterface {

  use StringTranslationTrait;

  /**
   * The entity indexer config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Target entity type.
   *
   * @var string
   */
  protected $target_entity_type;

  /**
   * Target bundle.
   *
   * @var string
   */
  protected $target_bundle;

  /**
   * Relewise type
   *
   * @var string
   */
  protected $type = 'content';

  /**
   * @var array
   */
  protected $data_fields = [];

  /**
   * The configuration used to generate display names.
   *
   * @var array
   */
  protected $display_name = [];

  /**
   * The configuration used to generate categories.
   *
   * @var array
   */
  protected array $category_path = [];

  /**
   * The configuration used to provide a brand.
   *
   * @var array
   */
  protected $brand = [];

  /**
   * The configuration used to provide a list price.
   *
   * @var array
   */
  protected $list_price = [];

  /**
   * The configuration used to provide a list price.
   *
   * @var array
   */
  protected $sales_price = [];

  /**
   * @var string
   */
  protected $exclude_field = '';

  /**
   * @var boolean
   */
  protected $tracking = FALSE;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $definition = $this->entityTypeManager()
      ->getDefinition($this->target_entity_type);
    $this->addDependency('module', $definition->getProvider());
    $dependency = $definition->getBundleConfigDependency($this->target_bundle);
    $this->addDependency($dependency['type'], $dependency['name']);
    // TODO: add target dependency
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getTargetEntityTypeId(): string {
    return $this->target_entity_type;
  }

  /**
   * @inheritDoc
   */
  public function getTargetEntityType(): EntityTypeInterface {
    return $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId());
  }

  /**
   * @inheritDoc
   */
  public function setTargetEntityTypeId(string $entity_type_id): EntityIndexer {
    $this->target_entity_type = $entity_type_id;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getTargetBundle(): string {
    return $this->target_bundle;
  }

  /**
   * @inheritDoc
   */
  public function getTargetBundleEntity(): EntityInterface {
    return $this->entityTypeManager()->getStorage($this->getTargetEntityType()->getBundleEntityType())->load($this->getTargetBundle());
  }

  /**
   * @inheritDoc
   */
  public function setTargetBundle(string $bundle): EntityIndexer {
    $this->target_bundle = $bundle;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * @inheritDoc
   */
  public function setType(string $type): EntityIndexer {
    $this->type = $type;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function isContent(): bool {
    return ($this->type == 'content');
  }

  /**
   * @inheritDoc
   */
  public function isProduct(): bool {
    return ($this->type == 'product');
  }

  /**
   * @inheritDoc
   */
  public function isBrand(): bool {
    return ($this->type == 'brand');
  }

  /**
   * @inheritDoc
   */
  public function getCategoryPathConfig(): array {
    return $this->category_path;
  }

  /**
   * @inheritDoc
   */
  public function setCategoryPathConfig(array $config): EntityIndexer {
    $this->category_path = $config;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getCategoryPathProvider(): ?CategoryPathProviderInterface {
    if (empty($this->getCategoryPathConfig()) || empty($this->getCategoryPathConfig()['plugin'])) {
      return NULL;
    }
    /** @var \Drupal\relewise\CategoryPathProviderManager $categoryPathProviderManager */
    $categoryPathProviderManager = \Drupal::service('plugin.relewise.category_path');
    $plugin_options = $categoryPathProviderManager->buildConfiguration($this->getCategoryPathConfig(), $this->getTargetEntityType(), $this->getTargetBundleEntity());
    return $categoryPathProviderManager->getInstance($plugin_options);
  }

  /**
   * @inheritDoc
   */
  public function getDisplayNameConfig(): array {
    return $this->display_name;
  }

  /**
   * @inheritDoc
   */
  public function getDisplayNameProvider(): ?DisplayNameProviderInterface {
    if (empty($this->getDisplayNameConfig()) || empty($this->getDisplayNameConfig()['plugin'])) {
      return NULL;
    }
    /** @var \Drupal\relewise\DisplayNameProviderManager $displayNameProviderManager */
    $displayNameProviderManager = \Drupal::service('plugin.relewise.display_name');
    $plugin_options = $displayNameProviderManager->buildConfiguration($this->getDisplayNameConfig(), $this->getTargetEntityType(), $this->getTargetBundleEntity());
    return $displayNameProviderManager->getInstance($plugin_options);
  }

  /**
   * @inheritDoc
   */
  public function setDisplayNameConfig(array $config): EntityIndexer {
    $this->display_name = $config;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getDataFieldConfigs(): array {
    return $this->data_fields;
  }

  /**
   * @inheritDoc
   */
  public function getDataFieldConfig($field): array {
    return $this->data_fields[$field] ?? [];
  }

  /**
   * @inheritDoc
   */
  public function getDataFieldLabel($field): string {
    return $this->getTargetFieldDefinitions()[$field]->getLabel() ?? $this->t('Unknown');
  }

  /**
   * @inheritDoc
   */
  public function getDataFieldTargetDefinitions($field): array {
    return [
      'type' => $this->getTargetFieldDefinitions()[$field]->getType(),
      'settings' => $this->getTargetFieldDefinitions()[$field]->getSettings()
    ];
  }

  /**
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   */
  protected function getTargetFieldDefinitions(): array {
    $fieldDefinitions = &drupal_static(__FUNCTION__, []);

    if (empty($fieldDefinitions[$this->getTargetEntityTypeId()][$this->getTargetBundle()])) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $fieldDefinitions[$this->getTargetEntityTypeId()][$this->getTargetBundle()] = $entityFieldManager
        ->getFieldDefinitions($this->getTargetEntityTypeId(), $this->getTargetBundle());
    }
    return $fieldDefinitions[$this->getTargetEntityTypeId()][$this->getTargetBundle()];
  }

  /**
   * @inheritDoc
   */
  public function getDataFields(): array {
    if (empty($this->getDataFieldConfigs())) {
      return [];
    }
    $fieldDefinitions = $this->getTargetFieldDefinitions();
    /** @var \Drupal\relewise\DataFieldManager $dataFieldManager */
    $dataFieldManager = \Drupal::service('plugin.relewise.data_field');
    $fields = [];
    foreach ($this->getDataFieldConfigs() as $field => $dataFieldConfig) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition */
      $fieldDefinition = $fieldDefinitions[$field];
      $plugin_options = $dataFieldManager->buildConfiguration($dataFieldConfig, $fieldDefinition);
      $fields[$field] = $dataFieldManager->getInstance($plugin_options);
    }
    return $fields;
  }

  /**
   * @inheritDoc
   */
  public function setDataFields(array $fields): EntityIndexer {
    $this->data_fields = $fields;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getExcludeField(): string {
    return $this->exclude_field;
  }

  /**
   * @inheritDoc
   */
  public function setExcludeField(string $exclude_field): EntityIndexer {
    $this->exclude_field = $exclude_field;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getBrandConfig(): array {
    return $this->brand;
  }

  /**
   * @inheritDoc
   */
  public function getBrandProvider(): ?BrandProviderInterface {
    if (empty($this->getBrandConfig()) || empty($this->getBrandConfig()['plugin'])) {
      return NULL;
    }
    /** @var \Drupal\relewise\BrandProviderManager $brandProviderManager */
    $brandProviderManager = \Drupal::service('plugin.relewise.brand');
    $plugin_options = $brandProviderManager->buildConfiguration($this->getBrandConfig(), $this->getTargetEntityType(), $this->getTargetBundleEntity());
    return $brandProviderManager->getInstance($plugin_options);
  }

  /**
   * @inheritDoc
   */
  public function setBrandConfig(array $brand_provider): EntityIndexer {
    $this->brand = $brand_provider;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getListPriceConfig(): array {
    return $this->list_price;
  }

  /**
   * @inheritDoc
   */
  public function getListPriceProvider(): ?PriceProviderInterface {
    if (empty($this->getListPriceConfig()) || empty($this->getListPriceConfig()['plugin'])) {
      return NULL;
    }
    /** @var \Drupal\relewise\PriceProviderManager $priceProviderManager */
    $priceProviderManager = \Drupal::service('plugin.relewise.price');
    $plugin_options = $priceProviderManager->buildConfiguration($this->getListPriceConfig(), $this->getTargetEntityType(), $this->getTargetBundleEntity());
    return $priceProviderManager->getInstance($plugin_options);
  }

  /**
   * @inheritDoc
   */
  public function setListPriceConfig(array $list_price): EntityIndexer {
    $this->list_price = $list_price;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getSalesPriceConfig(): array {
    return $this->sales_price;
  }

  /**
   * @inheritDoc
   */
  public function getSalesPriceProvider(): ?PriceProviderInterface {
    if (empty($this->getSalesPriceConfig()) || empty($this->getSalesPriceConfig()['plugin'])) {
      return NULL;
    }
    /** @var \Drupal\relewise\PriceProviderManager $priceProviderManager */
    $priceProviderManager = \Drupal::service('plugin.relewise.price');
    $plugin_options = $priceProviderManager->buildConfiguration($this->getSalesPriceConfig(), $this->getTargetEntityType(), $this->getTargetBundleEntity());
    return $priceProviderManager->getInstance($plugin_options);
  }

  /**
   * @inheritDoc
   */
  public function setSalesPriceConfig(array $sales_price): EntityIndexer {
    $this->sales_price = $sales_price;
    return $this;
  }

  /**
   * @return bool
   */
  public function isTracking(): bool {
    return $this->tracking;
  }

  /**
   * @param bool $tracking
   *
   * @return EntityIndexer
   */
  public function setTracking(bool $tracking): EntityIndexer {
    $this->tracking = $tracking;
    return $this;
  }

}
