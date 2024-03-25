<?php

namespace Drupal\relewise\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\BrandProviderManager;
use Drupal\relewise\CategoryPathProviderManager;
use Drupal\relewise\DataFieldManager;
use Drupal\relewise\DisplayNameProviderManager;
use Drupal\relewise\PluginSettingsFormInterface;
use Drupal\relewise\PriceProviderManager;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityIndexerEditForm extends EntityForm {

  protected Relewise $relewise;

  protected DisplayNameProviderManager $displayNameManager;

  protected CategoryPathProviderManager $categoryPathManager;

  protected BrandProviderManager $brandProviderManager;

  protected PriceProviderManager $priceProviderManager;

  protected DataFieldManager $dataFieldManager;

  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * @var \Drupal\relewise\EntityIndexerInterface
   */
  protected $entity;

  protected array $exclude_fields = [];

  function __construct(Relewise $relewise, DisplayNameProviderManager $displayNameManager, CategoryPathProviderManager $categoryPathManager, BrandProviderManager $brandProviderManager, PriceProviderManager $priceProviderManager, DataFieldManager $dataFieldManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->relewise = $relewise;
    $this->displayNameManager = $displayNameManager;
    $this->categoryPathManager = $categoryPathManager;
    $this->brandProviderManager = $brandProviderManager;
    $this->priceProviderManager = $priceProviderManager;
    $this->dataFieldManager = $dataFieldManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise'),
      $container->get('plugin.relewise.display_name'),
      $container->get('plugin.relewise.category_path'),
      $container->get('plugin.relewise.brand'),
      $container->get('plugin.relewise.price'),
      $container->get('plugin.relewise.data_field'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = '', $bundle = '') {
    $form['#tree'] = TRUE;

    if ($this->entity->isNew()) {
      $this->entity->setTargetEntityTypeId($entity_type_id);
      $this->entity->setTargetBundle($bundle);
    }
    $id = $this->entity->getTargetEntityTypeId() . '.' . $this->entity->getTargetBundle() . '.' . $this->entity->getType();
    $this->entity->set('id', $id);

    $form['target_entity_type'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Target entity type'),
      '#default_value' => $this->entity->getTargetEntityTypeId(),
      '#required' => TRUE,
    ];
    $entity_type = $this->entityTypeManager->getDefinition($this->entity->getTargetEntityTypeId());
    $form['entity_type_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Entity type'),
      '#description' => $entity_type->getLabel()
    ];

    $form['target_bundle'] = [
      '#type' => 'hidden',
      '#title' => $this->t('The target bundle'),
      '#default_value' => $this->entity->getTargetBundle(),
      '#required' => TRUE,
    ];
    $entity_type_bundle = $this->entityTypeManager->getStorage($entity_type->getBundleEntityType())->load($this->entity->getTargetBundle());
    $form['bundle_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Bundle'),
      '#description' => $entity_type_bundle->label(),
    ];

    $ajax = [
      'callback' => '::configCallback',
      'event' => 'change',
      'wrapper' => 'entity-indexer-configuration',
      'progress' => [
        'type' => 'throbber',
      ],
    ];
    $form['config'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'entity-indexer-configuration'
      ],
      '#parents' => []
    ];

    $form['config']['id'] = [
      '#type' => 'hidden',
      '#title' => $this->t('ID'),
      '#default_value' => $id,
      '#required' => TRUE,
    ];

    $options = $this->relewise->getRelewiseTypes();
    if ($this->entity->isNew()) {
      $form['config']['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#default_value' => $this->entity->getType(),
        '#options' => $options,
        '#ajax' => $ajax
      ];
    } else {
      $form['config']['type'] = [
        '#type' => 'hidden',
        '#default_value' => $this->entity->getType(),
      ];
      $form['config']['type_item'] = [
        '#type' => 'item',
        '#title' => $this->t('Type'),
        '#description' => $options[$this->entity->getType()],
      ];
    }

    $form['config']['tracking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track views'),
      '#description' => $this->t('Should Relewise track every time an instance of this entity is viewed?'),
      '#default_value' => $this->entity->isTracking(),
    ];

    $plugin_config = $this->entity->getDisplayNameConfig();
    $plugin_options = $this->displayNameManager->buildConfiguration($plugin_config, $entity_type, $entity_type_bundle);
    $plugin = $this->displayNameManager->getInstance($plugin_options);
    $form['config']['display_name'] = $this->buildProviderSettings('Display name', $ajax, $plugin_config, $this->displayNameManager->getDefinitions(), $plugin, $form_state);

    if ($this->entity->isContent() || $this->entity->isProduct()) {
      $plugin_config = $this->entity->getCategoryPathConfig();
      $plugin_options = $this->categoryPathManager->buildConfiguration($plugin_config, $entity_type, $entity_type_bundle);
      $plugin = $this->categoryPathManager->getInstance($plugin_options);
      $form['config']['category_path'] = $this->buildProviderSettings('Category path', $ajax, $plugin_config, $this->categoryPathManager->getDefinitions(), $plugin, $form_state);
    } else {
      $form['config']['category_path']['plugin'] = [
        '#type' => 'hidden',
        '#default_value' => NULL,
      ];
    }

    if ($this->entity->isProduct()) {
      $plugin_config = $this->entity->getBrandConfig();
      $plugin_options = $this->brandProviderManager->buildConfiguration($plugin_config, $entity_type, $entity_type_bundle);
      $plugin = $this->brandProviderManager->getInstance($plugin_options);
      $form['config']['brand'] = $this->buildProviderSettings('Brand', $ajax, $plugin_config, $this->brandProviderManager->getDefinitions(), $plugin, $form_state);

      $plugin_config = $this->entity->getListPriceConfig();
      $plugin_options = $this->priceProviderManager->buildConfiguration($plugin_config, $entity_type, $entity_type_bundle);
      $plugin = $this->priceProviderManager->getInstance($plugin_options);
      $form['config']['list_price'] = $this->buildProviderSettings('List price', $ajax, $plugin_config, $this->priceProviderManager->getDefinitions(), $plugin, $form_state);

      $plugin_config = $this->entity->getSalesPriceConfig();
      $plugin_options = $this->priceProviderManager->buildConfiguration($plugin_config, $entity_type, $entity_type_bundle);
      $plugin = $this->priceProviderManager->getInstance($plugin_options);
      $form['config']['sales_price'] = $this->buildProviderSettings('Sales price', $ajax, $plugin_config, $this->priceProviderManager->getDefinitions(), $plugin, $form_state);
    } else {
      $form['config']['brand']['plugin'] = [
        '#type' => 'hidden',
        '#default_value' => NULL,
      ];
      $form['config']['list_price']['plugin'] = [
        '#type' => 'hidden',
        '#default_value' => NULL,
      ];
      $form['config']['sales_price']['plugin'] = [
        '#type' => 'hidden',
        '#default_value' => NULL,
      ];
    }

    $form['config']['exclude'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exclude settings'),
      '#parents' => []
    ];
    $form['config']['exclude']['exclude_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#default_value' => $this->entity->getExcludeField(),
      '#options' => $this->getExcludeFields($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle()),
    ];

    $form['config']['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Data fields')
    ];
    foreach ($this->getSupportedFields($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle()) as $field => $info) {
      $form['config']['fields'][$field] = $this->buildDataFieldSettings($field, $ajax, $info, $form_state);
    }

    return parent::buildForm($form, $form_state);
  }

  protected function buildProviderSettings($title, $ajax, array $config, array $definitions, ?PluginSettingsFormInterface $plugin, FormStateInterface $form_state) {
    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t($title . ' settings')
    ];
    $options = ['' => ' - ' . $this->t('Disabled') . ' - '];
    $plugin_id = $config['plugin'] ?? '';
    foreach ($definitions as $id => $definition) {
      $options[$id] = $this->t($definition['title']);
      if (empty($default) && $this->entity->isNew()) {
        $default = $id;
      }
    }
    $form['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t($title . ' provider'),
      '#default_value' => $plugin_id,
      '#options' => $options,
      '#ajax' => $ajax,
    ];
    if (!empty($plugin_id)) {
      if (!empty($plugin)) {
        $form['settings'] = $plugin->settingsForm([], $form_state);
      }
    }

    return $form;
  }

  protected function buildDataFieldSettings($field, $ajax, array $info, FormStateInterface $form_state) {
    $config = $this->entity->getDataFieldConfig($field);
    $plugin_id = $config['plugin'] ?? '';
    $plugin_options = $this->dataFieldManager->buildConfiguration($config, $info['definition']);
    $plugin = $this->dataFieldManager->getInstance($plugin_options);

    $form = [
      '#type' => 'details',
      '#title' => $info['title'],
      '#description' => $info['description'],
      '#open' => !empty($plugin_id)
    ];
    $form['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Plugin'),
      '#default_value' => $plugin_id,
      '#options' => $info['options'],
      '#ajax' => $ajax,
    ];
    if (!empty($plugin_id)) {
      if (!empty($plugin)) {
        $form['settings'] = $plugin->settingsForm([], $form_state);
      }
    }

    return $form;
  }

  /**
   * AJAX callback for refreshing config.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function configCallback($form, FormStateInterface $form_state) {
    return $form['config'];
  }

  protected function getSupportedFields($entity_type_id, $bundle): array {
    $fields = [];
    $this->exclude_fields = ['' => ' - ' . $this->t('Disabled') . ' - '];
    $definitions = $this->entityFieldManager
      ->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($definitions as $field_name => $definition) {
      if ($definition->getType() == 'boolean') {
        $this->exclude_fields[$field_name] = $definition->getLabel();
      }
      if ($definition->isDisplayConfigurable('view')) {
        $fields[$field_name] = [
          'title' => $definition->getLabel(),
          'description' => $definition->getDescription(),
          'definition' => $definition,
          'options' => [
            '' => ' - ' . $this->t('Disabled') . ' - ',
          ] + $this->dataFieldManager->getApplicableOptions($definition)
          ];
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\relewise\EntityIndexerInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $id = $entity->getTargetEntityTypeId() . '.' . $entity->getTargetBundle() . '.' . $entity->getType();
    $entity->set('id', $id);

    if (empty($form_state->getValue('display_name')['plugin'])) {
      $entity->setDisplayNameConfig([]);
    }
    if (empty($form_state->getValue('category_path')['plugin'])) {
      $entity->setCategoryPathConfig([]);
    }
    if (empty($form_state->getValue('brand')['plugin'])) {
      $entity->setBrandConfig([]);
    }
    if (empty($form_state->getValue('list_price')['plugin'])) {
      $entity->setListPriceConfig([]);
    }
    if (empty($form_state->getValue('sales_price')['plugin'])) {
      $entity->setSalesPriceConfig([]);
    }

    $entity->setTracking(!empty($form_state->getValue('tracking')));

    $fields = [];
    foreach ($form_state->getValue('fields', []) as $key => $field) {
      if (!empty($field) && !empty($field['plugin'])) {
        $fields[$key] = $field;
      }
    }
    $entity->setDataFields($fields);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    $this->messenger()->addMessage($this->t('Entity Indexer %label saved.', [
      '%label' => $this->entity->label(),
    ]));
  }

  protected function getExcludeFields($entity_type_id, $bundle): array {
    if (empty($this->exclude_fields)) {
      $this->getSupportedFields($entity_type_id, $bundle);
    }
    return $this->exclude_fields;
  }

}
