<?php

namespace Drupal\relewise;

use Drupal\entity_overview\OverviewFilter;
use Relewise\Models\ProductRecommendationResponse;

class ProductRecommendationOverviewResult extends BaseOverviewResult {

  protected ?\Relewise\Models\ProductRecommendationResponse $result = NULL;

  public function __construct(?ProductRecommendationResponse $result, OverviewFilter $filter, Relewise $relewise) {
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
      $this->entities = $this->relewise->getEntitiesFromResults($this->result->recommendations, 'productId');
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
