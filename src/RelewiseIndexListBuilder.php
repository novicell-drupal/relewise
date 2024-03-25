<?php

namespace Drupal\relewise;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class RelewiseIndexListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['default'] = $this->t('Default');
    $header['label'] = $this->t('Name');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\relewise\RelewiseIndexInterface $relewiseIndex */
    $relewiseIndex = $entity;
    $row['default'] = $relewiseIndex->isDefault() ? '⭐' : '';
    $row['label'] = $relewiseIndex->label();
    $row['status'] = $relewiseIndex->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

}
