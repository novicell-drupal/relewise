<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\ProductOverviewResult;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\ContainsCondition;
use Relewise\Models\ContainsConditionCollectionArgumentEvaluationMode;
use Relewise\Models\DataSelectionStrategy;
use Relewise\Models\ProductAttributeSorting;
use Relewise\Models\ProductAttributeSortingSortableAttribute;
use Relewise\Models\ProductDataFilter;
use Relewise\Models\ProductDataSorting;
use Relewise\Models\ProductPopularitySorting;
use Relewise\Models\ProductRelevanceSorting;
use Relewise\Models\ProductSorting;
use Relewise\Models\SortOrder;
use Relewise\Models\ValueConditionCollection;

/**
 * @Engine(
 *  id = "relewise_product",
 *  title = "Relewise Product",
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
class ProductEngine extends BaseSearchEngine {

  protected function getRelewiseType(): string {
    return 'product';
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new ProductOverviewResult($this->getResult($filter), $filter, $this->relewise);
  }

  protected function getSearchSorting(OverviewFilter $filter): ?ProductSorting {
    $sorting = NULL;
    switch ($filter->getSort()) {
      case 'newest':
        $selectionStrategy = DataSelectionStrategy::from($this->getSetting('data_selection_strategy'));
        $sorting = ProductDataSorting::create($filter->getOverview()->getSortField(), $selectionStrategy, SortOrder::Descending);
        break;
      case 'oldest':
        $selectionStrategy = DataSelectionStrategy::from($this->getSetting('data_selection_strategy'));
        $sorting = ProductDataSorting::create($filter->getOverview()->getSortField(), $selectionStrategy, SortOrder::Ascending);
        break;
      case 'alphabetical':
        $sorting = ProductAttributeSorting::create(ProductAttributeSortingSortableAttribute::DisplayName, SortOrder::Ascending);
        break;
      case 'popular':
        $sorting = ProductPopularitySorting::create();
        break;
      case 'relevant':
        $sorting = ProductRelevanceSorting::create();
        break;
    }
    return $sorting;
  }

  public function getResult(OverviewFilter $filter) {
    $this->setCount($filter, 0);
    $filters = $this->getFilters($filter);
    $sorting = $this->getSearchSorting($filter);
    $term = $this->getSearchTerm($filter);
    $result = $this->relewise->searchProduct($term, $filters, $filter->getCount() ?? NULL, $filter->getPage(), $sorting, NULL, $this->getSetting('index'));
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
      return $this->relewise->getEntitiesFromResults($response->results, 'productId');
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
    if ($filter->getSort() == 'relevant') {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Create filters for the request
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return ProductDataFilter[]
   */
  protected function getFilters(OverviewFilter $filter) {
    $filters = [];
    foreach ($this->getSearchFields($filter) as $key => $value) {
      if (!empty($value)) {
        if (is_array($value)) {
          $filters[] = ProductDataFilter::create($key)
            ->setConditions(ValueConditionCollection::create(ContainsCondition::create(FALSE)
                ->setValue(DataValueFactory::stringListFromArray($value))
                ->setValueCollectionEvaluationMode(ContainsConditionCollectionArgumentEvaluationMode::Any)));
        } else {
          $filters[] = ProductDataFilter::create($key)
            ->setConditions(ValueConditionCollection::create(ContainsCondition::create(FALSE)
                ->setValue(DataValueFactory::string($value))
                ->setValueCollectionEvaluationMode(ContainsConditionCollectionArgumentEvaluationMode::Any)));
        }
      }
    }
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $data_selection_strategy_options = [];
    foreach (DataSelectionStrategy::cases() as $selectionStrategy) {
      $data_selection_strategy_options[$selectionStrategy->value] = $this->t($selectionStrategy->name);
    }
    $form['data_selection_strategy'] = [
      '#type' => 'select',
      '#title' => $this->t('Data selection strategy'),
      '#description' => $this->t('What data selection strategy to use.'),
      '#options' => $data_selection_strategy_options,
      '#default_value' => $this->getSetting('data_selection_strategy') ?? DataSelectionStrategy::ProductWithFallbackToVariant->value
    ];
    return $form;
  }
}
