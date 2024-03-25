<?php

namespace Drupal\relewise;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class EntityIndexerListBuilder extends ConfigEntityListBuilder {

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['entity_type'] = $this->t('Entity type');
    $header['bundle'] = $this->t('Bundle');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\relewise\EntityIndexerInterface $entity_indexer */
    $entity_indexer = $entity;
    $definition = $this->entityTypeManager->getDefinition($entity_indexer->getTargetEntityTypeId());
    $row['entity_type'] = $definition->getLabel();

    $entity_type_bundle = $this->entityTypeManager->getStorage($definition->getBundleEntityType())->load($entity_indexer->getTargetBundle());
    $row['bundle'] = $entity_type_bundle->label();

    $row['type'] = $this->t('Unknown');
    if ($entity_indexer->isContent()) {
      $row['type'] = $this->t('Content');
    }
    if ($entity_indexer->isProduct()) {
      $row['type'] = $this->t('Product');
    }
    if ($entity_indexer->isBrand()) {
      $row['type'] = $this->t('Brand');
    }

    return $row + parent::buildRow($entity);
  }

}
