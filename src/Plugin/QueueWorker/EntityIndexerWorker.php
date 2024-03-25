<?php
namespace Drupal\relewise\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\relewise\RelewiseIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relewise entity indexer worker.
 *
 * @QueueWorker(
 *   id = "relewise_entity_indexer",
 *   title = @Translation("Relewise entity indexer"),
 *   cron = {"time" = 10}
 * )
 */
class EntityIndexerWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\relewise\RelewiseIndexer
   */
  private $relewiseIndexer;

  /**
   * @var EntityTypeManagerInterface
   */
  private $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, RelewiseIndexer $relewiseIndexer)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->relewiseIndexer = $relewiseIndexer;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('relewise.indexer'));
  }

  public function processItem($data) {
    $entities = $this->entityTypeManager->getStorage($data['entity_type'])->loadMultiple($data['entity_ids']);
    $this->relewiseIndexer->updateEntityIndexMultiple($entities);
  }

}
