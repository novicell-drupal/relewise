<?php

namespace Drupal\relewise\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a brand provider annotation object.
 *
 * Plugin Namespace: Plugin\Relewise\Brand
 *
 * @see plugin_api
 *
 * @Annotation
 */
class BrandProvider extends Plugin {

  /**
   * The plugin id.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;
}
