<?php
namespace Drupal\relewise;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\relewise\Annotation\CategoryPathProvider;
use Drupal\relewise\Entity\EntityIndexer;

interface RelewiseIndexInterface extends ConfigEntityInterface {

  /**
   * @return bool
   */
  public function isEnabled(): bool;

  /**
   * @param bool $enabled
   */
  public function setEnabled(bool $enabled): void;

  /**
   * @return bool
   */
  public function isDefault(): bool;

  /**
   * @param bool $default
   */
  public function setDefault(bool $default): void;

  /**
   * @return array
   */
  public function getContentId(): array;

  /**
   * @param array $content_id
   */
  public function setContentId(array $content_id): void;

  /**
   * @return array
   */
  public function getContentDisplayName(): array;

  /**
   * @param array $content_display_name
   */
  public function setContentDisplayName(array $content_display_name): void;

  /**
   * @return array
   */
  public function getContentData(): array;

  /**
   * @param array $content_data
   */
  public function setContentData(array $content_data): void;

  /**
   * @param string $field
   * @param array $config
   */
  public function setContentDataField($field, array $config): void;

  /**
   * @return array
   */
  public function getProductBrand(): array;

  /**
   * @param array $product_brand
   */
  public function setProductBrand(array $product_brand): void;

  /**
   * @return array
   */
  public function getProductId(): array;

  /**
   * @param array $product_id
   */
  public function setProductId(array $product_id): void;

  /**
   * @return array
   */
  public function getProductDisplayName(): array;

  /**
   * @param array $product_display_name
   */
  public function setProductDisplayName(array $product_display_name): void;

  /**
   * @return array
   */
  public function getProductData(): array;

  /**
   * @param array $product_data
   */
  public function setProductData(array $product_data): void;

  /**
   * @param string $field
   * @param array $config
   */
  public function setProductDataField($field, array $config): void;

  /**
   * @return array
   */
  public function getLanguages(): array;

  /**
   * @param array $languages
   */
  public function setLanguages(array $languages): void;
}
