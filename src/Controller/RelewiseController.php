<?php

namespace Drupal\relewise\Controller;

use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Relewise module routes.
 */
class RelewiseController extends ControllerBase {

  protected $relewise;

  public function __construct(Relewise $relewise) {
    $this->relewise = $relewise;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('relewise'));
  }

  /**
   * Tracks a content view by a user.
   *
   * @param string $entity_type_id
   *   The entity type of the viewed content.
   * @param string $entity_id
   *   The entity id of the viewed content
   */
  public function trackContentView($entity_type_id, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    // Update the history table, stating that this user viewed this node.
    $this->relewise->registerContentView($entity);

    return new JsonResponse(TRUE);
  }

  public function searchContent($term) {
    return new JsonResponse($this->relewise->searchContent($term, [], NULL, 10, 0, ['SelectedContentProperties' => ['AllData' => TRUE]]));
  }

  public function searchTermPrediction($term) {
    return new JsonResponse($this->relewise->searchTermPrediction($term));
  }

  /**
   * @return array Render array.
   */
  public function dashboard() {
    return [];
  }

}
