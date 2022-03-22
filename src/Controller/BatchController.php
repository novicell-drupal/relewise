<?php

namespace Drupal\relewise\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\relewise\Relewise;

class BatchController extends ControllerBase implements ContainerInjectionInterface {

  public function BatchIndex() {
    $nids = \Drupal::entityQuery('node')->execute();
    $chunks = array_chunk($nids, 50, TRUE);

    $operations = [];
    foreach ($chunks as $chunk) {
      $operations[] = ['\Drupal\relewise\Controller\BatchController::PerformIndexing', [$chunk]];
    }

    $batch = [
      'title' => t('Indexing progress'),
      'operations' => $operations,
      'max' => count($operations),
      'finished' => '\Drupal\relewise\Controller\BatchController::BatchMigrateFinished',
      'batch_redirect' => '<front>',
    ];
    batch_set($batch);

    return batch_process('<front>');
  }

  public static function BatchMigrateFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'Performed one indexing operation.', 'Performed @count indexing operations.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

  /**
   * Batch PerformOperation.
   *
   * @param array $context
   *   An array of contextual key/value information for rebuild batch process.
   */
  public static function PerformIndexing($nids, &$context) {
    /** @var Relewise $relewise */
    $relewise = \Drupal::service('relewise');

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    foreach ($nodes as $node) {
      $relewise->insertContentIndex($node);
    }

    if (isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress']++;
    } else {
      $context['sandbox']['progress'] = 1;
    }
    $context['message'] = t('Performed @count indexing operations.', ['@count' => count($nids)]);
    $context['results'][] = $context['message'];
  }

}
