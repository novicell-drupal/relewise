<?php

namespace Drupal\relewise\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relewise\Relewise;
use Drupal\relewise\RelewiseIndexer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigEntityController extends ControllerBase {
  use StringTranslationTrait;

  protected Relewise $relewise;

  protected RelewiseIndexer $relewiseIndexer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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


  public function __construct(Relewise $relewise, RelewiseIndexer $relewiseIndexer, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, RendererInterface $renderer) {
    $this->relewise = $relewise;
    $this->relewiseIndexer = $relewiseIndexer;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise'),
      $container->get('relewise.indexer'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('renderer')
    );
  }

  public function addContent() {
    $build = [];

    $entity_types = $this->relewiseIndexer->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      $entity_type_id = $entity_type['id'];
      $build[$entity_type_id . '_title'] = [
        '#markup' => '<h3>' . $entity_type['label'] . '</h3>',
      ];

      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      $bundle_entity_type_id = $entity_type->getBundleEntityType();

      $build[$entity_type_id] = [
        '#theme' => 'entity_add_list',
        '#bundles' => []
      ];

      if ($bundle_entity_type_id) {
        $bundle_entity_type = $this->entityTypeManager->getDefinition($bundle_entity_type_id);
        $bundle_entity_type_label = $bundle_entity_type->getSingularLabel();
        $build[$entity_type_id]['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

        // Build the message shown when there are no bundles.
        $build[$entity_type_id]['#add_bundle_message'] = $this->t('There is no @entity_type yet.', [
          '@entity_type' => $bundle_entity_type_label,
        ]);
        // Filter out the bundles the user doesn't have access to.
        $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
        foreach ($bundles as $bundle_name => $bundle_info) {
          $access = $access_control_handler->createAccess($bundle_name, NULL, [], TRUE);
          if (!$access->isAllowed()) {
            unset($bundles[$bundle_name]);
          }
          $this->renderer->addCacheableDependency($build, $access);
        }
        // Add descriptions from the bundle entities.
        $bundles = $this->loadBundleDescriptions($bundles, $bundle_entity_type);
      }

      $form_route_name = 'entity.entity_indexer.add_form';
      // Prepare the #bundles array for the template.
      foreach ($bundles as $bundle_name => $bundle_info) {
        $build[$entity_type_id]['#bundles'][$bundle_name] = [
          'label' => $bundle_info['label'],
          'description' => $bundle_info['description'] ?? '',
          'add_link' => Link::createFromRoute($bundle_info['label'], $form_route_name, [
            'entity_type_id' => $entity_type_id,
            'bundle' => $bundle_name
          ]),
        ];
      }
    }

    return $build;
  }

  /**
   * Expands the bundle information with descriptions, if known.
   *
   * @param array $bundles
   *   An array of bundle information.
   * @param \Drupal\Core\Entity\EntityTypeInterface $bundle_entity_type
   *   The bundle entity type definition.
   *
   * @return array
   *   The expanded array of bundle information.
   */
  protected function loadBundleDescriptions(array $bundles, EntityTypeInterface $bundle_entity_type) {
    if (!$bundle_entity_type->entityClassImplements(EntityDescriptionInterface::class)) {
      return $bundles;
    }
    $bundle_names = array_keys($bundles);
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type->id());
    /** @var \Drupal\Core\Entity\EntityDescriptionInterface[] $bundle_entities */
    $bundle_entities = $storage->loadMultiple($bundle_names);
    foreach ($bundles as $bundle_name => &$bundle_info) {
      if (isset($bundle_entities[$bundle_name])) {
        $bundle_info['description'] = $bundle_entities[$bundle_name]->getDescription();
      }
    }

    return $bundles;
  }
}
