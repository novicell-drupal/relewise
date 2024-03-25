<?php

namespace Drupal\relewise;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\Plugin\EntityOverview\Engine\ProductEngine;
use Relewise\Models\ContentRecommendationResponse;
use Relewise\Models\ProductRecommendationResponse;
use Relewise\Models\ProductSearchResponse;

class ContentRecommendationOverviewResult extends BaseOverviewResult {

  protected ?\Relewise\Models\ContentRecommendationResponse $result = NULL;

  public function __construct(?ContentRecommendationResponse $result, OverviewFilter $filter, Relewise $relewise) {
    $this->result = $result;
    $this->filter = $filter;
    $this->relewise = $relewise;
  }

  /**
   * @inheritDoc
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * @inheritDoc
   */
  public function getEntities(): array {
    if (empty($this->entities) && !is_null($this->result)) {
      $this->entities = $this->relewise->getEntitiesFromResults($this->result->recommendations, 'contentId');
    }
    return $this->entities;
  }

  /**
   * @inheritDoc
   */
  public function getShownCount() {
    if (!is_null($this->result) && !empty($this->result->recommendations)) {
      return count($this->result->recommendations);
    } else {
      return 0;
    }
  }

  /**
   * @inheritDoc
   */
  public function getResultsCount() {
    if (!is_null($this->result) && !empty($this->result->recommendations)) {
      return count($this->result->recommendations);
    } else {
      return 0;
    }
  }
}
