<?php

namespace Drupal\relewise\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a price provider annotation object.
 *
 * Plugin Namespace: Plugin\Relewise\Price
 *
 * @see plugin_api
 *
 * @Annotation
 */
class PriceProvider extends Plugin {

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
