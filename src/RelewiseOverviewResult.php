<?php

namespace Drupal\relewise;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewResultInterface;
use Drupal\relewise\Plugin\EntityOverview\Engine\RelewiseEngine;

class RelewiseOverviewResult implements OverviewResultInterface {

  protected RelewiseEngine $engine;

  protected OverviewFilter $filter;

  protected $result = [];

  protected array $entities = [];

  protected array $counts = [];

  public function __construct(RelewiseEngine $engine, OverviewFilter $filter) {
    $this->engine = $engine;
    $this->filter = $filter;
  }


  /**
   * @inheritDoc
   */
  public function getResult() {
    if (empty($this->result)) {
      $this->result = $this->engine->getResult($this->filter);
      if ($this->result !== FALSE && !empty($this->result['hits']) && $this->result['hits'] > 0) {
        $this->counts['results'] = $this->result['hits'];
        $this->counts['shown'] = count($this->result['results']);
      } else {
        $this->counts['results'] = 0;
        $this->counts['shown'] = 0;
      }
    }
    return $this->result;
  }

  /**
   * @inheritDoc
   */
  public function getEntities(): array {
    if (empty($this->entities)) {
      $result = $this->getResult();
      if (!empty($result)) {
        $this->entities = $this->engine->loadEntities($result);
      }
    }
    return $this->entities;
  }

  /**
   * @inheritDoc
   */
  public function getShownCount() {
    if (!isset($this->counts['shown'])) {
      $this->getResult();
    }
    return $this->counts['shown'];
  }

  /**
   * @inheritDoc
   */
  public function getResultsCount() {
    if (!isset($this->counts['results'])) {
      $this->getResult();
    }
    return $this->counts['results'];
  }

  /**
   * @inheritDoc
   */
  public function getTotalCount() {
    if (!isset($this->counts['total'])) {
      $this->counts['total'] = $this->engine->getTotalCount($this->filter);
    }
    return $this->counts['total'];
  }

  /**
   * @inheritDoc
   */
  public function getRecommendations(): array {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return $this->engine->getCacheableMetadata($this->filter, (count($this->filter->getFacets()) > 0));
  }


}
