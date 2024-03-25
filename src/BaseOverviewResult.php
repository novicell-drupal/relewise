<?php

namespace Drupal\relewise;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;

abstract class BaseOverviewResult implements OverviewResultInterface {

  protected OverviewFilter $filter;

  protected array $entities = [];

  /**
   * @var \Drupal\relewise\Relewise
   */
  protected Relewise $relewise;

  /**
   * @inheritDoc
   */
  public function getTotalCount() {
    $overview = $this->filter->getOverview();
    $cid = 'entity_overview:' . $overview->id() . '_total';
    $cache = \Drupal::cache()->get($cid);
    if ($cache === FALSE) {
      $total = 0;
      $tags = [];
      try {
        foreach ($overview->getEntityBundles() as $entity_type_id => $bundles) {
          $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
          $keys = $entity_type->getKeys();
          $query = \Drupal::entityTypeManager()->getStorage($entity_type_id)
            ->getQuery()
            ->condition($keys['bundle'], $bundles, 'IN')
            ->condition('status', 1)
            ->accessCheck(TRUE)
            ->count();
          $total += $query
            ->execute();
          $tags += $entity_type->getListCacheTags();
        }
      } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      }
      \Drupal::cache()->set($cid, $total, Cache::PERMANENT, $tags);
    } else {
      $total = $cache->data;
    }
    return $total;
  }

  /**
   * @inheritDoc
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $cache = new CacheableMetadata();
    return $cache->setCacheMaxAge(3600);
  }

  /**
   * @inheritDoc
   */
  public function getRecommendations(): array {
    return [];
  }
}
