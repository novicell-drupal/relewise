<?php

namespace Drupal\relewise;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BrandProviderBase extends BundleBasedPluginSettingsBase implements BrandProviderInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  protected Relewise $relewise;

  function __construct(array $configuration, $plugin_id, $plugin_definition, Relewise $relewise) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->relewise = $relewise;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('relewise')
    );
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  public function settingsSummary() {
    return [];
  }

}
