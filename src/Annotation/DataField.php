<?php

namespace Drupal\relewise\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a data field annotation object.
 *
 * Plugin Namespace: Plugin\Relewise\DataField
 *
 * @see plugin_api
 *
 * @Annotation
 */
class DataField extends Plugin {

  /**
   * The data field plugin ID.
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
  public $label;

  /**
   * The field types supported by the plugin.
   *
   * @var array
   */
  public $field_types;
}
