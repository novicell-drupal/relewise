<?php

namespace Drupal\relewise;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\node\NodeInterface;
use Drupal\relewise\Entity\EntityIndexer;
use Drupal\user\EntityOwnerInterface;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\BatchedTrackingRequest;
use Relewise\Models\Brand;
use Relewise\Models\BrandAdministrativeAction;
use Relewise\Models\BrandAdministrativeActionUpdateKind;
use Relewise\Models\BrandIdFilter;
use Relewise\Models\BrandIndexConfiguration;
use Relewise\Models\BrandUpdate;
use Relewise\Models\BrandUpdateUpdateKind;
use Relewise\Models\ClearTextParser;
use Relewise\Models\Content;
use Relewise\Models\ContentAdministrativeAction;
use Relewise\Models\ContentAdministrativeActionUpdateKind;
use Relewise\Models\ContentDataFilter;
use Relewise\Models\ContentIdFilter;
use Relewise\Models\ContentIndexConfiguration;
use Relewise\Models\ContentUpdate;
use Relewise\Models\ContentUpdateUpdateKind;
use Relewise\Models\DataIndexConfiguration;
use Relewise\Models\DeleteSearchIndexRequest;
use Relewise\Models\EqualsCondition;
use Relewise\Models\FieldIndexConfiguration;
use Relewise\Models\FilterCollection;
use Relewise\Models\HtmlParser;
use Relewise\Models\IndexConfiguration;
use Relewise\Models\LanguageIndexConfiguration;
use Relewise\Models\LanguageIndexConfigurationEntry;
use Relewise\Models\PredictionSourceType;
use Relewise\Models\Product;
use Relewise\Models\ProductAdministrativeAction;
use Relewise\Models\ProductAdministrativeActionUpdateKind;
use Relewise\Models\ProductIdFilter;
use Relewise\Models\ProductIndexConfiguration;
use Relewise\Models\ProductUpdate;
use Relewise\Models\ProductUpdateUpdateKind;
use Relewise\Models\SaveSearchIndexRequest;
use Relewise\Models\SearchIndex;
use Relewise\Models\Trackable;
use Relewise\Models\TrackContentAdministrativeActionRequest;
use Relewise\Models\ValueConditionCollection;

class RelewiseIndexer {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The module handler service.
   *
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  protected Relewise $relewise;

  public function __construct(Relewise $relewise, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, ModuleHandlerInterface $moduleHandler, Connection $connection) {
    $this->relewise = $relewise;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->moduleHandler = $moduleHandler;
    $this->connection = $connection;
  }

  /**
   * Tells whether an entity is selected indexing in the config.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function isSelectedForIndexing(EntityInterface $entity, $include_exclusion = TRUE): bool {
    $indexers = $this->getEntityIndexersFromEntity($entity);
    if ($include_exclusion) {
      foreach ($indexers as $id => $indexer) {
        if ($this->isExcludedFromIndexing($entity, $indexer->getType())) {
          unset($indexers[$id]);
        }
      }
    }
    return !empty($indexers);
  }

  /**
   * Tells whether an entity is chosen to be excluded from indexing via a field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $type
   *
   * @return bool
   */
  public function isExcludedFromIndexing(EntityInterface $entity, $type): bool {
    $indexer = $this->getEntityIndexerFromEntity($entity, $type);
    if (empty($indexer)) {
      return TRUE;
    }
    if (!empty($indexer->getExcludeField()) && $entity instanceof ContentEntityInterface) {
      $field = $entity->get($indexer->getExcludeField());
      try {
        if (!$field->isEmpty() && !empty($field->first()) && !empty($field->first()
            ->getValue()) && isset($field->first()
              ->getValue()['value']) && $field->first()
            ->getValue()['value'] == 1) {
          return TRUE;
        }
      } catch (MissingDataException $e) {
        return FALSE;
      }
    }
    return FALSE;
  }

  /**
   * Updates the index on a single entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function updateEntityIndex(EntityInterface $entity): void {
    $this->updateEntityIndexMultiple([$entity]);
  }

  /**
   * Updates the index on multiple entities at once.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   */
  public function updateEntityIndexMultiple(array $entities) {
    /** @var Trackable[] $batch */
    $batch = [];
    $published_content = [];
    $unpublished_content = [];
    $published_products = [];
    $unpublished_products = [];
    $published_brands = [];
    $unpublished_brands = [];

    foreach ($entities as $entity) {
      foreach ($this->getEntityIndexersFromEntity($entity) as $indexer) {
        if ($indexer->isContent()) {
          $content = $this->getFullContent($entity);
          if (is_null($content)) {
            $unpublished_content[] = $entity;
          }
          else {
            $batch[] = ContentUpdate::create($content, ContentUpdateUpdateKind::UpdateAndAppend);
            if ($entity instanceof EntityPublishedInterface) {
              if ($entity->isPublished()) {
                $published_content[] = $entity;
              }
              else {
                $unpublished_content[] = $entity;
              }
            }
          }
        }

        if ($indexer->isProduct()) {
          $product = $this->getFullProduct($entity);
          if (is_null($product)) {
            $unpublished_products[] = $entity;
          }
          else {
            $batch[] = ProductUpdate::create($product, [],ProductUpdateUpdateKind::UpdateAndAppend);
            if ($entity instanceof EntityPublishedInterface) {
              if ($entity->isPublished()) {
                $published_products[] = $entity;
              }
              else {
                $unpublished_products[] = $entity;
              }
            }
          }
        }

        if ($indexer->isBrand()) {
          $brand = $this->getFullBrand($entity);
          if (is_null($brand)) {
            $unpublished_brands[] = $entity;
          }
          else {
            $batch[] = BrandUpdate::create($brand, BrandUpdateUpdateKind::UpdateAndAppend);
            if ($entity instanceof EntityPublishedInterface) {
              if ($entity->isPublished()) {
                $published_brands[] = $entity;
              }
              else {
                $unpublished_brands[] = $entity;
              }
            }
          }
        }
      }
    }

    if (!empty($published_content)) {
      $batch[] = $this->contentAdministrativeActionsFromEntities($published_content, ContentAdministrativeActionUpdateKind::EnableInRecommendations);
    }
    if (!empty($unpublished_content)) {
      $batch[] = $this->contentAdministrativeActionsFromEntities($unpublished_content, ContentAdministrativeActionUpdateKind::DisableInRecommendations);
    }
    if (!empty($published_products)) {
      $batch[] = $this->productAdministrativeActionsFromEntities($published_products, ProductAdministrativeActionUpdateKind::EnableInRecommendations);
    }
    if (!empty($unpublished_products)) {
      $batch[] = $this->productAdministrativeActionsFromEntities($unpublished_products, ProductAdministrativeActionUpdateKind::DisableInRecommendations);
    }
    if (!empty($published_brands)) {
      $batch[] = $this->brandAdministrativeActionsFromEntities($published_brands, BrandAdministrativeActionUpdateKind::Enable);
    }
    if (!empty($unpublished_brands)) {
      $batch[] = $this->brandAdministrativeActionsFromEntities($unpublished_products, BrandAdministrativeActionUpdateKind::Disable);
    }
    if (!empty($batch)) {
      try {
        $this->relewise->getTracker()->batchedTracking($this->createBatchedTrackingRequest($batch));
      } catch (\Exception $exception) {
        \Drupal::logger('relewise')->error('Could not index content batch: ' . $exception->getMessage());
      }
    }
  }

  /**
   * Deletes an entity from the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function deleteContentIndex(EntityInterface $entity) {
    return $this->relewise->getTracker()->trackContentAdministrativeAction(
      TrackContentAdministrativeActionRequest::create(
        $this->contentAdministrativeActionFromEntity($entity, ContentAdministrativeActionUpdateKind::PermanentlyDelete)
      )
    );
  }

  /**
   * Enable an entity in the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function enableContentIndex(EntityInterface $entity) {
    return $this->relewise->getTracker()->trackContentAdministrativeAction(
      TrackContentAdministrativeActionRequest::create(
        $this->contentAdministrativeActionFromEntity($entity, ContentAdministrativeActionUpdateKind::EnableInRecommendations)
      )
    );
  }

  /**
   * Disables an entity in the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function disableContentIndex(EntityInterface $entity) {
    return $this->relewise->getTracker()->trackContentAdministrativeAction(
      TrackContentAdministrativeActionRequest::create(
        $this->contentAdministrativeActionFromEntity($entity, ContentAdministrativeActionUpdateKind::DisableInRecommendations)
      )
    );
  }

  /**
   * @param EntityInterface[] $entities
   * @param \Relewise\Models\ContentAdministrativeActionUpdateKind $kind
   *
   * @return \Relewise\Models\ContentAdministrativeAction
   */
  public function contentAdministrativeActionsFromEntities(array $entities, ContentAdministrativeActionUpdateKind $kind): ContentAdministrativeAction {
    $content = [];
    foreach ($entities as $entity) {
      $content[] = $this->relewise->getContentId($entity);
    }

    return ContentAdministrativeAction::create(
      NULL,
      NULL,
      FilterCollection::create(
        ContentIdFilter::create()->setContentIds(...$content)
      ),
      $kind
    );
  }

  /**
   * @param EntityInterface[] $entities
   * @param \Relewise\Models\ProductAdministrativeActionUpdateKind $kind
   *
   * @return \Relewise\Models\ProductAdministrativeAction
   */
  public function productAdministrativeActionsFromEntities(array $entities, ProductAdministrativeActionUpdateKind $kind, ProductAdministrativeActionUpdateKind $variant_kind = ProductAdministrativeActionUpdateKind::None): ProductAdministrativeAction {
    $products = [];
    foreach ($entities as $entity) {
      $products[] = $this->relewise->getProductId($entity);
    }
    $filters = FilterCollection::create(
      ProductIdFilter::create()->setProductIds(...$products)
    );

    return ProductAdministrativeAction::create(
      NULL,
      NULL,
      $filters,
      $kind,
      $variant_kind
    );
  }

  /**
   * @param EntityInterface[] $entities
   * @param \Relewise\Models\BrandAdministrativeActionUpdateKind $kind
   *
   * @return \Relewise\Models\BrandAdministrativeAction
   */
  public function brandAdministrativeActionsFromEntities(array $entities, BrandAdministrativeActionUpdateKind $kind): BrandAdministrativeAction {
    $brands = [];
    foreach ($entities as $entity) {
      $brands[] = $this->relewise->getBrandId($entity);
    }

    return BrandAdministrativeAction::create(
      NULL,
      NULL,
      FilterCollection::create(
        BrandIdFilter::create()->setBrandIds(...$brands)
      ),
      $kind
    );
  }

  public function getFullContent(EntityInterface $entity): ?Content {
    if ($this->isExcludedFromIndexing($entity, 'content')) {
      return NULL;
    }

    $indexer = $this->getEntityIndexerFromEntity($entity, 'content');
    $content = $this->relewise->getContent($entity);

    if (!empty($indexer->getDisplayNameProvider())) {
      $content->setDisplayName(
        $indexer->getDisplayNameProvider()->getMultilingualDisplayName($entity)
      );
    }

    if (!empty($indexer->getCategoryPathProvider())) {
      $content->setCategoryPaths(
        $indexer->getCategoryPathProvider()->getCategoryPaths($entity)
      );
    }

    $content->addToData('type',
      DataValueFactory::string($entity->getEntityTypeId() . ':' . $entity->bundle())
    );
    $content->addToData('bundle',
      DataValueFactory::string($entity->bundle())
    );
    $content->addToData('LastUpdatedInRelewise',
      DataValueFactory::float(time())
    );
    if ($entity instanceof EntityChangedInterface) {
      $content->addToData('changed',
        DataValueFactory::float($entity->getChangedTime())
      );
    }
    if ($entity instanceof EntityOwnerInterface) {
      $content->addToData('owner',
        DataValueFactory::float($entity->getOwnerId())
      );
    }
    if ($entity instanceof NodeInterface) {
      /** @var NodeInterface $node */
      $node = $entity;
      $content->addToData('created',
        DataValueFactory::float($node->getCreatedTime())
      );
    }

    if ($entity instanceof ContentEntityInterface) {
      foreach ($indexer->getDataFields() as $field_name => $plugin) {
        $value = $plugin->getDataValue($entity->get($field_name));
        if (!empty($value)) {
          $content->addToData($field_name,
            $value
          );
        }
      }
    }

    $this->moduleHandler->alter('relewise_content', $content, $entity);

    return $content;
  }

  public function getFullProduct(EntityInterface $entity): ?Product {
    if ($this->isExcludedFromIndexing($entity, 'product')) {
      return NULL;
    }

    $indexer = $this->getEntityIndexerFromEntity($entity, 'product');
    $product = $this->relewise->getProduct($entity);

    if (!empty($indexer->getDisplayNameProvider())) {
      $product->setDisplayName(
        $indexer->getDisplayNameProvider()->getMultilingualDisplayName($entity)
      );
    }

    if (!empty($indexer->getCategoryPathProvider())) {
      $product->setCategoryPaths(
        $indexer->getCategoryPathProvider()->getCategoryPaths($entity)
      );
    }

    $product->addToData('type',
      DataValueFactory::string($entity->getEntityTypeId() . ':' . $entity->bundle())
    );
    $product->addToData('bundle',
      DataValueFactory::string($entity->bundle())
    );
    $product->addToData('LastUpdatedInRelewise',
      DataValueFactory::float(time())
    );
    if ($entity instanceof EntityChangedInterface) {
      $product->addToData('changed',
        DataValueFactory::float($entity->getChangedTime())
      );
    }
    if ($entity instanceof EntityOwnerInterface) {
      $product->addToData('owner',
        DataValueFactory::float($entity->getOwnerId())
      );
    }
    if ($entity instanceof NodeInterface) {
      /** @var NodeInterface $node */
      $node = $entity;
      $product->addToData('created',
        DataValueFactory::float($node->getCreatedTime())
      );
    }

    if ($entity instanceof ContentEntityInterface) {
      foreach ($indexer->getDataFields() as $field_name => $plugin) {
        $value = $plugin->getDataValue($entity->get($field_name));
        if (!empty($value)) {
          $product->addToData($field_name,
            $value
          );
        }
      }
    }


    $this->moduleHandler->alter('relewise_product', $product, $entity);

    return $product;
  }

  public function getFullBrand(EntityInterface $entity): ?Brand {
    if ($this->isExcludedFromIndexing($entity, 'brand')) {
      return NULL;
    }

    $indexer = $this->getEntityIndexerFromEntity($entity, 'brand');
    $brand = $this->relewise->getBrand($entity);

    if (!empty($indexer->getDisplayNameProvider())) {
      $brand->setDisplayName(
        $indexer->getDisplayNameProvider()->getStringDisplayName($entity)
      );
    }

    $brand->addToData('type',
      DataValueFactory::string($entity->getEntityTypeId())
    );
    $brand->addToData('bundle',
      DataValueFactory::string($entity->bundle())
    );
    $brand->addToData('LastUpdatedInRelewise',
      DataValueFactory::float(time())
    );
    if ($entity instanceof EntityChangedInterface) {
      $brand->addToData('changed',
        DataValueFactory::float($entity->getChangedTime())
      );
    }
    if ($entity instanceof EntityOwnerInterface) {
      $brand->addToData('owner',
        DataValueFactory::float($entity->getOwnerId())
      );
    }
    if ($entity instanceof NodeInterface) {
      /** @var NodeInterface $node */
      $node = $entity;
      $brand->addToData('created',
        DataValueFactory::float($node->getCreatedTime())
      );
    }

    if ($entity instanceof ContentEntityInterface) {
      foreach ($indexer->getDataFields() as $field_name => $plugin) {
        $value = $plugin->getDataValue($entity->get($field_name));
        if (!empty($value)) {
          $brand->addToData($field_name,
            $value
          );
        }
      }
    }


    $this->moduleHandler->alter('relewise_brand', $brand, $entity);

    return $brand;
  }

  /**
   * Deletes all content from index and queues reindexing. Warning: may lose tracking data.
   *
   * @return bool
   */
  public function resetIndex($content = TRUE, $products = TRUE) {
    $batch = [];

    if ($content) {
      $filters = ContentDataFilter::create(
        'LastUpdatedInRelewise'
      );
      $filters->setConditions(
        ValueConditionCollection::create(EqualsCondition::create(DataValueFactory::float(time()), TRUE))
      );
      $collection = FilterCollection::create($filters);
      $action = ContentAdministrativeAction::create(NULL, NULL, $collection, ContentAdministrativeActionUpdateKind::PermanentlyDelete);
      $batch[] = $action;

      $filters = ContentIdFilter::create(TRUE);
      $filters->setContentIds('NonExistingId');
      $collection = FilterCollection::create($filters);
      $action = ContentAdministrativeAction::create(NULL, NULL, $collection, ContentAdministrativeActionUpdateKind::PermanentlyDelete);
      $batch[] = $action;
    }

    if ($products) {
      $filters = ProductIdFilter::create(TRUE);
      $filters->setProductIds('NonExistingId');
      $collection = FilterCollection::create($filters);
      $action = ProductAdministrativeAction::create(NULL, NULL, $collection, ProductAdministrativeActionUpdateKind::PermanentlyDelete, ProductAdministrativeActionUpdateKind::PermanentlyDelete);
      $batch[] = $action;
    }

    try {
      $this->relewise->getTracker()->batchedTracking($this->createBatchedTrackingRequest($batch));
    } catch (\Exception $exception) {
      return FALSE;
    }
    return TRUE;
  }

  public function reindex() {
    $indexers = EntityIndexer::loadMultiple();
    $indexedTypes = [];
    foreach ($indexers as $indexer) {
      if (!array_key_exists($indexer->getTargetEntityTypeId(), $indexedTypes)) {
        $indexedTypes[$indexer->getTargetEntityTypeId()] = [];
      }
      $indexedTypes[$indexer->getTargetEntityTypeId()][] = $indexer->getTargetBundle();
    }

    $this->connection->truncate('relewise_queue')->execute();
    $query = $this->connection->insert('relewise_queue')
      ->fields([
        'entity_type_id',
        'entity_id',
        'timestamp'
      ]);
    $timestamp = time();
    foreach ($indexedTypes as $entity_type => $bundle_ids) {
      if (empty($bundle_ids)) {
        continue;
      }
      $ids = $this->entityTypeManager->getStorage($entity_type)->getQuery()
        ->condition($this->entityTypeManager->getDefinition($entity_type)->getKey('bundle'), $bundle_ids, 'IN')
        ->condition('status', 1)
        ->accessCheck()
        ->execute();

      foreach ($ids as $id) {
        $query->values([
          'entity_type_id' => $entity_type,
          'entity_id' => $id,
          'timestamp' => $timestamp
        ]);
      }
    }
    $query->execute();
  }


  /**
   * Returns list of all entity types that is supported for content indexing.
   *
   * @return array
   */
  public function getSupportedEntityTypes() {
    $types = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type) {
      if (!$this->canBeIndexed($entity_type)) {
        continue;
      }

      $bundles = [];
      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle) {
        $bundles[$bundle_id] = [
          'id' => $bundle_id,
          'label' => $bundle['label']
        ];
      }

      $types[$entity_type->id()] = [
        'id' => $entity_type->id(),
        'label' => $entity_type->getLabel(),
        'bundles' => $bundles,
      ];
    }
    $this->moduleHandler->alter('relewise_supported_entity_types', $types);

    return $types;
  }

  /**
   * Returns whether an entity type can be indexed.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *
   * @return bool
   */
  protected function canBeIndexed(EntityTypeInterface $entity_type): bool {
    if (!$entity_type->hasViewBuilderClass() || !$entity_type->isCommonReferenceTarget() || !$entity_type->hasRouteProviders() || $entity_type->getBundleEntityType() == NULL) {
      return false;
    }
    return true;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $type
   *
   * @return \Drupal\relewise\Entity\EntityIndexer|null
   */
  protected function getEntityIndexerFromEntity(EntityInterface $entity, $type): ?EntityIndexer {
    return $this->getEntityIndexer($entity->getEntityTypeId(), $entity->bundle(), $type);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return \Drupal\relewise\Entity\EntityIndexer[]
   */
  public function getEntityIndexersFromEntity(EntityInterface $entity): array {
    return $this->getEntityIndexers($entity->getEntityTypeId(), $entity->bundle());
  }

  /**
   * @param string $entity_type_id
   * @param string $bundle
   *
   * @return \Drupal\relewise\Entity\EntityIndexer[]
   */
  protected function getEntityIndexers($entity_type_id, $bundle): array {
    $indexers = &drupal_static(__FUNCTION__, []);

    if (!isset($indexers[$entity_type_id . '.' . $bundle])) {
      $types = array_keys($this->relewise->getRelewiseTypes());
      $ids = [];
      foreach ($types as $type) {
        $ids[] = $entity_type_id . '.' . $bundle . '.' . $type;
      }
      $indexers[$entity_type_id . '.' . $bundle] = EntityIndexer::loadMultiple($ids);
    }
    return $indexers[$entity_type_id . '.' . $bundle];
  }

  /**
   * @param string $entity_type_id
   * @param string $bundle
   * @param string $type
   *
   * @return \Drupal\relewise\Entity\EntityIndexer|null
   */
  protected function getEntityIndexer($entity_type_id, $bundle, $type): ?EntityIndexer {
    $indexers = &drupal_static(__FUNCTION__, []);

    $id = $entity_type_id . '.' . $bundle . '.' . $type;
    if (!isset($indexers[$id])) {
      $indexers[$id] = EntityIndexer::load($id);
    }
    return $indexers[$id];
  }

  public function createBatchedTrackingRequest(array $batch): BatchedTrackingRequest {
    $request = BatchedTrackingRequest::create();
    foreach ($batch as $trackable) {
      $request->addToItems($trackable);
    }
    return $request;
  }

  protected function contentAdministrativeActionFromEntity(EntityInterface $entity, ContentAdministrativeActionUpdateKind $kind) {
    return ContentAdministrativeAction::create(
      NULL,
      NULL,
      FilterCollection::create(
        ContentIdFilter::create()->setContentIds($this->relewise->getContentId($entity))
      ),
      $kind
    );
  }

  /**
   * Save Relewise index to the Relewise servers.
   *
   * @param \Drupal\relewise\RelewiseIndexInterface $relewiseIndex
   */
  public function updateRelewiseIndex(RelewiseIndexInterface $relewiseIndex): void {
    $searchIndex = SearchIndex::create($relewiseIndex->id(), $relewiseIndex->label(), $relewiseIndex->isDefault());
    $searchIndex->setEnabled($relewiseIndex->isEnabled());
    $indexConfiguration = IndexConfiguration::create();

    $content = ContentIndexConfiguration::create();
    $content->setId($this->configToFieldIndexConfiguration($relewiseIndex->getContentId()));
    $content->setDisplayName($this->configToFieldIndexConfiguration($relewiseIndex->getContentDisplayName()));
    $dataIndexConfiguration = DataIndexConfiguration::create();
    foreach ($relewiseIndex->getContentData() as $field => $config) {
      $dataIndexConfiguration->addToKeys($field, $this->configToFieldIndexConfiguration($config));
    }
    $content->setData($dataIndexConfiguration);
    $indexConfiguration->setContent($content);

    $product = ProductIndexConfiguration::create();
    $product->setId($this->configToFieldIndexConfiguration($relewiseIndex->getProductId()));
    $product->setDisplayName($this->configToFieldIndexConfiguration($relewiseIndex->getProductDisplayName()));
    $dataIndexConfiguration = DataIndexConfiguration::create();
    foreach ($relewiseIndex->getProductData() as $field => $config) {
      $dataIndexConfiguration->addToKeys($field, $this->configToFieldIndexConfiguration($config));
    }
    $product->setData($dataIndexConfiguration);
    $brandIndexConfiguration = BrandIndexConfiguration::create();
    $brandIndexConfiguration->setId($this->configToFieldIndexConfiguration($relewiseIndex->getProductBrand()['id'] ?? []));
    $brandIndexConfiguration->setDisplayName($this->configToFieldIndexConfiguration($relewiseIndex->getProductBrand()['display_name'] ?? []));
    $product->setBrand($brandIndexConfiguration);
    $indexConfiguration->setProduct($product);

    $languages = [];
    foreach ($relewiseIndex->getLanguages() as $langcode) {
      $languages[] = LanguageIndexConfigurationEntry::create($this->relewise->languageFromLanguageCode($langcode), TRUE);
    }
    $indexConfiguration->setLanguage(LanguageIndexConfiguration::create()->setLanguages(...$languages));

    $searchIndex->setConfiguration($indexConfiguration);
    $request = SaveSearchIndexRequest::create($searchIndex, \Drupal::currentUser()->getDisplayName());
    $this->relewise->getSearchAdministrator()->saveSearchIndex($request);
  }

  protected function configToFieldIndexConfiguration(array $config): FieldIndexConfiguration {
    if (isset($config['parser']) && $config['parser'] == 'HTML') {
      $parser = HtmlParser::create();
    } else {
      $parser = ClearTextParser::create();
    }
    return FieldIndexConfiguration::create($config['include'] ?? FALSE, intval($config['weight'] ?? 0), PredictionSourceType::from($config['prediction']), $parser);
  }

  public function deleteRelewiseIndex(RelewiseIndexInterface $relewiseIndex) {
    try {
      $request = DeleteSearchIndexRequest::create($relewiseIndex->id(), \Drupal::currentUser()->getDisplayName());
      $this->relewise->getSearchAdministrator()->deleteSearchIndex($request);
    } catch (\Exception $e) {
      // Index might already be deletes from somewhere else.
    }
  }

  public function getDataFields($entity_bundles): array {
    $available_fields = [];
    foreach ($this->relewise->getRelewiseTypes() as $type => $label) {
      $available_fields[$type] = [
        'type',
        'bundle',
        'created',
        'changed',
        'owner'
      ];
    }
    $indexers = EntityIndexer::loadMultiple();
    foreach ($indexers as $indexer) {
      if (array_key_exists($indexer->getTargetEntityTypeId(), $entity_bundles)) {
        if (in_array($indexer->getTargetBundle(), $entity_bundles[$indexer->getTargetEntityTypeId()])) {
          $type = $indexer->getType();
          foreach ($indexer->getDataFieldConfigs() as $field => $config) {
            $available_fields[$type][] = $field;
          }
        }
      }
    }
    $all_fields = $this->getAllDataFields();
    $fields = [];
    foreach ($available_fields as $type => $field_names) {
      $fields[$type] = [];
      foreach ($field_names as $field_name) {
        $fields[$type][$field_name] = $all_fields[$type][$field_name];
      }
    }
    return $fields;
  }

  public function getAllDataFields(): array {
    $fields = &drupal_static(__FUNCTION__, []);

    if (empty($fields)) {
      foreach ($this->relewise->getRelewiseTypes() as $type => $label) {
        $fields[$type] = [];
        $fields[$type]['type'] = [
          'label' => $this->t('Entity Type ID'),
          'type' => 'string'
        ];
        $fields[$type]['bundle'] = [
          'label' => $this->t('Bundle ID'),
          'type' => 'string'
        ];
        $fields[$type]['created'] = [
          'label' => $this->t('Created'),
          'type' => 'timestamp'
        ];
        $fields[$type]['changed'] = [
          'label' => $this->t('Last changed'),
          'type' => 'timestamp'
        ];
        $fields[$type]['owner'] = [
          'label' => $this->t('Owner'),
          'type' => 'float',
        ];
      }

      $indexers = EntityIndexer::loadMultiple();
      foreach ($indexers as $indexer) {
        $type = $indexer->getType();
        foreach ($indexer->getDataFieldConfigs() as $field => $config) {
          $fields[$type][$field] = [
            'label' => $indexer->getDataFieldLabel($field),
            'type' => $config['plugin'],
            'target_definition' => $indexer->getDataFieldTargetDefinitions($field)
          ];
        }
      }

      $this->moduleHandler->alter('relewise_all_data_fields', $fields);
    }

    return $fields;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function isEntityInIndexQueue(EntityInterface $entity): bool {
    return $this->isInIndexQueue($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * @param string $entity_type_id
   * @param mixed $entity_id
   *
   * @return bool
   */
  public function isInIndexQueue($entity_type_id, $entity_id): bool {
    $result = $this->connection->select('relewise_queue', 'rq')
      ->condition('entity_type_id', $entity_type_id)
      ->condition('entity_id', $entity_id)
      ->fields('rq', ['timestamp'])
      ->execute();
    return (count($result->fetchAll()) > 0);
  }

  public function addEntityToIndexQueue(EntityInterface $entity) {
    if (!$this->isEntityInIndexQueue($entity)) {
      try {
        $this->connection->insert('relewise_queue')
          ->fields([
            'entity_type_id' => $entity->getEntityTypeId(),
            'entity_id' => $entity->id(),
            'timestamp' => time(),
          ])
          ->execute();
      } catch (\Exception $e) {
      }
    }
  }

  public function addMultipleToIndexQueue($entity_type_id, array $ids) {
    foreach ($ids as $id) {
      if (!$this->isInIndexQueue($entity_type_id, $id)) {
        try {
          $this->connection->insert('relewise_queue')
            ->fields([
              'entity_type_id' => $entity_type_id,
              'entity_id' => $id,
              'timestamp' => time(),
            ])
            ->execute();
        } catch (\Exception $e) {
        }
      }
    }
  }

  public function deleteEntityFromIndexQueue(EntityInterface $entity) {
    $this->connection->delete('relewise_queue')
      ->condition('entity_type_id', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

  /**
   * @return int
   */
  public function getSizeOfIndexQueue(): int {
    return $this->connection->select('relewise_queue', 'rq')
      ->countQuery()->execute()->fetchField();
  }


  public function getTotalSizeOfIndexQueue() {
    $total = 0;

    $indexers = EntityIndexer::loadMultiple();
    $indexedTypes = [];
    foreach ($indexers as $indexer) {
      if (!array_key_exists($indexer->getTargetEntityTypeId(), $indexedTypes)) {
        $indexedTypes[$indexer->getTargetEntityTypeId()] = [];
      }
      $indexedTypes[$indexer->getTargetEntityTypeId()][] = $indexer->getTargetBundle();
    }

    foreach ($indexedTypes as $entity_type => $bundle_ids) {
      if (empty($bundle_ids)) {
        continue;
      }
      $ids = $this->entityTypeManager->getStorage($entity_type)->getQuery()
        ->condition($this->entityTypeManager->getDefinition($entity_type)->getKey('bundle'), $bundle_ids, 'IN')
        ->condition('status', 1)
        ->accessCheck()
        ->execute();

      $total += count($ids);
    }
    return $total;
  }

  public function processIndexQueue() {
    $config = \Drupal::config('relewise.settings');

    $result = $this->connection->select('relewise_queue', 'rq')
      ->fields('rq', ['entity_type_id', 'entity_id'])
      ->orderBy('timestamp')
      ->range(0, $config->get('index_batch_size') ?? 50)
      ->execute();

    $entity_ids = [];
    while($values = $result->fetchAssoc()) {
      if (!isset($entity_ids[$values['entity_type_id']])) {
        $entity_ids[$values['entity_type_id']] = [];
      }
      $entity_ids[$values['entity_type_id']][] = $values['entity_id'];
    }

    $entities = [];
    foreach ($entity_ids as $entity_type_id => $ids) {
      $entities = array_merge($entities, $this->entityTypeManager->getStorage($entity_type_id)->loadMultiple($ids));
    }

    $this->updateEntityIndexMultiple($entities);

    foreach ($entities as $entity) {
      $this->deleteEntityFromIndexQueue($entity);
    }
  }
}
