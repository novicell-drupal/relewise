# Relewise

This module adds integration to the Relewise service and offers direct API access for specialized implementations.

## Installation

Relewise needs two settings in order to work provided in _settings.php_.

* $settings['relewise.apikey'] for the API key;
* $settings['relewise.dataset'] for what dataset the site uses;

## Drupal features

These features are implemented out of the box and does not require custom code to utilize.

* Entity indexing. In order to get custom fields as well an alter hook needs to implemented though.
* Content view tracking. _Currently only nodes._
* User tracking with integration for permission with Cookiebot.

## Relewise service

For more custom functionality the _"relewise"_ service can be used to implement further features with the following methods:

* **isSelectedForIndexing**: Tells whether an entity is selected indexing in the config.
* **userUpdate**: Register updates to the user like logins.
* **registerContentView**: Register content view on an entity.
* **updateContentIndex**: Updates the index on a single entity.
* **updateContentIndexMultiple**: Updates the index on multiple entities at once.
* **deleteContentIndex**: Deletes an entity from the index.
* **resetIndex**: Deletes all content from index and queues reindexing. Warning: may lose tracking data.
* **searchContent**: Performs a search for content in the index.
* **getSearchedContent**: Transforms a search result into entities.
* **getContentId**: Returns a content ID used for content indexing.

## API

In case more specific and custom functionality is needed there is also a service called _"relewise.api"_ which gives direct access to the Relewise API without any adaption to Drupal.
