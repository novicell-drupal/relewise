<?php

namespace Drupal\relewise\Form;

use Drupal\content_hierarchy\ContentHierarchyData;
use Drupal\content_hierarchy\ContentHierarchyWidgets;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\relewise\Relewise;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RelewiseSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\relewise\Relewise
   */
  protected $relewise;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('relewise'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, Relewise $relewise, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->relewise = $relewise;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'relewise.settings'
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'relewise_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('relewise.settings');

    $form['#tree'] = TRUE;
    $form['settings'] = [
      '#type' => 'item',
      '#open' => TRUE,
      '#title' => $this->t('Relewise settings'),
      '#description' => ''
    ];

    $options = [
      10 => 10,
      25 => 25,
      50 => 50,
      100 => 100,
      200 => 200
    ];
    $form['index_batch_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Index batch size'),
      '#description' => $this->t('How many content items to index per batch when resetting index.'),
      '#options' => $options,
      '#default_value' => $config->get('index_batch_size') ?? 50
    ];

    $form['entity_bundles'] = array(
      '#type' => 'details',
      '#title' => $this->t('Entity types'),
      '#open' => TRUE
    );

    $bundles = $config->get('entity_bundles') ?? [];
    $entity_types = $this->relewise->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      $options = [];
      foreach ($entity_type['bundles'] as $bundle_id => $bundle) {
        $options[$bundle_id] = $bundle['label'];
      }

      $form['entity_bundles'][$entity_type['id']] = array(
        '#type' => 'checkboxes',
        '#title' => $entity_type['label'],
        '#description' => $this->t(''),
        '#options' => $options,
        '#default_value' => $bundles[$entity_type['id']] ?? []
      );
    }

    $form = parent::buildform($form, $form_state);
    $form['actions']['reindex'] = [
      '#type' => 'link',
      '#attributes' => ['class' => 'button'],
      '#title' => $this->t('Re-index site'),
      '#url' => Url::fromRoute('relewise.reindex')
    ];
    $form['actions']['purge'] = [
      '#type' => 'link',
      '#attributes' => ['class' => ['button', 'button--danger']],
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('relewise.purge')
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('relewise.settings');

    $config->set('index_batch_size', $form_state->getValue('index_batch_size'));

    $bundles = [];
    $entity_types = $this->relewise->getSupportedEntityTypes();
    foreach ($entity_types as $entity_type) {
      foreach ($form_state->getValue(['entity_bundles', $entity_type['id']], []) as $bundle) {
        if (!empty($bundle)) {
          if (!isset($bundles[$entity_type['id']])) {
            $bundles[$entity_type['id']] = [];
          }
          $bundles[$entity_type['id']][] = $bundle;
        }
      }
    }
    $config->set('entity_bundles', $bundles);

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
