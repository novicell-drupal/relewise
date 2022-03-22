<?php

namespace Drupal\relewise\Filters;

class ContentIdFilter extends Filter {

  protected array $contentIds = [];

  protected bool $negated = FALSE;

  public function __construct(array $contentIds, $negated = FALSE) {
    $this->contentIds = $contentIds;
    $this->negated = $negated;
  }

  /**
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentIdFilter';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'ContentIds' => $this->contentIds,
      'Negated' => $this->negated
    ];
  }

}
