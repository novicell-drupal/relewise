<?php

namespace Drupal\relewise\DataTypes;

class UserUpdate extends DataType {

  protected array $user;

  protected string $kind;

  public function __construct(string $kind, array $user) {
    $this->kind = $kind;
    $this->user = $user;
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
    return 'UserUpdate';
  }

  /**
   * @inheritDoc
   */
  public function toArray(): array {
    return [
      'Kind' => $this->kind,
      'User' => $this->user
    ];
  }

}
