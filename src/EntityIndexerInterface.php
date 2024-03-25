<?php
namespace Drupal\relewise;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\relewise\Annotation\CategoryPathProvider;
use Drupal\relewise\Entity\EntityIndexer;

interface EntityIndexerInterface extends ConfigEntityInterface {

  /**
   * @return string
   */
  public function getTargetEntityTypeId(): string;

  public function getTargetEntityType(): EntityTypeInterface;

  /**
   * @param string $entity_type_id
   *
   * @return $this
   */
  public function setTargetEntityTypeId(string $entity_type_id): self;

  /**
   * @return string
   */
  public function getTargetBundle(): string;

  public function getTargetBundleEntity(): EntityInterface;

  /**
   * @param string $bundle
   *
   * @return $this
   */
  public function setTargetBundle(string $bundle): self;


  /**
   * @return string
   */
  public function getType(): string;

  /**
   * @param string $type
   *
   * @return $this
   */
  public function setType(string $type): self;

  public function isContent(): bool;

  public function isProduct(): bool;

  public function isBrand(): bool;

  /**
   * @return array
   */
  public function getCategoryPathConfig(): array;

  public function getCategoryPathProvider(): ?CategoryPathProviderInterface;

  /**
   * @param array $config
   *
   * @return $this
   */
  public function setCategoryPathConfig(array $config): self;

  /**
   * @return array
   */
  public function getDisplayNameConfig(): array;

  /**
   * @param array $config
   *
   * @return $this
   */
  public function setDisplayNameConfig(array $config): self;

  public function getBrandConfig(): array;

  public function getBrandProvider(): ?BrandProviderInterface;

  /**
   * @param array $brand_provider
   *
   * @return $this
   */
  public function setBrandConfig(array $brand_provider): self;

  /**
   * @return array
   */
  public function getListPriceConfig(): array;

  public function getListPriceProvider(): ?PriceProviderInterface;

  /**
   * @param array $list_price
   *
   * @return $this
   */
  public function setListPriceConfig(array $list_price): self;

  /**
   * @return array
   */
  public function getSalesPriceConfig(): array;

  public function getSalesPriceProvider(): ?PriceProviderInterface;

  /**
   * @param array $sales_price
   *
   * @return $this
   */
  public function setSalesPriceConfig(array $sales_price): self;

  /**
   * @return string
   */
  public function getExcludeField(): string;

  /**
   * @param string $exclude_field
   *
   * @return $this
   */
  public function setExcludeField(string $exclude_field): self;

  /**
   * @return array
   */
  public function getDataFieldConfigs(): array;

  /**
   * @return array
   */
  public function getDataFieldConfig($field): array;

  /**
   * @param $field
   *
   * @return string
   */
  public function getDataFieldLabel($field): string;

  /**
   * @param $field
   *
   * @return array
   */
  public function getDataFieldTargetDefinitions($field): array;

  /**
   * @return DataFieldInterface[]
   */
  public function getDataFields(): array;

  /**
   * @param array $fields
   *
   * @return $this
   */
  public function setDataFields(array $fields): self;

  /**
   * @return bool
   */
  public function isTracking(): bool;

  /**
   * @param bool $tracking
   *
   * @return EntityIndexer
   */
  public function setTracking(bool $tracking): self;
}
