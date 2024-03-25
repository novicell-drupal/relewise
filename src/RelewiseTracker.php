<?php

namespace Drupal\relewise;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Relewise\Models\BrandView;
use Relewise\Models\ContentView;
use Relewise\Models\ProductView;
use Relewise\Models\Trackable;

class RelewiseTracker {
  /**
   * The module handler service.
   *
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  protected Relewise $relewise;

  protected RelewiseIndexer $relewiseIndexer;

  public function __construct(Relewise $relewise, RelewiseIndexer $relewiseIndexer, ModuleHandlerInterface $moduleHandler) {
    $this->relewise = $relewise;
    $this->relewiseIndexer = $relewiseIndexer;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * Tells whether an entity is selected indexing in the config.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function isSelectedForTracking(EntityInterface $entity, $include_exclusion = TRUE): bool {
    $indexers = $this->relewiseIndexer->getEntityIndexersFromEntity($entity);
    if ($include_exclusion) {
      foreach ($indexers as $id => $indexer) {
        if ($this->relewiseIndexer->isExcludedFromIndexing($entity, $indexer->getType())) {
          unset($indexers[$id]);
        }
      }
    }
    foreach ($indexers as $indexer) {
      if ($indexer->isTracking()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Register a view on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function registerEntityView(EntityInterface $entity) {
    /** @var Trackable[] $batch */
    $batch = [];
    $indexers = $this->relewiseIndexer->getEntityIndexersFromEntity($entity);
    foreach ($indexers as $indexer) {
      if ($indexer->isTracking()) {
        if ($indexer->isContent()) {
          $batch[] = ContentView::create(
            $this->relewise->getCurrentUser(),
            $this->relewise->getContent($entity)
          );
        }
        elseif ($indexer->isProduct()) {
          $batch[] = ProductView::create(
            $this->relewise->getCurrentUser(),
            $this->relewise->getProduct($entity)
          );
        }
        elseif ($indexer->isBrand()) {
          $batch[] = BrandView::create(
            $this->relewise->getCurrentUser(),
            $this->relewise->getBrand($entity)
          );
        }
      }
    }

    if (!empty($batch)) {
      try {
        $this->relewise->getTracker()
          ->batchedTracking($this->relewiseIndexer->createBatchedTrackingRequest($batch));
      } catch (\Exception $exception) {
        \Drupal::logger('relewise')
          ->error('Could not track entity view: ' . $exception->getMessage());
      }
    }
  }


  /**
   * Returns an ID used for entity view tracking.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function getTrackingId(EntityInterface $entity) {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

}
