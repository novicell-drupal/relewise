<?php
namespace Drupal\relewise\Conditions;

use Drupal\relewise\AbstractClientItem;

abstract class Condition extends AbstractClientItem {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.Requests.Conditions';
  }

}
