<?php

namespace Drupal\relewise\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Returns responses for Relewise module routes.
 */
class RelewiseController extends ControllerBase {

  /**
   * Marks a node as read by the current user right now.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request of the page.
   * @param \Drupal\node\NodeInterface $node
   *   The node whose "last read" timestamp should be updated.
   */
  public function readNode(Request $request, NodeInterface $node) {
    /** @var \Drupal\relewise\Relewise $relewise */
    $relewise = \Drupal::service('relewise');

    // Update the history table, stating that this user viewed this node.
    $relewise->registerContentView($node);

    return new JsonResponse(TRUE);
  }

  /**
   * @return array Render array.
   */
  public function dashboard() {
    return [];
  }

}
