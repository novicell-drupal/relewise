<?php
namespace Drupal\relewise;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\relewise\Conditions\EqualsCondition;
use Drupal\relewise\DataTypes\ContentAdministrativeAction;
use Drupal\relewise\DataTypes\ContentUpdate;
use Drupal\relewise\DataTypes\Search\Facets\Queries\ContentDataStringValueFacet;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentSorting;
use Drupal\relewise\DataTypes\UserUpdate;
use Drupal\relewise\Filters\ContentDataFilter;
use Drupal\relewise\Filters\ContentIdFilter;
use Drupal\relewise\Tracking\ContentView;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class Relewise {


  /**
   * The Relewise API service.
   *
   * @var \Drupal\relewise\RelewiseAPI
   */
  protected $API;

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

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current request.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $requestStack;

  public function __construct(RelewiseAPI $API, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, ModuleHandlerInterface $moduleHandler, TitleResolverInterface $titleResolver, RouteMatchInterface $routeMatch, RequestStack $requestStack) {
    $this->API = $API;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->moduleHandler = $moduleHandler;
    $this->titleResolver = $titleResolver;
    $this->routeMatch = $routeMatch;
    $this->requestStack = $requestStack;
  }

  /**
   * Tells whether an entity is selected indexing in the config.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function isSelectedForIndexing(EntityInterface $entity) {
    /*if (empty(Settings::get('relewise.dataset', '')) || empty(Settings::get('relewise.apikey', ''))) {
      return FALSE;
    }*/
    $config = \Drupal::config('relewise.settings');
    $bundles = $config->get('entity_bundles') ?? [];
    if (!empty($bundles[$entity->getEntityTypeId()]) && in_array($entity->bundle(), $bundles[$entity->getEntityTypeId()])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Register updates to the user like logins.
   *
   * @param \Drupal\user\UserInterface $user
   *
   * @return bool
   */
  public function userUpdate(UserInterface $user) {
    return $this->API->TrackUserUpdateRequest(new UserUpdate('UpdateAndAppend', $this->getCurrentUserArray()));
  }

  /**
   * Register content view on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function registerContentView(EntityInterface $entity) {
    return $this->API->TrackContentViewRequest(
      new ContentView($this->getCurrentUserArray(), $this->getContentId($entity))
    );
  }

  /**
   * Updates the index on a single entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function updateContentIndex(EntityInterface $entity) {
    $content = $this->getContent($entity);
    if (!empty($content)) {
      if ($this->API->TrackContentUpdateRequest(new ContentUpdate('UpdateAndAppend', $content))) {
        if ($entity instanceof EntityPublishedInterface) {
          $filters = [
            new ContentIdFilter([$this->getContentId($entity)])
          ];
          if ($entity->isPublished()) {
            $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('EnableInRecommendations', $filters));
          } else {
            $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('DisableInRecommendations', $filters));
          }
        }
      } else {
        \Drupal::logger('relewise')->error('Could not index content %content_id', ['%content_id' => $this->getContentId($entity)]);
      };
    }
  }

  /**
   * Updates the index on multiple entities at once.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   */
  public function updateContentIndexMultiple(array $entities) {
    $batch = [];
    $published = [];
    $unpublished = [];
    $deleted = [];
    foreach ($entities as $entity) {
      $content = $this->getContent($entity);
      if (is_null($content)) {
        $deleted[] = $this->getContentId($entity);
      } else {
        $batch[] = new ContentUpdate('UpdateAndAppend', $content);
        if ($entity instanceof EntityPublishedInterface) {
          if ($entity->isPublished()) {
            $published[] = $this->getContentId($entity);
          }
          else {
            $unpublished[] = $this->getContentId($entity);
          }
        }
      }
    }
    if (!empty($published)) {
      $batch[] = new ContentAdministrativeAction('EnableInRecommendations', [
        new ContentIdFilter($published)
      ]);
    }
    if (!empty($unpublished)) {
      $batch[] = new ContentAdministrativeAction('DisableInRecommendations', [
        new ContentIdFilter($unpublished)
      ]);
    }
    if (!empty($deleted)) {
      $batch[] = new ContentAdministrativeAction('PermanentlyDelete', [
        new ContentIdFilter($deleted)
      ]);
    }
    if (!empty($content)) {
      if (!$this->API->BatchedTrackingRequest($batch)) {
        \Drupal::logger('relewise')->error('Could not index content batch');
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
    $filters = [
      new ContentIdFilter([$this->getContentId($entity)])
    ];
    return $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('PermanentlyDelete', $filters));
  }

  /**
   * Enable an entity in the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function enableContentIndex(EntityInterface $entity) {
    $filters = [
      new ContentIdFilter([$this->getContentId($entity)])
    ];
    return $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('EnableInRecommendations', $filters));
  }

  /**
   * Disables an entity in the index.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function disableContentIndex(EntityInterface $entity) {
    $filters = [
      new ContentIdFilter([$this->getContentId($entity)])
    ];
    return $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('DisableInRecommendations', $filters));
  }

  /**
   * Deletes all content from index and queues reindexing. Warning: may lose tracking data.
   *
   * @return bool
   */
  public function resetIndex() {
    $conditions = [
      new EqualsCondition(new Value(time()), TRUE)
    ];
    $filters = [
      new ContentDataFilter('LastUpdatedInRelewise', $conditions)
    ];
    if ($this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('PermanentlyDelete', $filters))) {
      $filters = [
        new ContentIdFilter(['NonExistingId'], TRUE)
      ];
      return $this->API->TrackContentAdministrativeActionRequest(new ContentAdministrativeAction('PermanentlyDelete', $filters));
    } else {
      return FALSE;
    }
  }

  public function reindex() {
    $config = \Drupal::config('relewise.settings');
    $bundles = $config->get('entity_bundles') ?? [];

    foreach ($bundles as $entity_type => $bundle_ids) {
      if (empty($bundle_ids)) {
        continue;
      }
      $ids = $this->entityTypeManager->getStorage($entity_type)->getQuery()
        ->condition($this->entityTypeManager->getDefinition($entity_type)->getKey('bundle'), $bundle_ids, 'IN')
        ->condition('status', 1)
        ->execute();
      $chunks = array_chunk($ids, $config->get('index_batch_size') ?? 50);
      $queue = \Drupal::queue('relewise_content_update');
      foreach ($chunks as $chunk) {
        $queue->createItem([
          'entity_type' => $entity_type,
          'entity_ids' => $chunk
        ]);
      }
    }
  }

  /**
   * Performs a search for content in the index.
   *
   * @param string $term
   * @param array $fields
   * @param \Drupal\relewise\DataTypes\Search\Sorting\Content\ContentSorting|null $sorting
   * @param int|null $count
   * @param int $page
   * @param array $settings
   *
   * @return array|bool
   */
  public function searchContent($term, array $fields = [], ?ContentSorting $sorting = NULL, $count = 10, $page = 0, array $settings = []) {
    $facets = [];
    foreach ($fields as $key => $value) {
      if (is_array($value)) {
        $facets[] = new ContentDataStringValueFacet($key, 'Data', array_values($value));
      } else {
        $facets[] = new ContentDataStringValueFacet($key, 'Data', [$value]);
      }
    }
    $skip = NULL;
    if (!is_null($count)) {
      $skip = $page * $count;
    }
    $title = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $this->routeMatch->getRouteObject());
    if (is_array($title)) {
      /** @var \Drupal\Core\Render\RendererInterface $renderer */
      $renderer = \Drupal::service('renderer');
      $title = strip_tags($renderer->render($title));
    }
    $settings += [
      "SelectedContentProperties" => []
    ];
    $settings['SelectedContentProperties'] += [
      "DisplayName" => TRUE,
      "CategoryPaths" => FALSE,
      "Assortments" => FALSE,
      "AllData" => FALSE,
      "ViewedByUserInfo" => FALSE
    ];

    return $this->API->ContentSearchRequest($term, $facets, $sorting, $count, $skip, \Drupal::languageManager()->getCurrentLanguage()->getId(), 'DKK', $this->getCurrentUserArray(), $title, $settings);
  }

  /**
   * Predict search terms for content based on partial term.
   *
   * @param $term
   * @param $count
   *
   * @return array|bool
   */
  public function searchTermPrediction($term, $count = 10) {
    $title = $this->titleResolver->getTitle($this->requestStack->getCurrentRequest(), $this->routeMatch->getRouteObject());
    return $this->API->SearchTermPredictionRequest($term, $count, \Drupal::languageManager()->getCurrentLanguage()->getId(), 'DKK', $this->getCurrentUserArray(), $title);
  }

  /**
   * Transforms a search result into entities.
   *
   * @param mixed $result
   *
   * @return EntityInterface[]
   */
  public function getSearchedContent($result) {
    $entities = [];
    if ($result !== FALSE && !empty($result['hits']) && $result['hits'] > 0) {
      $content = [];
      foreach ($result['results'] as $item) {
        $content_info = explode(':', $item['contentId']);
        $content[$content_info[0]] = empty($content[$content_info[0]]) ? [] : $content[$content_info[0]];
        $content[$content_info[0]][] = $content_info[1];
      }
      foreach ($content as $entity_type => $entity_ids) {
        try {
          $entities += $this->entityTypeManager
            ->getStorage($entity_type)
            ->loadMultiple($entity_ids);
        } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
          \Drupal::logger('relewise')->error('Error occurred while loading content from search result.');
        }
      }
    }
    return $entities;
  }

  /**
   * Returns a content ID used for content indexing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  public function getContentId(EntityInterface $entity) {
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

  protected function getContent(EntityInterface $entity) {
    $content = new Content($this->getContentId($entity));
    $langcode = $entity->language()->getId();
    if ($langcode == 'und') {
      $langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
    }
    $content->DisplayName()->addValue($entity->label(), $langcode);
    $content->addData('type', $entity->getEntityTypeId());
    $content->addData('bundle', $entity->bundle());
    $content->addData('LastUpdatedInRelewise', time());
    if ($entity instanceof EntityPublishedInterface) {
      $content->addData('published', $entity->isPublished());
    }
    if ($entity instanceof EntityChangedInterface) {
      $content->addData('changed', $entity->getChangedTime());
    }
    if ($entity instanceof EntityOwnerInterface) {
      $content->addData('owner', $entity->getOwnerId());
    }
    if ($entity instanceof NodeInterface) {
      /** @var NodeInterface $node */
      $node = $entity;
      $content->addData('created', $node->getCreatedTime());
    }

    /** @var \Drupal\path_alias\AliasManagerInterface $aliasManager */
    $aliasManager = \Drupal::service('path_alias.manager');
    $alias = $aliasManager->getAliasByPath($entity->toUrl()->toString());
    $content->addPath('alias', $alias, $langcode);

    $this->moduleHandler->alter('relewise_content', $content, $entity);

    return $content;
  }

  protected function getCurrentUserArray() {
    $tempId = $this->requestStack->getCurrentRequest()->cookies->get('Drupal_visitor_relewise');
    $user = \Drupal::currentUser();
    if ($user->isAuthenticated()) {
      return [
        'AuthenticatedId' => $user->id(),
        'TemporaryId' => $tempId,
        'Email' => $user->getEmail()
      ];
    } else {
      if (empty($tempId)) {
        if ($this->requestStack->getCurrentRequest()->cookies->get('cookiebot-consent--statistics') === '0') {
          $tempId = NULL;
          user_cookie_delete('relewise');
        } else {
          $tempId = uniqid();
          user_cookie_save(['relewise' => $tempId]);
        }
      }
      return [
        'TemporaryId' => $tempId,
      ];
    }
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
}
