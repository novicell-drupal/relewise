<?php

namespace Drupal\relewise;

use Drupal\entity_overview\OverviewFilter;
use Relewise\Models\ContentSearchResponse;

class ContentOverviewResult extends BaseOverviewResult {

  /**
   * @var \Relewise\Models\ContentSearchResponse|null
   */
  protected ?ContentSearchResponse $result = NULL;

  public function __construct(?ContentSearchResponse $result, OverviewFilter $filter, Relewise $relewise) {
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
      $this->entities = $this->relewise->getEntitiesFromResults($this->result->results, 'contentId');
    }
    return $this->entities;
  }

  /**
   * @inheritDoc
   */
  public function getShownCount() {
    if ($this->result !== FALSE && !empty($this->result->hits) && $this->result->hits > 0) {
      return count($this->result->results);
    } else {
      return 0;
    }
  }

  /**
   * @inheritDoc
   */
  public function getResultsCount() {
    if ($this->result !== FALSE && !empty($this->result->hits) && $this->result->hits > 0) {
      return $this->result->hits;
    } else {
      return 0;
    }
  }
}
