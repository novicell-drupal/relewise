<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\ContentOverviewResult;
use Drupal\relewise\Relewise;
use Relewise\Models\ContentAttributeSorting;
use Relewise\Models\ContentAttributeSortingSortableAttribute;
use Relewise\Models\ContentDataSorting;
use Relewise\Models\ContentPopularitySorting;
use Relewise\Models\ContentRelevanceSorting;
use Relewise\Models\ContentResult;
use Relewise\Models\ContentSearchResponse;
use Relewise\Models\ContentSorting;
use Relewise\Models\SortOrder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Engine(
 *  id = "relewise_content",
 *  title = "Relewise Content",
 *  facets = {
 *    "text",
 *    "owner",
 *    "count",
 *    "sort",
 *    "pagination"
 *  },
 *  multiple = true,
 *  recommendations = true
 * )
 */
class ContentEngine extends BaseSearchEngine {

  /**
   * @inheritDoc
   */
  protected function getRelewiseType(): string {
    return 'content';
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new ContentOverviewResult($this->getResult($filter), $filter, $this->relewise);
  }

  protected function getSearchSorting(OverviewFilter $filter): ?ContentSorting {
    $sorting = NULL;
    switch ($filter->getSort()) {
      case 'newest':
        $sorting = ContentDataSorting::create($filter->getOverview()->getSortField(), SortOrder::Descending);
        break;
      case 'oldest':
        $sorting = ContentDataSorting::create($filter->getOverview()->getSortField(), SortOrder::Ascending);
        break;
      case 'alphabetical':
        $sorting = ContentAttributeSorting::create(ContentAttributeSortingSortableAttribute::DisplayName, SortOrder::Ascending);
        break;
      case 'popular':
        $sorting = ContentPopularitySorting::create();
        break;
      case 'relevant':
        $sorting = ContentRelevanceSorting::create();
        break;
    }
    return $sorting;
  }

  /**
   * {@inheritdoc}
   */
  public function getResult(OverviewFilter $filter) {
    $this->killSwitch->trigger();
    $this->setCount($filter, 0);
    $filters = $this->getFilters($filter);
    $sorting = $this->getSearchSorting($filter);
    $term = $this->getSearchTerm($filter);
    $result = $this->relewise->searchContent($term, $filters, $filter->getCount() ?? NULL, $filter->getPage(), $sorting, NULL, $this->getSetting('index'));
    if (!empty($result) && !empty($result->hits) && $result->hits > 0) {
      $this->setCount($filter, $result->hits);
    }

    if ($filter->hasPagination()) {
      $this->request->query->set('page', $filter->getPage());
      $this->pagerManager->createPager($this->getCount($filter), $filter->getCount(), 0);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(OverviewFilter $filter): array {
    $response = $this->getResult($filter);
    if (is_null($response)) {
      return [];
    } else {
      return $this->relewise->getEntitiesFromResults($response->results, 'contentId');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSortCriterias(): array {
    return [
      'relevant' => $this->t('Most relevant'),
      'popular' => $this->t('Most popular'),
      'newest' => $this->t('Newest first'),
      'oldest' => $this->t('Oldest first'),
      'alphabetical' => $this->t('Alphabetical')
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function isPersonalizedRequest(OverviewFilter $filter): bool {
    if (!empty($this->getSearchTerm($filter)) || $filter->getSort() == 'relevant') {
      return TRUE;
    } else {
      return FALSE;
    }
  }

}
