<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\RequestStack;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\OverviewManager;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentAttributeSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentDataSorting;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentPopularitySorting;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Engine(
 *  id = "relewise",
 *  title = "Relewise",
 *  facets = {
 *    "count",
 *    "sort",
 *    "text"
 *  },
 *  global = true
 * )
 */
class RelewiseEngine extends EngineBase {

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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack, Relewise $relewise, PagerManagerInterface $pagerManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager);
    $this->entityTypeManager = $entityTypeManager;
    $this->relewise = $relewise;
    $this->request = $requestStack->getCurrentRequest();
    $this->pagerManager = $pagerManager;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('relewise'),
      $container->get('pager.manager')
    );
  }

  public function getResult($entity_bundle, array $filter = [], $page = 0) {
    $this->setCount($entity_bundle, $filter, 0);
    $fields = [];
    if (!empty($this->overviewManager->getEntityTypeID($entity_bundle))) {
      $fields['type'] = $this->overviewManager->getEntityTypeID($entity_bundle);
    }
    if (!empty($this->overviewManager->getBundle($entity_bundle))) {
      $fields['bundle'] = [$this->overviewManager->getBundle($entity_bundle)];
    }
    foreach ($filter['fields'] ?? [] as $key => $value) {
      if ($key !== 'text' && !empty($value)) {
        $fields[$key] = $value;
      }
    }
    $sorting = NULL;
    switch ($filter['sort'] ?? NULL) {
      case 'newest':
        $sorting = new ContentDataSorting('changed');
        break;
      case 'oldest':
        $sorting = new ContentDataSorting('changed', 'Ascending');
        break;
      case 'alphabetical':
        $sorting = new ContentAttributeSorting('DisplayName', 'Ascending');
        break;
      case 'popular':
        $sorting = new ContentPopularitySorting();
        break;
    }
    $result = $this->relewise->searchContent($filter['fields']['text'] ?? '', $fields, $sorting, $filter['count'] ?? NULL, $page);
    if ($result !== FALSE && !empty($result['hits']) && $result['hits'] > 0) {
      $this->setCount($entity_bundle, $filter, $result['hits']);
    }
    if ($filter['pagination'] ?? FALSE) {
      $this->request->query->set('page', $page);
      $element = $this->pagerManager->getMaxPagerElementId() + 1;
      $this->pagerManager->createPager($this->getCount($entity_bundle, $filter), $filter['count'], $element);
    }
    return $result;
  }

  public function getEntities($entity_bundle, array $filter = [], $page = 0) {
    $entities = [];
    $result = $this->getResult($entity_bundle, $filter, $page);
    if ($result !== FALSE && !empty($result['hits']) && $result['hits'] > 0) {
      $content = [];
      foreach ($result['results'] as $item) {
        $content_info = explode(':', $item['contentId']);
        $content[$content_info[0]] = empty($content[$content_info[0]]) ? [] : $content[$content_info[0]];
        $content[$content_info[0]][] = $content_info[1];
      }
      foreach ($content as $entity_type => $entity_ids) {
        $entities += $this->entityTypeManager->getStorage($entity_type)->loadMultiple($entity_ids);
      }
    }
    return $entities;
  }

  public function getEntitiesTotal($entity_bundle, array $filter = [], $shown = 0) {
    $count = 0;
    $total = 0;
    switch ($filter['show_total']) {
      case 'filtered':
        $cid = 'relewise:' . $entity_bundle . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $keys = $this->entityTypeManager->getDefinition($this->overviewManager->getEntityTypeID($entity_bundle))
            ->getKeys();
          $total = $this->entityTypeManager->getStorage($this->overviewManager->getEntityTypeID($entity_bundle))
            ->getQuery()
            ->condition($keys['bundle'], [$this->overviewManager->getBundle($entity_bundle)], 'IN')
            ->condition('status', 1)
            ->count()
            ->execute();
          \Drupal::cache()->set($cid, $total, Cache::PERMANENT, [$this->overviewManager->getEntityTypeID($entity_bundle) . '_list']);
        } else {
          $total = $cache->data;
        }
        $count = $this->getCount($entity_bundle, $filter);
        break;
      case 'shown':
        $count = $shown;
        $total = $this->getCount($entity_bundle, $filter);
        break;
    }
    return t('Showing @count out of @total', ['@count' => $count, '@total' => $total]);
  }


  public function getSortCriterias() {
    return [
      'relevant' => $this->t('Most relevant'),
      'popular' => $this->t('Most popular'),
      'newest' => $this->t('Newest first'),
      'oldest' => $this->t('Oldest first'),
      'alphabetical' => $this->t('Alphabetical')
    ];
  }

  public function getShowTotalOptions() {
    return [
      '' => t('None'),
      'filtered' => t('Filtered out of total number of items'),
      'shown' => t('Shown items out of filtered number of items'),
    ];
  }

  protected function setCount($entity_bundle, array $filter, $count) {
    $payload = $filter;
    $payload['entity_bundle'] = $entity_bundle;
    $hash = md5(serialize($payload));
    $this->count[$hash] = $count;
  }

  protected function getCount($entity_bundle, array $filter) {
    $payload = $filter;
    $payload['entity_bundle'] = $entity_bundle;
    $hash = md5(serialize($payload));
    return $this->count[$hash];
  }

}
