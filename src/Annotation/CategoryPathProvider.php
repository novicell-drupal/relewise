<?php

namespace Drupal\relewise\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a category path provider annotation object.
 *
 * Plugin Namespace: Plugin\Relewise\CategoryPath
 *
 * @see plugin_api
 *
 * @Annotation
 */
class CategoryPathProvider extends Plugin {

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
