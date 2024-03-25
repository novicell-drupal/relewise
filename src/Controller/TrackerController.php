<?php

namespace Drupal\relewise\Controller;

use Drupal\relewise\RelewiseTracker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for tracker routes.
 */
class TrackerController extends ControllerBase {

  protected RelewiseTracker $relewiseTracker;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  public function __construct(RelewiseTracker $relewiseTracker) {
    $this->relewiseTracker = $relewiseTracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise.tracker')
    );
  }

  /**
   * Tracks an entity view by a user.
   *
   * @param string $entity_type_id
   *   The entity type of the viewed content.
   * @param string $entity_id
   *   The entity id of the viewed content
   */
  public function trackEntityView($entity_type_id, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    $this->relewiseTracker->registerEntityView($entity);

    return new JsonResponse(TRUE);
  }

}
