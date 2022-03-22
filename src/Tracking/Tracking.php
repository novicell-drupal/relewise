<?php
namespace Drupal\relewise\Tracking;

use Drupal\relewise\AbstractClientItem;

abstract class Tracking extends AbstractClientItem {

  /**
   * {@inheritdoc}
   */
  public function getNamespace(): string {
    return 'Relewise.Client.Requests.Tracking';
  }

}
