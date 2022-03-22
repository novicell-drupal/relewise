<?php
namespace Drupal\relewise\Plugin\QueueWorker;

use Drupal\content_hierarchy_path\ContentHierarchyPathUpdater;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relewise content updater.
 *
 * @QueueWorker(
 *   id = "relewise_content_update",
 *   title = @Translation("Relewise content updater"),
 *   cron = {"time" = 10}
 * )
 */
class ContentUpdateWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\relewise\Relewise
   */
  private $relewise;

  /**
   * @var EntityTypeManagerInterface
   */
  private $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, Relewise $relewise)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->relewise = $relewise;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('relewise'));
  }

  public function processItem($data) {
    $entities = $this->entityTypeManager->getStorage($data['entity_type'])->loadMultiple($data['entity_ids']);
    if (count($entities) == 1) {
      foreach ($entities as $entity) {
        $this->relewise->updateContentIndex($entity);
      }
    } else {
      $this->relewise->updateContentIndexMultiple($entities);
    }
  }

}
