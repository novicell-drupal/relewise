<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\ContentRecommendationOverviewResult;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\ContainsCondition;
use Relewise\Models\ContainsConditionCollectionArgumentEvaluationMode;
use Relewise\Models\ContentDataFilter;
use Relewise\Models\ContentRecommendationRequestSettings;
use Relewise\Models\ContentsViewedAfterViewingContentRequest;
use Relewise\Models\ContentsViewedAfterViewingProductRequest;
use Relewise\Models\FilterCollection;
use Relewise\Models\PersonalContentRecommendationRequest;
use Relewise\Models\PopularContentsRequest;
use Relewise\Models\ProductAndVariantId;
use Relewise\Models\ValueConditionCollection;

/**
 * @Engine(
 *  id = "relewise_content_recommendations",
 *  title = "Relewise Content Recommendations",
 *  facets = {
 *    "owner",
 *    "count",
 *    "sort"
 *  },
 *  multiple = true,
 *  recommendations = false
 * )
 */
class ContentRecommendationEngine extends BaseSearchEngine {

  protected function getRelewiseType(): string {
    return 'content';
  }

  /**
   * @inheritDoc
   */
  public function getOverviewResult(OverviewFilter $filter): OverviewResultInterface {
    return new ContentRecommendationOverviewResult($this->getResult($filter), $filter, $this->relewise);
  }

  /**
   * @inheritDoc
   */
  public function getSortCriterias(): array {
    return [
      'personal' => $this->t('Personal recommendations'),
      'popular' => $this->t('Most popular'),
      'content_after_content' => $this->t('Contents viewed after viewing content'),
      'content_after_product' => $this->t('Contents viewed after viewing product')
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
   * {@inheritdoc}
   */
  protected function isSearchField($field_name): bool {
    return TRUE;
  }

  protected function getRecommendationSettings(OverviewFilter $filter): ContentRecommendationRequestSettings {
    $settings = ContentRecommendationRequestSettings::create();
    $settings->setNumberOfRecommendations($filter->getCount());
    $settings->setAllowFillIfNecessaryToReachNumberOfRecommendations($this->getSetting('allowFillIfNecessaryToReachNumberOfRecommendations') ?? FALSE);
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
      case 'popular':
        $request = PopularContentsRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $request->setSinceMinutesAgo($this->getSetting('sinceMinutesAgo') ?? 60);
        $result = $recommender->popularContents($request);
        break;
      case 'personal':
        $request = PersonalContentRecommendationRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->personalContentRecommendation($request);
        break;
      case 'content_after_content':
        $request = ContentsViewedAfterViewingContentRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
          $this->relewise->getCurrentIdOfRouteEntity() ?? ''
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->contentsViewedAfterViewingContent($request);
        break;
      case 'content_after_product':
        $productAndVariant = ProductAndVariantId::create($this->relewise->getCurrentIdOfRouteEntity() ?? '');
        $request = ContentsViewedAfterViewingProductRequest::create(
          $this->relewise->getCurrentLanguage(),
          $this->relewise->getCurrentCurrency(),
          $this->relewise->getDisplayLocation(),
          $this->relewise->getCurrentUser(),
          $productAndVariant
        );
        $request->setSettings($settings);
        $request->setFilters($collection);
        $result = $recommender->contentsViewedAfterViewingProduct($request);
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
      return $this->relewise->getEntitiesFromResults($response->recommendations, 'contentId');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['allowFillIfNecessaryToReachNumberOfRecommendations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow fill if necessary to reach number of recommendations?'),
      '#default_value' => $this->getSetting('allowFillIfNecessaryToReachNumberOfRecommendations') ?? FALSE
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
      '#description' => $this->t('Used for most popular.'),
      '#default_value' => $this->getSetting('sinceMinutesAgo') ?? 60
    ];
    return $form;
  }

}
