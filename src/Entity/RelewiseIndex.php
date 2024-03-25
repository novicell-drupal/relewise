<?php

namespace Drupal\relewise\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relewise\RelewiseIndexInterface;

/**
 * Defines the Relewise index config entity.
 *
 * @ConfigEntityType(
 *   id = "relewise_index",
 *   label = @Translation("Relewise index"),
 *   handlers = {
 *     "list_builder" = "Drupal\relewise\RelewiseIndexListBuilder",
 *     "form" = {
 *       "default" = "Drupal\relewise\Form\RelewiseIndexEditForm",
 *       "add" = "Drupal\relewise\Form\RelewiseIndexEditForm",
 *       "edit" = "Drupal\relewise\Form\RelewiseIndexEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "relewise_index",
 *   admin_permission = "configure relewise",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "enabled",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "enabled",
 *     "default",
 *     "content_id",
 *     "content_display_name",
 *     "content_data",
 *     "product_id",
 *     "product_display_name",
 *     "product_data",
 *     "product_brand",
 *     "languages"
 *   },
 *   lookup_keys = {
 *     "id"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/search/relewise/relewise_index/add",
 *     "collection" = "/admin/config/search/relewise/relewise_index",
 *     "edit-form" = "/admin/config/search/relewise/relewise_index/{relewise_index}",
 *     "delete-form" = "/admin/config/search/relewise/relewise_index/{relewise_index}/delete"
 *   }
 * )
 */
class RelewiseIndex extends ConfigEntityBase implements RelewiseIndexInterface {

  use StringTranslationTrait;

  /**
   * The relewise index config ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The index label.
   *
   * @var string
   */
  protected $label;

  /**
   * Is the index currently enabled in Relewise?
   *
   * @var boolean
   */
  protected $enabled = TRUE;

  /**
   * Is the index the default one?
   *
   * @var boolean
   */
  protected $default = FALSE;

  /**
   * @var array
   */
  protected array $content_id = [];

  /**
   * @var array
   */
  protected array $content_display_name = [];

  /**
   * @var array
   */
  protected array $content_data = [];

  /**
   * @var array
   */
  protected $product_brand = [];

  /**
   * @var array
   */
  protected $product_id = [];

  /**
   * @var array
   */
  protected $product_display_name = [];

  /**
   * @var array
   */
  protected $product_data = [];

  /**
   * @var array
   */
  protected $languages = [];

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isDefault(): bool {
    return $this->default;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefault(bool $default): void {
    $this->default = $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentId(): array {
    return $this->content_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setContentId(array $content_id): void {
    $this->content_id = $content_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentDisplayName(): array {
    return $this->content_display_name;
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDisplayName(array $content_display_name): void {
    $this->content_display_name = $content_display_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentData(): array {
    return $this->content_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setContentData(array $content_data): void {
    $this->content_data = $content_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setContentDataField($field, array $config): void {
    $this->product_data[$field] = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductBrand(): array {
    return $this->product_brand;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductBrand(array $product_brand): void {
    $this->product_brand = $product_brand;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductId(): array {
    return $this->product_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductId(array $product_id): void {
    $this->product_id = $product_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductDisplayName(): array {
    return $this->product_display_name;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductDisplayName(array $product_display_name): void {
    $this->product_display_name = $product_display_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getProductData(): array {
    return $this->product_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductData(array $product_data): void {
    $this->product_data = $product_data;
  }

  /**
   * {@inheritdoc}
   */
  public function setProductDataField($field, array $config): void {
    $this->product_data[$field] = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguages(): array {
    return $this->languages;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguages(array $languages): void {
    $this->languages = $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled(bool $enabled): void {
    $this->enabled = $enabled;
  }

}
