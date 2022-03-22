<?php

namespace Drupal\relewise\Filters;

use Drupal\relewise\Conditions\Condition;

class ContentDataFilter extends Filter {

  protected string $key = '';

  protected bool $filterOutIfKeyIsNotFound = TRUE;

  protected bool $mustMatchAllConditions = TRUE;

  /**
   * @var \Drupal\relewise\Conditions\Condition[]
   */
  protected array $conditions = [];

  public function __construct(string $key, array $conditions, $filterOutIfKeyIsNotFound = TRUE, $mustMatchAllConditions = TRUE) {
    $this->key = $key;
    $this->conditions = $conditions;
    $this->filterOutIfKeyIsNotFound = $filterOutIfKeyIsNotFound;
    $this->mustMatchAllConditions = $mustMatchAllConditions;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentDataFilter';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Key' => $this->key,
      'FilterOutIfKeyIsNotFound' => $this->filterOutIfKeyIsNotFound,
      'MustMatchAllConditions' => $this->mustMatchAllConditions,
      'Conditions' => $this->getItemsArray($this->conditions)
    ];
  }

}
