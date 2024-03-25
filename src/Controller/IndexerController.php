<?php

namespace Drupal\relewise\Controller;

use Drupal\Core\Url;
use Drupal\relewise\RelewiseIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Indexer routes.
 */
class IndexerController extends ControllerBase {

  protected RelewiseIndexer $relewiseIndexer;

  public function __construct(RelewiseIndexer $relewiseIndexer) {
    $this->relewiseIndexer = $relewiseIndexer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise.indexer')
    );
  }

  public function reindex() {
    $this->relewiseIndexer->reindex();
    $url = Url::fromRoute('relewise.settings');
    \Drupal::messenger()->addStatus($this->t('Relewise search index is being re-indexed.'));
    return new RedirectResponse($url->toString());
  }

  public function purge() {
    $this->relewiseIndexer->resetIndex();
    $url = Url::fromRoute('relewise.settings');
    \Drupal::messenger()->addStatus($this->t('Relewise search index has been purged.'));
    return new RedirectResponse($url->toString());
  }

}
