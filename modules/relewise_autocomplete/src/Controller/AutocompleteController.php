<?php

namespace Drupal\relewise_autocomplete\Controller;

use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\relewise\Relewise;
use Drupal\relewise\RelewiseIndexer;
use Drupal\relewise\RelewiseTracker;
use Drupal\relewise_autocomplete\RelewiseAutocomplete;
use Relewise\Models\CategoryNameAndIdResult;
use Relewise\Models\CategoryPathResult;
use Relewise\Models\ContentResult;
use Relewise\Models\ContentSearchSettings;
use Relewise\Models\SelectedContentPropertiesSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Relewise module routes.
 */
class AutocompleteController extends ControllerBase {

  protected RelewiseAutocomplete $autocomplete;

  public function __construct(RelewiseAutocomplete $autocomplete) {
    $this->autocomplete = $autocomplete;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise.autocomplete')
    );
  }

  public function searchContent($term) {
    $searchResponse = $this->autocomplete->searchContent($term);
    $results = $this->autocomplete->processContentSearchResponse($searchResponse);
    return new JsonResponse($results);
  }

  public function searchProduct($term) {
    $searchResponse = $this->autocomplete->searchProduct($term);
    $results = $this->autocomplete->processProductSearchResponse($searchResponse);
    return new JsonResponse($results);
  }

  public function searchMulti($term) {
    $searchResponse = $this->autocomplete->multiSearch($term);
    $results = $this->autocomplete->processSearchResponseCollection($searchResponse);
    return new JsonResponse($results);
  }
}
