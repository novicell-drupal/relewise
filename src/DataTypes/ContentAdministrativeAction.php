<?php
namespace Drupal\relewise\DataTypes;

use Drupal\relewise\Filters\Filter;

class ContentAdministrativeAction extends DataType {

  /**
   * @var \Drupal\relewise\Filters\Filter[]
   */
  protected array $filters = [];

  protected string $kind;

  /**
   * @param string $kind
   * @param Filter[] $filters
   */
  public function __construct(string $kind, array $filters) {
    $this->kind = $kind;
    $this->filters = $filters;
  }

  /**
   * @return string
   */
  public function getKind(): string {
    return $this->kind;
  }

  /**
   * @param string $kind
   */
  public function setKind(string $kind): void {
    $this->kind = $kind;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName(): string {
    return 'ContentAdministrativeAction';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Kind' => $this->kind,
      'Filters' => $this->getItemsArray($this->filters)
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toRootArray(): array {
    return [
      'AdministrativeAction' => $this->toArray()
    ];
  }
}
