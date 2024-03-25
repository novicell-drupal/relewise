<?php

namespace Drupal\relewise_autocomplete;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\relewise\Relewise;
use Relewise\Models\CategoryNameAndIdResult;
use Relewise\Models\CategoryPathResult;
use Relewise\Models\ContentResult;
use Relewise\Models\ContentSearchRequest;
use Relewise\Models\ContentSearchResponse;
use Relewise\Models\ContentSearchSettings;
use Relewise\Models\ProductResult;
use Relewise\Models\ProductSearchRequest;
use Relewise\Models\ProductSearchResponse;
use Relewise\Models\ProductSearchSettings;
use Relewise\Models\SearchIndexSelector;
use Relewise\Models\SearchRequestCollection;
use Relewise\Models\SearchResponse;
use Relewise\Models\SearchResponseCollection;
use Relewise\Models\SelectedContentPropertiesSettings;
use Relewise\Models\SelectedProductPropertiesSettings;

class RelewiseAutocomplete {

  protected Relewise $relewise;
  protected ConfigFactoryInterface $configFactory;
  protected ImmutableConfig $config;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  public function __construct(Relewise $relewise, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    $this->relewise = $relewise;
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('relewise_autocomplete.settings');
    $this->moduleHandler = $moduleHandler;
  }

  public function searchContent($term): ContentSearchResponse {
    $request = $this->createContentSearchRequest($term);
    return $this->relewise->getSearcher()->contentSearch($request);
  }

  public function createContentSearchRequest($term): ContentSearchRequest {
    $config = $this->config->get('content');

    $request = ContentSearchRequest::create(
      $this->relewise->getCurrentLanguage(),
      $this->relewise->getCurrentCurrency(),
      $this->relewise->getCurrentUser(),
      'Autocomplete',
      $term,
      0,
      $config['count'] ?? 10
    );

    $settings = ContentSearchSettings::create();
    $settings->setSelectedContentProperties(SelectedContentPropertiesSettings::create()
      ->setDisplayName($config['display_name'] ?? TRUE)
      ->setCategoryPaths($config['category_paths'] ?? TRUE)
      ->setAllData($config['all_data'] ?? TRUE)
    );
    $request->setSettings($settings);
    /*if (!empty($sorting)) {
      $specification = ContentSortBySpecification::create()->setValue($sorting);
      $request->setSorting($specification);
    }*/
    if (!empty($config['index'])) {
      $request->setIndexSelector(SearchIndexSelector::create($config['index']));
    }
    $this->moduleHandler->alter('relewise_autocomplete_content_request', $request);
    return $request;
  }

  public function processContentSearchResponse(ContentSearchResponse $searchResponse): array {
    $config = $this->config->get('content');
    $results = [
      'hits' => $searchResponse->hits,
      'results' => [],
    ];
    if ($config['recommendations'] ?? FALSE) {
      $results['recommendations'] = [];
      //$results['recommendations'] = $searchResponse->recommendations;
    }
    /** @var ContentResult $contentResult */
    foreach ($searchResponse->results as $key => $contentResult) {
      $result = [];
      if ($config['id'] ?? FALSE) {
        $result['contentId'] = $contentResult->contentId;
      }
      if ($config['display_name'] ?? TRUE) {
        $result['displayName'] = $contentResult->displayName;
      }
      if ($config['category_paths'] ?? FALSE) {
        $result['categoryPaths'] = $this->processCategoryPathResults($contentResult->categoryPaths);
      }
      if ($config['all_data'] ?? TRUE) {
        $result['data'] = $contentResult->data;
      }
      if ($config['href'] ?? TRUE) {
        $result['href'] = $this->getHref($contentResult->contentId);
      }
      $results['results'][$key] = $result;
    }
    $this->moduleHandler->alter('relewise_autocomplete_content', $results, $searchResponse);

    return $results;
  }

  public function searchProduct($term): ProductSearchResponse {
    $request = $this->createProductSearchRequest($term);
    return $this->relewise->getSearcher()->productSearch($request);
  }

  public function createProductSearchRequest($term): ProductSearchRequest {
    $config = $this->config->get('product');

    $request = ProductSearchRequest::create(
      $this->relewise->getCurrentLanguage(),
      $this->relewise->getCurrentCurrency(),
      $this->relewise->getCurrentUser(),
      'Autocomplete',
      $term,
      0,
      $config['count'] ?? 10
    );

    $settings = ProductSearchSettings::create();
    $settings->setSelectedProductProperties(SelectedProductPropertiesSettings::create()
      ->setDisplayName($config['display_name'] ?? TRUE)
      ->setCategoryPaths($config['category_paths'] ?? TRUE)
      ->setAllData($config['all_data'] ?? TRUE)
    );
    $request->setSettings($settings);
    /*if (!empty($sorting)) {
      $specification = ContentSortBySpecification::create()->setValue($sorting);
      $request->setSorting($specification);
    }*/
    if (!empty($config['index'])) {
      $request->setIndexSelector(SearchIndexSelector::create($config['index']));
    }
    $this->moduleHandler->alter('relewise_autocomplete_product_request', $request);
    return $request;
  }

  public function processProductSearchResponse(ProductSearchResponse $searchResponse): array {
    $config = $this->config->get('product');
    $results = [
      'hits' => $searchResponse->hits,
      'results' => [],
    ];
    if ($config['recommendations'] ?? FALSE) {
      $results['recommendations'] = [];
      //$results['recommendations'] = $searchResponse->recommendations;
    }
    /** @var ProductResult $productResult */
    foreach ($searchResponse->results as $key => $productResult) {
      $result = [];
      if ($config['id'] ?? FALSE) {
        $result['productId'] = $productResult->productId;
      }
      if ($config['display_name'] ?? TRUE) {
        $result['displayName'] = $productResult->displayName;
      }
      if ($config['category_paths'] ?? FALSE) {
        $result['categoryPaths'] = $this->processCategoryPathResults($productResult->categoryPaths);
      }
      if ($config['all_data'] ?? TRUE) {
        $result['data'] = $productResult->data;
      }
      if ($config['href'] ?? TRUE) {
        $result['href'] = $this->getHref($productResult->productId);
      }
      $results['results'][$key] = $result;
    }
    $this->moduleHandler->alter('relewise_autocomplete_product', $results, $searchResponse);

    return $results;
  }

  public function multiSearch($term): SearchResponseCollection {
    $request = $this->createSearchRequestCollection($term);
    return $this->relewise->getSearcher()->batchsearch($request);
  }

  public function createSearchRequestCollection($term) {
    $config = $this->config->get('multi_search') ?? [];
    $requests = [];
    if (in_array('content', $config)) {
      $requests[] = $this->createContentSearchRequest($term);
    }
    if (in_array('product', $config)) {
      $requests[] = $this->createProductSearchRequest($term);
    }

    return SearchRequestCollection::create(...$requests);
  }

  public function processSearchResponseCollection(SearchResponseCollection $searchResponse): array {
    $results = [];
    /** @var SearchResponse $response */
    foreach ($searchResponse->responses as $response) {
      switch ($response->typeDefinition) {
        case "Relewise.Client.Responses.Search.ContentSearchResponse, Relewise.Client":
          /** @var ContentSearchResponse $response */
          $results['content'] = $this->processContentSearchResponse($response);
          break;
        case "Relewise.Client.Responses.Search.ProductSearchResponse, Relewise.Client":
          /** @var ProductSearchResponse $response */
          $results['product'] = $this->processProductSearchResponse($response);
          break;
      }
    }
    return $results;
  }

  /**
   * @param CategoryPathResult[] $categoryPaths
   *
   * @return array
   */
  protected function processCategoryPathResults(array $categoryPaths): array {
    $paths = [];
    foreach ($categoryPaths as $categoryPath) {
      $pathFromRoot = [];
      /** @var CategoryNameAndIdResult $nameAndIdResult */
      foreach ($categoryPath->pathFromRoot as $nameAndIdResult) {
        $pathFromRoot[$nameAndIdResult->id] = $nameAndIdResult->displayName;
      }
      $paths[] = $pathFromRoot;
    }
    return $paths;
  }

  protected function getHref($Id): string {
    $info = explode(':', $Id);
    $entity_type_id = $info[0];
    $id = $info[1];
    $url = Url::fromRoute('entity.' . $entity_type_id . '.canonical', [$entity_type_id => $id]);
    return $url->toString();
  }

}
