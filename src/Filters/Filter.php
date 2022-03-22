<?php
namespace Drupal\relewise\Filters;

use Drupal\relewise\AbstractClientItem;

abstract class Filter extends AbstractClientItem {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.Requests.Filters';
  }

}
