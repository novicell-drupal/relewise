<?php

namespace Drupal\relewise\Plugin\EntityOverview\Engine;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\entity_overview\EngineBase;
use Drupal\entity_overview\OverviewFilter;
use Drupal\entity_overview\OverviewManager;
use Drupal\relewise\Entity\EntityIndexer;
use Drupal\relewise\Relewise;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\ContainsCondition;
use Relewise\Models\ContainsConditionCollectionArgumentEvaluationMode;
use Relewise\Models\ContentDataFilter;
use Relewise\Models\ValueConditionCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class BaseSearchEngine extends EngineBase {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * @var \Drupal\relewise\Relewise
   */
  protected Relewise $relewise;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, OverviewManager $overviewManager, KillSwitch $killSwitch, EntityTypeManagerInterface $entityTypeManager, RequestStack $requestStack, Relewise $relewise, PagerManagerInterface $pagerManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $overviewManager, $killSwitch);
    $this->entityTypeManager = $entityTypeManager;
    $this->relewise = $relewise;
    $this->request = $requestStack->getCurrentRequest();
    $this->pagerManager = $pagerManager;
  }

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_overview.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('relewise'),
      $container->get('pager.manager')
    );
  }

  /**
   * @var array
   */
  protected array $count = [];

  /**
   * The type of Relewise type the results are.
   *
   * @return string
   */
  abstract protected function getRelewiseType(): string;

  protected function getSearchTerm(OverviewFilter $filter) {
    return $filter->getFieldValue('text') ?? '';
  }

  /**
   * Check whether the field should be used as a filter or not.
   *
   * @param $field_name
   *
   * @return bool
   */
  protected function isSearchField($field_name): bool {
    if ($field_name == 'text') {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Fetch all the fields that needs to be filtered.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return array
   */
  protected function getSearchFields(OverviewFilter $filter): array {
    $fields = [];
    $entity_types = $filter->getOverview()->getEntityBundles();
    $fields['type'] = [];
    foreach ($entity_types as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $fields['type'][] = $entity_type . ':' . $bundle;
      }
    }
    foreach ($filter->getFieldValues() as $key => $value) {
      if ($this->isSearchField($key) && !empty($value)) {
        $fields[$key] = $value;
      }
    }
    return $fields;
  }

  /**
   * Create filters for the request
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return ContentDataFilter[]
   */
  protected function getFilters(OverviewFilter $filter) {
    $filters = [];
    foreach ($this->getSearchFields($filter) as $key => $value) {
      if (!empty($value)) {
        if (is_array($value)) {
          $filters[] = ContentDataFilter::create($key)
            ->setConditions(ValueConditionCollection::create(ContainsCondition::create(FALSE)
                ->setValue(DataValueFactory::stringListFromArray($value))
                ->setValueCollectionEvaluationMode(ContainsConditionCollectionArgumentEvaluationMode::Any)));
        } else {
          $filters[] = ContentDataFilter::create($key)
            ->setConditions(ValueConditionCollection::create(ContainsCondition::create(FALSE)
                ->setValue(DataValueFactory::string($value))
                ->setValueCollectionEvaluationMode(ContainsConditionCollectionArgumentEvaluationMode::Any)));
        }
      }
    }
    return $filters;
  }

  abstract public function getEntities(OverviewFilter $filter): array;

  public function getEntitiesTotal(OverviewFilter $filter, $shown = 0) {
    $count = 0;
    $total = 0;
    switch ($filter->getShowTotal()) {
      case 'filtered':
        $cid = 'relewise:' . $filter->getOverviewId() . '_total';
        $cache = \Drupal::cache()->get($cid);
        if ($cache === FALSE) {
          $tags = [];
          foreach ($filter->getOverview()->getEntityBundles() as $entity_type_id => $bundles) {
            $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
            $keys = $entity_type->getKeys();
            $total += $this->entityTypeManager->getStorage($entity_type_id)
              ->getQuery()
              ->condition($keys['bundle'], $bundles, 'IN')
              ->condition('status', 1)
              ->accessCheck()
              ->count()
              ->execute();
            $tags += $entity_type->getListCacheTags();
          }
          \Drupal::cache()
            ->set($cid, $total, Cache::PERMANENT, $tags);
        }
        else {
          $total = $cache->data;
        }
        $count = $this->getCount($filter);
        break;
      case 'shown':
        $count = $shown;
        $total = $this->getCount($filter);
        break;
    }
    return t('Showing @count out of @total', [
      '@count' => $count,
      '@total' => $total
    ]);
  }

  protected function setCount(OverviewFilter $filter, $count) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    $this->count[$hash] = $count;
  }

  protected function getCount(OverviewFilter $filter) {
    $payload = $filter->toArray();
    $hash = md5(serialize($payload));
    return $this->count[$hash] ?? NULL;
  }

  /**
   * Check whether the filter uses a personalized request from Relewise.
   *
   * @param \Drupal\entity_overview\OverviewFilter $filter
   *
   * @return bool
   */
  protected function isPersonalizedRequest(OverviewFilter $filter): bool {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getCacheableMetadata(OverviewFilter $filter, bool $has_facets): CacheableMetadata {
    $cache = new CacheableMetadata();
    $config = \Drupal::config('relewise.settings');
    if ($this->isPersonalizedRequest($filter)) {
      $max_age = $config->get('cache_personal_max_age') ?? 0;
      $tags = ['relewise', 'relewise_personal'];
    } else {
      $max_age = $config->get('cache_default_max_age') ?? 0;
      $tags = ['relewise', 'relewise_default'];
    }
    if ($max_age == 0) {
      $this->killSwitch->trigger();
    } else {
      foreach ($filter->getOverview()->getEntityBundles() as $entity_type_id => $bundles) {
        foreach ($bundles as $bundle) {
          $tags[] = 'relewise_' . $entity_type_id . '_' . $bundle;
        }
        //$tags += $this->entityTypeManager->getDefinition($entity_type_id)->getListCacheTags();
      }
      $cache->addCacheableDependency($filter->getOverview());
      $cache->addCacheTags($tags);
    }
    return $cache->setCacheMaxAge($max_age);
  }

  /**
   * @inheritDoc
   */
  public function getSupportedEntityTypes() {
    $indexers = EntityIndexer::loadMultiple();
    $indexedTypes = [];
    foreach ($indexers as $indexer) {
      if ($indexer->getType() == $this->getRelewiseType()) {
        if (!array_key_exists($indexer->getTargetEntityTypeId(), $indexedTypes)) {
          $indexedTypes[$indexer->getTargetEntityTypeId()] = [];
        }
        $indexedTypes[$indexer->getTargetEntityTypeId()][$indexer->getTargetBundle()] = [];
      }
    }

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo */
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    $types = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!isset($indexedTypes[$entity_type->id()])) {
        continue;
      }

      $bundles = [];
      foreach ($entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle) {
        if (isset($indexedTypes[$entity_type->id()][$bundle_id])) {
          $bundles[$bundle_id] = [
            'id' => $bundle_id,
            'label' => $bundle['label']
          ];
        }
      }

      $types[$entity_type->id()] = [
        'id' => $entity_type->id(),
        'label' => $entity_type->getLabel(),
        'bundles' => $bundles,
      ];
    }

    return $types;
  }

  /**
   * Finds the supported fields and sorting fields of selected entity types and bundles and caches it.
   *
   * @param array $entity_bundles
   *
   * @return void
   */
  protected function findSupportedFields(array $entity_bundles): void {
    /** @var \Drupal\relewise\RelewiseIndexer $relewiseIndexer */
    $relewiseIndexer = \Drupal::service('relewise.indexer');

    $definitions = $relewiseIndexer->getDataFields($entity_bundles)['content'];

    $this->supportedFields = $this->getEngineSupportedFields($entity_bundles);
    $this->supportedSortFields = [];
    foreach ($definitions as $field_name => $definition) {
      // TODO: Support more entity types than taxonomy
      if ($definition['type'] == 'entity_reference_ids' && in_array($definition['target_definition']['settings']['target_type'], [
          'taxonomy_term',
          /*'user', 'media'*/
        ])) {
        $this->supportedFields[$field_name] = $definition['label'];
      }
      if (in_array($definition['type'], [
        'datetime'
      ])) {
        $this->supportedFields[$field_name] = $definition['label'];
      }
      if (in_array($definition['type'], [
        'float',
        'timestamp'
      ])) {
        $this->supportedSortFields[$field_name] = $definition['label'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['index'] = [
      '#type' => 'select',
      '#title' => $this->t('Relewise index'),
      '#description' => $this->t('What Relewise index to use.'),
      '#options' => $this->relewise->getIndexOptions(),
      '#default_value' => $this->getSetting('index') ?? ''
    ];
    return $form;
  }

}
