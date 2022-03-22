<?php

namespace Drupal\relewise\DataTypes;

use Drupal\relewise\Content;

class ContentUpdate extends DataType {

  protected Content $content;

  protected string $kind;

  public function __construct(string $kind, Content $content) {
    $this->kind = $kind;
    $this->content = $content;
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
   * @inheritDoc
   */
  public function getTypeName(): string {
    return 'ContentUpdate';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Kind' => $this->kind,
      'Content' => $this->content->toArray()
    ];
  }

}
