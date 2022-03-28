<?php
namespace Drupal\relewise;

use Drupal\Core\Site\Settings;
use Drupal\relewise\DataTypes\ContentAdministrativeAction;
use Drupal\relewise\DataTypes\ContentUpdate;
use Drupal\relewise\DataTypes\Search\Sorting\Content\ContentSorting;
use Drupal\relewise\DataTypes\UserUpdate;
use Drupal\relewise\Tracking\ContentView;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class RelewiseAPI {

  /**
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * @var string
   */
  protected string $datasetId;

  /**
   * @var string
   */
  protected string $APIKey;

  public function __construct(Client $client) {
    $this->client = $client;
    $this->datasetId = Settings::get('relewise.dataset', '');
    $this->APIKey = Settings::get('relewise.apikey', '');
  }

  public function TrackUserUpdateRequest(UserUpdate $userUpdate) {
    return $this->CallAPI('TrackUserUpdateRequest', $userUpdate->toRootArray());
  }

  /**
   * @param \Drupal\relewise\Tracking\ContentView $contentView
   *
   * @return bool
   */
  public function TrackContentViewRequest(ContentView $contentView) {
    return $this->CallAPI('TrackContentViewRequest', $contentView->toRootArray());
  }

  public function TrackContentUpdateRequest(ContentUpdate $contentUpdate) {
    return $this->CallAPI('TrackContentUpdateRequest', $contentUpdate->toRootArray());
  }

  /**
   * @param \Drupal\relewise\DataTypes\DataType[] $datatypes
   *
   * @return bool
   */
  public function BatchedTrackingRequest(array $datatypes) {
    return $this->CallAPI('BatchedTrackingRequest', $this->getItemsArray($datatypes));
  }

  /**
   * @param \Drupal\relewise\DataTypes\ContentAdministrativeAction $action
   *
   * @return bool
   */
  public function TrackContentAdministrativeActionRequest(ContentAdministrativeAction $action) {
    return $this->CallAPI('TrackContentAdministrativeActionRequest', $action->toRootArray());
  }

  /**
   * @param string $term
   * @param \Drupal\relewise\DataTypes\DataType[] $facets
   * @param int|null $take
   * @param int|null $skip
   * @param string $language
   * @param string $currency
   * @param array $user
   * @param string $displayedAtLocation
   *
   * @return array
   */
  public function ContentSearchRequest($term, array $facets, ?ContentSorting $sorting, $take, $skip, $language, $currency, array $user, $displayedAtLocation) {
    $arr = [
      "Settings" => [
        "SelectedContentProperties" => [
          "DisplayName" => TRUE,
          "CategoryPaths" => FALSE,
          "Assortments" => FALSE,
          "AllData" => FALSE,
          "ViewedByUserInfo" => FALSE
        ]
      ],
      'Language' => ['Value' => $language],
      'Currency' => ['Value' => $currency],
      'User' => $user,
      'DisplayedAtLocation' => $displayedAtLocation
    ];
    if (!empty($term)) {
      $arr['Term'] = $term;
    }
    if (!is_null($take)) {
      $arr['Take'] = $take;
    }
    if (!is_null($skip)) {
      $arr['Skip'] = $skip;
    }
    if (!empty($facets)) {
      $arr['Facets'] = $this->getItemsArray($facets);
    }
    if (!empty($sorting)) {
      $arr['Sorting'] = [
        'Value' => $sorting->toItemArray()
      ];
    }
    return $this->CallAPI('ContentSearchRequest', $arr);
  }

  /**
   * @param string $endpoint
   * @param array $contents
   *
   * @return bool|array
   */
  protected function CallAPI($endpoint, array $contents) {
    if (empty($this->APIKey) || empty($this->datasetId)) {
      return FALSE;
    }
    $uri = 'https://api.relewise.com/' . $this->datasetId . '/v1/' . $endpoint;
    try {
      $response = $this->client->post($uri, [
        'headers' => [
          'Authorization' => 'APIKey ' . $this->APIKey,
          'Content-Type'     => 'application/json',
        ],
        'body' => json_encode($contents, JSON_PRESERVE_ZERO_FRACTION),
      ]);
    } catch (ClientException $exception) {
      return FALSE;
    }
    $contents = $response->getBody()->getContents();
    if (!empty($contents)) {
      return json_decode($contents, TRUE);
    }
    return TRUE;
  }

  /**
   * @param ClientItem[] $clientItems
   */
  protected function getItemsArray(array $clientItems): array {
    $items = [
      'Items' => []
    ];
    foreach ($clientItems as $item) {
      $items['Items'][] = $item->toItemArray();
    }
    return $items;
  }

}
