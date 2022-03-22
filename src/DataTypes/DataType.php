<?php
namespace Drupal\relewise\DataTypes;

use Drupal\relewise\AbstractClientItem;

abstract class DataType extends AbstractClientItem {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.DataTypes';
  }

}
