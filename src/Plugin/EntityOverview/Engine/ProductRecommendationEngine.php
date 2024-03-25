<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\ProductRecommendationOverviewResult;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\ContainsCondition;
use Relewise\Models\ContainsConditionCollectionArgumentEvaluationMode;
use Relewise\Models\FilterCollection;
use Relewise\Models\PersonalProductRecommendationRequest;
use Relewise\Models\PopularityTypes;
use Relewise\Models\PopularProductsRequest;
use Relewise\Models\ProductAndVariantId;
use Relewise\Models\ProductDataFilter;
use Relewise\Models\ProductRecommendationRequestSettings;
use Relewise\Models\ProductsViewedAfterViewingContentRequest;
use Relewise\Models\ProductsViewedAfterViewingProductRequest;
use Relewise\Models\SortProductsRequest;
use Relewise\Models\ValueConditionCollection;

/**
 * @Engine(
 *  id = "relewise_product_recommendations",
 *  title = "Relewise Product Recommendations",
 *  facets = {
 *    "owner",
 *    "count",
 *    "sort"
 *  },
 *  multiple = true,
 *  recommendations = false
 * )
 */
class ProductRecommendationEngine extends BaseSearchEngine {

  protected function getRelewiseType(): string {
    return 'product';
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new ProductRecommendationOverviewResult($this->getResult($filter), $filter, $this->relewise);
  }

  /**
   * @inheritDoc
   */
  public function getSortCriterias(): array {
    return [
      'relevant' => $this->t('Most relevant'),
      'personal' => $this->t('Personal recommendations'),
      'most_viewed' => $this->t('Most viewed'),
      'most_purchased' => $this->t('Most purchased'),
      'product_after_content' => $this->t('Products viewed after viewing content'),
      'product_after_product' => $this->t('Products viewed after viewing product')
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function isPersonalizedRequest(OverviewFilter $filter): bool {
    if ($filter->getSort() == 'personal') {
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
  protected function isSearchField($field_name): bool {
    return TRUE;
  }

  protected function getRecommendationSettings(OverviewFilter $filter): ProductRecommendationRequestSettings {
    $settings = ProductRecommendationRequestSettings::create();
    $settings->setNumberOfRecommendations($filter->getCount());
    $settings->setRecommendVariant($this->getSetting('recommendVariant') ?? FALSE);
    $settings->setAllowFillIfNecessaryToReachNumberOfRecommendations($this->getSetting('allowFillIfNecessaryToReachNumberOfRecommendations') ?? FALSE);
    $settings->setAllowProductsCurrentlyInCart($this->getSetting('allowProductsCurrentlyInCart') ?? FALSE);
    $settings->setAllowReplacingOfRecentlyShownRecommendations($this->getSetting('allowReplacingOfRecentlyShownRecommendations') ?? FALSE);
    $settings->setPrioritizeDiversityBetweenRequests($this->getSetting('prioritizeDiversityBetweenRequests') ?? FALSE);
    return $settings;
  }

  public function getResult(OverviewFilter $filter) {
    $recommender = $this->relewise->getRecommender();
    $this->setCount($filter, 0);

    $collection = FilterCollection::create(... $this->getFilters($filter));
    $settings = $this->getRecommendationSettings($filter);

    switch ($filter->getSort()) {
      case 'relevant':
        // TODO: Needs to be updated for Client v2
        $request = SortProductsRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
        );
        $result = $recommender->sortProducts($request);
        break;
      case 'most_viewed':
      case 'most_purchased':
        $basedOn = match ($filter->getSort()) {
          'most_viewed' => PopularityTypes::MostViewed,
          'most_purchased' => PopularityTypes::MostPurchased,
        };
        $request = PopularProductsRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
          $basedOn
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $request->setSinceMinutesAgo($this->getSetting('sinceMinutesAgo') ?? 60);
        $result = $recommender->popularProducts($request);
        break;
      case 'personal':
        $request = PersonalProductRecommendationRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->personalProductRecommendation($request);
        break;
      case 'product_after_content':
        $request = ProductsViewedAfterViewingContentRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
          $this->relewise->getCurrentIdOfRouteEntity() ?? ''
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->productsViewedAfterViewingContent($request);
        break;
      case 'product_after_product':
        $productAndVariant = ProductAndVariantId::create($this->relewise->getCurrentIdOfRouteEntity() ?? '');
        $request = ProductsViewedAfterViewingProductRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
          $productAndVariant
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->productsViewedAfterViewingProduct($request);
        break;
      default:
        $result = NULL;
    }

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
      return $this->relewise->getEntitiesFromResults($response->recommendations, 'productId');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['recommendVariant'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Recommend variant?'),
      '#default_value' => $this->getSetting('recommendVariant') ?? FALSE
    ];
    $form['allowFillIfNecessaryToReachNumberOfRecommendations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow fill if necessary to reach number of recommendations?'),
      '#default_value' => $this->getSetting('allowFillIfNecessaryToReachNumberOfRecommendations') ?? FALSE
    ];
    $form['allowProductsCurrentlyInCart'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow products currently in cart?'),
      '#default_value' => $this->getSetting('allowProductsCurrentlyInCart') ?? FALSE
    ];
    $form['allowReplacingOfRecentlyShownRecommendations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow replacing of recently shown recommendations?'),
      '#default_value' => $this->getSetting('allowReplacingOfRecentlyShownRecommendations') ?? FALSE
    ];
    $form['prioritizeDiversityBetweenRequests'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prioritize diversity between requests?'),
      '#default_value' => $this->getSetting('prioritizeDiversityBetweenRequests') ?? FALSE
    ];
    $form['sinceMinutesAgo'] = [
      '#type' => 'number',
      '#title' => $this->t('Since minutes ago.'),
      '#description' => $this->t('Used for most viewed and most purchased.'),
      '#default_value' => $this->getSetting('sinceMinutesAgo') ?? 60
    ];
    return $form;
  }

}
