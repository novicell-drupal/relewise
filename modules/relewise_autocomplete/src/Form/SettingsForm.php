<?php

namespace Drupal\relewise_autocomplete\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase {

  protected Relewise $relewise;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed configuration manager.
   * @param \Drupal\relewise\Relewise $relewise
   *   The Relewise service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, Relewise $relewise) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->relewise = $relewise;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('relewise')
    );
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return [
      'relewise_autocomplete.settings',
    ];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'relewise_autocomplete_settings';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $full_config = $this->config('relewise_autocomplete.settings');

    $form['#tree'] = TRUE;

    $count_options = [
      1 => 1,
      2 => 2,
      3 => 3,
      4 => 4,
      5 => 5,
      6 => 6,
      8 => 8,
      10 => 10,
      15 => 15,
      20 => 20,
    ];

    $form['multi_search'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Multi search'),
      '#description' => $this->t('What types to include in a multi search.'),
      '#options' => $this->getRelewiseTypes(),
      '#default_value' => $full_config->get('multi_search') ?? []
    ];

    foreach ($this->getRelewiseTypes() as $key => $label) {
      $config = $full_config->get($key) ?? [];
      $form[$key] = [
        '#type' => 'details',
        '#title' => $label,
        '#open' => TRUE,
      ];
      $form[$key]['index'] = [
        '#type' => 'select',
        '#title' => $this->t('Index'),
        '#description' => $this->t('What Relewise index to use.'),
        '#options' => $this->relewise->getIndexOptions(),
        '#default_value' => $config['index'] ?? '',
      ];

      $form[$key]['count'] = [
        '#type' => 'select',
        '#title' => $this->t('Number of results'),
        '#description' => $this->t('How many results to display?'),
        '#options' => $count_options,
        '#default_value' => $config['count'] ?? 10,
      ];

      $form[$key]['id'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('ID'),
        '#description' => $this->t('Include the ID in the search results.'),
        '#default_value' => $config['id'] ?? FALSE,
      ];

      $form[$key]['display_name'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display name'),
        '#description' => $this->t('Include display names in the search results.'),
        '#default_value' => $config['display_name'] ?? TRUE,
      ];

      $form[$key]['category_paths'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Category path'),
        '#description' => $this->t('Include category paths in the search results.'),
        '#default_value' => $config['category_paths'] ?? FALSE,
      ];

      $form[$key]['recommendations'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Recommendations'),
        '#description' => $this->t('Include recommendations in the search results.'),
        '#default_value' => $config['recommendations'] ?? FALSE,
      ];

      $form[$key]['href'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Url'),
        '#description' => $this->t('Include urls in the search results.'),
        '#default_value' => $config['href'] ?? TRUE,
      ];

      $form[$key]['all_data'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('All data'),
        '#description' => $this->t('Include all data in the search results.'),
        '#default_value' => $config['all_data'] ?? TRUE,
      ];
    }

    return parent::buildform($form, $form_state);
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $full_config = $this->config('relewise_autocomplete.settings');
    $full_config->setData([]);

    $multi_search = [];
    foreach ($form_state->getValue('multi_search') as $key => $value) {
      if ($value) {
        $multi_search[] = $key;
      }
    }
    $full_config->set('multi_search', $multi_search);
    foreach ($this->getRelewiseTypes() as $key => $label) {
      $config = [];
      $config['id'] = $form_state->getValue([$key, 'id']);
      $config['index'] = $form_state->getValue([$key, 'index']);
      $config['count'] = $form_state->getValue([$key, 'count']);
      $config['content_id'] = $form_state->getValue([$key, 'content_id']);
      $config['display_name'] = $form_state->getValue([$key, 'display_name']);
      $config['category_paths'] = $form_state->getValue([
        $key,
        'category_paths',
      ]);
      $config['recommendations'] = $form_state->getValue([
        $key,
        'recommendations',
      ]);
      $config['href'] = $form_state->getValue([$key, 'href']);
      $config['all_data'] = $form_state->getValue([$key, 'all_data']);
      $full_config->set($key, $config);
    }

    $full_config->save();

    parent::submitForm($form, $form_state);
  }

  protected function getRelewiseTypes() {
    $relewiseTypes = $this->relewise->getRelewiseTypes();
    unset($relewiseTypes['brand']);
    return $relewiseTypes;
  }

}
