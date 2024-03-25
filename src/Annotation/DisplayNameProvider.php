<?php

namespace Drupal\relewise\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a display name provider annotation object.
 *
 * Plugin Namespace: Plugin\Relewise\DisplayName
 *
 * @see plugin_api
 *
 * @Annotation
 */
class DisplayNameProvider extends Plugin {

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
