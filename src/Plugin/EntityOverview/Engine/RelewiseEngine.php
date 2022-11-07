<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentAttributeSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentDataSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentPopularitySorting;
use Drupal\relewise\Relewise;
use Drupal\relewise\RelewiseOverviewResult;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Engine(
 *  id = "relewise",
 *  title = "Relewise",
 *  facets = {
 *    "owner",
 *    "count",
 *    "sort",
 *    "text",
 *    "pagination"
 *  },
 *  multiple = true,
 *  recommendations = true
 * )
 */
class RelewiseEngine extends EngineBase {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\relewise\Relewise
   */
  protected Relewise $relewise;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  /**
   * @var array
   */
  protected array $count = [];

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack, Relewise $relewise, PagerManagerInterface $pagerManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager, $killSwitch);
    $this->entityTypeManager = $entityTypeManager;
    $this->relewise = $relewise;
    $this->request = $requestStack->getCurrentRequest();
    $this->pagerManager = $pagerManager;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('relewise'),
      $container->get('pager.manager')
    );
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new RelewiseOverviewResult($this, $filter);
  }

  public function getResult(OverviewFilter $filter) {
    $this->setCount($filter, 0);
    $fields = [];
    $entity_types = $filter->getOverview()->getEntityBundles();
    $fields['type'] = array_keys($entity_types);
    $fields['bundle'] = [];
    foreach ($entity_types as $entity_type => $bundles) {
      $fields['bundle'] = array_merge($fields['bundle'], $bundles);
    }
    foreach ($filter->getFieldValues() as $key => $value) {
      if ($key !== 'text' && !empty($value)) {
        $fields[$key] = $value;
      }
    }
    $sorting = NULL;
    switch ($filter->getSort()) {
      case 'newest':
        $sorting = new ContentDataSorting($filter->getOverview()->getSortField());
        break;
      case 'oldest':
        $sorting = new ContentDataSorting($filter->getOverview()->getSortField(), 'Ascending');
        break;
      case 'alphabetical':
        $sorting = new ContentAttributeSorting('DisplayName', 'Ascending');
        break;
      case 'popular':
        $sorting = new ContentPopularitySorting();
        break;
    }
    $result = $this->relewise->searchContent($filter->getFieldValue('text') ?? '', $fields, $sorting, $filter->getCount() ?? NULL, $filter->getPage());
    if ($result !== FALSE && !empty($result['hits']) && $result['hits'] > 0) {
      $this->setCount($filter, $result['hits']);
    }

    if ($filter->hasPagination()) {
      $this->request->query->set('page', $filter->getPage());
      $this->pagerManager->createPager($this->getCount($filter), $filter->getCount(), 0);
    }
    return $result;
  }

  public function getEntities(OverviewFilter $filter): array {
    return $this->loadEntities($this->getResult($filter));
  }

  public function loadEntities(array $result): array {
    $entities = [];
    if ($result !== FALSE && !empty($result['hits']) && $result['hits'] > 0) {
      $content = [];
      foreach ($result['results'] as $item) {
        $content_info = explode(':', $item['contentId']);
        $content[$content_info[0]] = empty($content[$content_info[0]]) ? [] : $content[$content_info[0]];
        $content[$content_info[0]][] = $content_info[1];
      }
      foreach ($content as $entity_type => $entity_ids) {
        $entities += $this->entityTypeManager->getStorage($entity_type)
          ->loadMultiple($entity_ids);
      }
    }
    return $entities;
  }

  public function getEntitiesTotal(OverviewFilter $filter, $shown = 0) {
    $count = 0;
    $total = 0;
    switch ($filter->getShowTotal()) {
      case 'filtered':
        $cid = 'relewise:' . $filter->getOverviewId() . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $tags = [];
          foreach ($filter->getOverview()->getEntityBundles() as $entity_type_id => $bundles) {
            $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
            $keys = $entity_type->getKeys();
            $total += $this->entityTypeManager->getStorage($entity_type_id)
              ->getQuery()
              ->condition($keys['bundle'], $bundles, 'IN')
              ->condition('status', 1)
              ->count()
              ->execute();
            $tags += $entity_type->getListCacheTags();
          }
          \Drupal::cache()
            ->set($cid, $total, Cache::PERMANENT, $tags);
        }
        else {
          $total = $cache->data;
        }
        $count = $this->getCount($filter);
        break;
      case 'shown':
        $count = $shown;
        $total = $this->getCount($filter);
        break;
    }
    return t('Showing @count out of @total', [
      '@count' => $count,
      '@total' => $total
    ]);
  }


  public function getSortCriterias(): array {
    return [
      'relevant' => $this->t('Most relevant'),
      'popular' => $this->t('Most popular'),
      'newest' => $this->t('Newest first'),
      'oldest' => $this->t('Oldest first'),
      'alphabetical' => $this->t('Alphabetical')
    ];
  }

  protected function setCount(OverviewFilter $filter, $count) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    $this->count[$hash] = $count;
  }

  protected function getCount(OverviewFilter $filter) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    return $this->count[$hash] ?? NULL;
  }

  public function getCacheableMetadata($filter, bool $has_facets): CacheableMetadata {
    $this->killSwitch->trigger();
    $cache = new CacheableMetadata();
    return $cache->setCacheMaxAge(0);
  }

}
