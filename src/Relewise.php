<?php
namespace Drupal\relewise;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relewise\Entity\RelewiseIndex;
use Drupal\user\UserInterface;
use Relewise\Factory\UserFactory;
use Relewise\Models\Brand;
use Relewise\Models\Content;
use Relewise\Models\ContentDataFilter;
use Relewise\Models\ContentFacetQuery;
use Relewise\Models\ContentSearchRequest;
use Relewise\Models\ContentSearchResponse;
use Relewise\Models\ContentSearchSettings;
use Relewise\Models\ContentSortBySpecification;
use Relewise\Models\ContentSorting;
use Relewise\Models\Currency;
use Relewise\Models\FilterCollection;
use Relewise\Models\Language;
use Relewise\Models\Product;
use Relewise\Models\ProductDataFilter;
use Relewise\Models\ProductFacetQuery;
use Relewise\Models\ProductSearchRequest;
use Relewise\Models\ProductSearchResponse;
use Relewise\Models\ProductSearchSettings;
use Relewise\Models\ProductSortBySpecification;
use Relewise\Models\ProductSorting;
use Relewise\Models\SearchIndexSelector;
use Relewise\Models\SearchTermPredictionRequest;
use Relewise\Models\SearchTermPredictionResponse;
use Relewise\Models\SelectedContentPropertiesSettings;
use Relewise\Models\SelectedProductPropertiesSettings;
use Relewise\Models\TrackUserUpdateRequest;
use Relewise\Models\User;
use Relewise\Models\UserUpdate;
use Relewise\Models\UserUpdateUpdateKind;
use Relewise\Recommender;
use Relewise\SearchAdministrator;
use Relewise\Searcher;
use Relewise\Tracker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

class Relewise {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle info service.
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The current request.
   */
  protected RequestStack $requestStack;

  /**
   * @var string
   */
  protected string $datasetId;

  /**
   * @var string
   */
  protected string $APIKey;

  protected Searcher $searcher;
  protected SearchAdministrator $searchAdministrator;
  protected Tracker $tracker;
  protected Recommender $recommender;
  protected RouteMatchInterface $routeMatch;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, ModuleHandlerInterface $moduleHandler, RequestStack $requestStack, RouteMatchInterface $routeMatch) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->moduleHandler = $moduleHandler;
    $this->requestStack = $requestStack;
    $this->routeMatch = $routeMatch;
    $this->datasetId = Settings::get('relewise.dataset', '');
    $this->APIKey = Settings::get('relewise.apikey', '');
  }

  /**
   * Returns array keyed by type and with label as value.
   *
   * @return array
   */
  public function getRelewiseTypes(): array {
    return [
      'content' => $this->t('Content'),
      'product' => $this->t('Product'),
      'brand' => $this->t('Brand')
    ];
  }

  /**
   * @return string[]
   */
  public function getIndexOptions($required = FALSE) {
    if ($required) {
      $options = [];
    } else {
      $options = ['' => ' - ' . $this->t('Default') . ' - '];
    }
    $indexes = RelewiseIndex::loadMultiple();
    foreach ($indexes as $index) {
      $options[$index->id()] = $index->label();
    }
    return $options;
  }

  /**
   * Register updates to the user like logins.
   *
   * @param \Drupal\user\UserInterface $user
   *
   * @return bool
   */
  public function userUpdate(UserInterface $user) {
    $request = TrackUserUpdateRequest::create(
      UserUpdate::create($this->getCurrentUser(), UserUpdateUpdateKind::UpdateAndAppend)
    );
    return $this->getTracker()->trackUserUpdate($request);
  }

  /**
   * Performs a search for content in the index.
   *
   * @param string $term
   * @param ContentDataFilter[] $filters
   * @param int $count
   * @param int $page
   * @param \Relewise\Models\ContentSorting|null $sorting
   * @param \Relewise\Models\ContentSearchSettings|null $settings
   * @param string $index
   *
   * @return \Relewise\Models\ContentSearchResponse|null
   */
  public function searchContent(string $term, array $filters = [], int $count = 10, int $page = 0, ?ContentSorting $sorting = NULL, ?ContentSearchSettings $settings = NULL, string $index = '', string $displayLocation = ''): ?ContentSearchResponse {
    $skip = 0;
    if (!empty($count)) {
      $skip = $page * $count;
    }
    if (empty($settings)) {
      $settings = ContentSearchSettings::create();
      $properties = SelectedContentPropertiesSettings::create();
      $properties->setDisplayName(TRUE);
      $properties->setCategoryPaths(FALSE);
      $properties->setAssortments(FALSE);
      $properties->setAllData(FALSE);
      $properties->setViewedByUserInfo(FALSE);
      $settings->setSelectedContentProperties(
        $properties
      );
    }

    $request = ContentSearchRequest::create(
      $this->getCurrentLanguage(),
      $this->getCurrentCurrency(),
      $this->getCurrentUser(),
      $this->getDisplayLocation($displayLocation),
      empty($term) ? NULL : $term,
      $skip,
      $count
    );
    $request->setSettings($settings);
    if (!empty($facets)) {
      $facetQuery = ContentFacetQuery::create();
      foreach ($facets as $facet) {
        $facetQuery->addToItems($facet);
      }
      $request->setFacets($facetQuery);
    }
    if (!empty($filters)) {
      $filterCollection = FilterCollection::create(...$filters);
      $request->setFilters($filterCollection);
    }
    if (!empty($sorting)) {
      $specification = ContentSortBySpecification::create()->setValue($sorting);
      $request->setSorting($specification);
    }
    if (!empty($index)) {
      $request->setIndexSelector(SearchIndexSelector::create($index));
    }
    return $this->getSearcher()->contentSearch($request);
  }

  /**
   * Performs a search for product in the index.
   *
   * @param string|null $term
   * @param ProductDataFilter[] $filters
   * @param int $count
   * @param int $page
   * @param \Relewise\Models\ProductSorting|null $sorting
   * @param \Relewise\Models\ProductSearchSettings|null $settings
   * @param string $index
   * @param \Relewise\Models\DataSelectionStrategy $selectionStrategy
   *
   * @return \Relewise\Models\ProductSearchResponse|null
   */
  public function searchProduct(?string $term, array $filters = [], int $count = 10, int $page = 0, ?ProductSorting $sorting = NULL, ?ProductSearchSettings $settings = NULL, string $index = '', string $displayLocation = ''): ?ProductSearchResponse {
    $skip = 0;
    if (!is_null($count)) {
      $skip = $page * $count;
    }
    if (empty($settings)) {
      $settings = ProductSearchSettings::create();
      $properties = SelectedProductPropertiesSettings::create();
      $properties->setDisplayName(TRUE);
      $properties->setCategoryPaths(FALSE);
      $properties->setAssortments(FALSE);
      $properties->setAllData(FALSE);
      $properties->setViewedByUserInfo(FALSE);
      $settings->setSelectedProductProperties(
        $properties
      );
    }

    $request = ProductSearchRequest::create(
      $this->getCurrentLanguage(),
      $this->getCurrentCurrency(),
      $this->getCurrentUser(),
      $this->getDisplayLocation($displayLocation),
      empty($term) ? NULL : $term,
      $skip,
      $count
    );
    $request->setSettings($settings);
    if (!empty($facets)) {
      $facetQuery = ProductFacetQuery::create();
      foreach ($facets as $facet) {
        $facetQuery->addToItems($facet);
      }
      $request->setFacets($facetQuery);
    }
    if (!empty($filters)) {
      $filterCollection = FilterCollection::create(...$filters);
      $request->setFilters($filterCollection);
    }
    if (!empty($sorting)) {
      $specification = ProductSortBySpecification::create()->setValue($sorting);
      $request->setSorting($specification);
    }
    if (!empty($index)) {
      $request->setIndexSelector(SearchIndexSelector::create($index));
    }
    return $this->getSearcher()->productSearch($request);
  }

  /**
   * Predict search terms for content based on partial term.
   *
   * @param $term
   * @param $count
   *
   * @return \Relewise\Models\SearchTermPredictionResponse|null
   */
  public function searchTermPrediction($term, $count = 10, string $displayLocation = ''): ?SearchTermPredictionResponse {
    $request = SearchTermPredictionRequest::create(
      $this->getCurrentLanguage(),
      $this->getCurrentCurrency(),
      $this->getCurrentUser(),
      $displayLocation,
      $term,
      $count
    );
    return $this->getSearcher()->searchTermPrediction($request);
  }

  /**
   * Transforms search results into entities.
   *
   * @param array $results
   *
   * @return array[]
   */
  public function getEntityIdsFromResults($results, $id_name): array {
    $content = [];
    foreach ($results as $item) {
      $content_info = explode(':', $item->$id_name);
      $content[$content_info[0]] = empty($content[$content_info[0]]) ? [] : $content[$content_info[0]];
      $content[$content_info[0]][] = $content_info[1];
    }
    return $content;
  }

  /**
   * Transforms search results into entities.
   *
   * @param array $results
   *
   * @return EntityInterface[]
   */
  public function getEntitiesFromResults($results, $id_name): array {
    $entities = [];
    $content = $this->getEntityIdsFromResults($results, $id_name);
    foreach ($content as $entity_type => $entity_ids) {
      try {
        $entities += $this->entityTypeManager
          ->getStorage($entity_type)
          ->loadMultiple($entity_ids);
      } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
        \Drupal::logger('relewise')->error('Error occurred while loading content from search result.');
      }
    }
    return $entities;
  }

  /**
   * Returns a content ID used for content indexing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function getContentId(EntityInterface $entity) {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

  public function getContent(EntityInterface $entity): Content {
    return Content::create($this->getContentId($entity));
  }

  /**
   * Returns a product ID used for product indexing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function getProductId(EntityInterface $entity) {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

  public function getProduct(EntityInterface $entity): Product {
    return Product::create($this->getProductId($entity));
  }

  /**
   * Returns a brand ID used for brand indexing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function getBrandId(EntityInterface $entity) {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

  public function getBrand(EntityInterface $entity): Brand {
    return Brand::create($this->getBrandId($entity));
  }

  public function currencyCodeFromEntity(EntityInterface $entity): string {
    return 'DKK';
  }

  public function currencyFromEntity(EntityInterface $entity): Currency {
    return Currency::create($this->currencyCodeFromEntity($entity));
  }

  public function languageFromEntity(EntityInterface $entity): Language {
    return $this->languageFromLanguageCode($entity->language()->getId());
  }

  public function languageFromLanguageCode($langcode): Language {
    if ($langcode == 'und') {
      $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    }
    return Language::create($langcode);
  }

  public function getDisplayLocation($overwrite = ''): string {
    return $overwrite ?? '';
  }

  public function getCurrentLanguage(): Language {
    return $this->languageFromLanguageCode(\Drupal::languageManager()->getCurrentLanguage()->getId());
  }

  public function getCurrentCurrency(): Currency {
    return Currency::create('DKK');
  }

  public function getCurrentUser(): User {
    if ($this->requestStack->getCurrentRequest()->cookies->get('cookiebot-consent--statistics') === '0') {
      user_cookie_delete('relewise');
    }
    $tempId = $this->requestStack->getCurrentRequest()->cookies->get('Drupal_visitor_relewise');
    $user = \Drupal::currentUser();
    if ($user->isAuthenticated()) {
      return UserFactory::byAuthenticatedId($user->id(), $tempId);
    } else {
      if (empty($tempId)) {
        if ($this->requestStack->getCurrentRequest()->cookies->get('cookiebot-consent--statistics') === '1') {
          $tempId = uniqid();
          user_cookie_save(['relewise' => $tempId]);
        } else {
          return UserFactory::anonymous();
        }
      }
      return UserFactory::byTemporaryId($tempId);
    }
  }

  /**
   * Get the id of the current route entity or NULL if it's not an entity route.
   *
   * @return string|null
   */
  public function getCurrentIdOfRouteEntity(): ?string {
    $entity = $this->getEntityFromRouteMatch($this->routeMatch);
    if ($entity instanceof EntityInterface) {
      return $this->getContentId($entity);
    } else {
      return NULL;
    }
  }

  /**
   * Returns an entity parameter from a route match object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return mixed|null
   *   The entity, or null if it's not an entity route.
   */
  protected function getEntityFromRouteMatch(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    if (!$route) {
      return NULL;
    }

    $entity_type_id = $this->getEntityTypeFromRoute($route);
    if ($entity_type_id) {
      return $route_match->getParameter($entity_type_id);
    }

    return NULL;
  }

  /**
   * Return the entity type id from a route object.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return string|null
   *   The entity type id, null if it doesn't exist.
   */
  protected function getEntityTypeFromRoute(Route $route): ?string {
    if (!empty($route->getOptions()['parameters'])) {
      foreach ($route->getOptions()['parameters'] as $option) {
        if (isset($option['type']) && strpos($option['type'], 'entity:') === 0) {
          return substr($option['type'], strlen('entity:'));
        }
      }
    }

    return NULL;
  }

  public function getSearcher(): Searcher {
    if (empty($this->searcher)) {
      $this->searcher = new Searcher(
        $this->datasetId,
        $this->APIKey);
      //$this->searcher->setHttpVersion(CURL_HTTP_VERSION_1_1);
    }
    return $this->searcher;
  }

  public function getSearchAdministrator(): SearchAdministrator {
    if (empty($this->searchAdministrator)) {
      $this->searchAdministrator = new SearchAdministrator(
        $this->datasetId,
        $this->APIKey);
    }
    return $this->searchAdministrator;
  }

  public function getTracker(): Tracker {
    if (empty($this->tracker)) {
      $this->tracker = new Tracker(
        $this->datasetId,
        $this->APIKey);
    }
    return $this->tracker;
  }

  public function getRecommender(): Recommender {
    if (empty($this->recommender)) {
      $this->recommender = new Recommender(
        $this->datasetId,
        $this->APIKey);
    }
    return $this->recommender;
  }
}
