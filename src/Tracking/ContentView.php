<?php

namespace Drupal\relewise\Tracking;

class ContentView extends Tracking {

  protected $user = [];
  protected $contentId = '';

  public function __construct(array $user, string $contentId) {
    $this->user = $user;
    $this->contentId = $contentId;
  }

  public function getTypeName(): string {
    return 'ContentView';
  }

  public function toArray(): array {
    return [
      'User' => $this->user,
      'Content' => [
        'id' => $this->contentId
      ],
      'TypeName' => $this->getTypeName()
    ];
  }

}
