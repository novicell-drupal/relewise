<?php

namespace Drupal\relewise\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\Relewise;
use Drupal\relewise\RelewiseIndexer;
use Relewise\Models\PredictionSourceType;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RelewiseIndexEditForm extends EntityForm {

  protected Relewise $relewise;

  protected RelewiseIndexer $relewiseIndexer;

  /**
   * @var \Drupal\relewise\RelewiseIndexInterface
   */
  protected $entity;

  function __construct(Relewise $relewise, RelewiseIndexer $relewiseIndexer) {
    $this->relewise = $relewise;
    $this->relewiseIndexer = $relewiseIndexer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('relewise'),
      $container->get('relewise.indexer')
    );
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = '', $bundle = '') {
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#description' => $this->t('A short name to help you identify this index in the index list.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->id(),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#machine_name' => [
        'exists' => 'Drupal\relewise\Entity\RelewiseIndex::load',
      ],
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Is the index enabled for searching?'),
      '#default_value' => $this->entity->isEnabled(),
    ];

    $form['default'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is default index'),
      '#description' => $this->t('Should this index be used if no specific index has been chosen?'),
      '#default_value' => $this->entity->isDefault(),
    ];

    $dataFields = $this->relewiseIndexer->getAllDataFields();

    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Content'),
      '#open' => TRUE
    ];
    $form['content']['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Include'), $this->t('Weight'), $this->t('Prediction Source Type'), $this->t('Parser')],
      '#empty' => $this->t('There are no fields yet.'),
      '#tableselect' => FALSE,
      '#attributes' => ['class' => ["responsive-enabled"]],
    ];
    $form['content']['table']['content_id'] = $this->buildFieldIndexRow($this->t('Id'), ['content_id'], $this->entity->getContentId(), TRUE);
    $form['content']['table']['content_display_name'] = $this->buildFieldIndexRow($this->t('Display name'), ['content_display_name'], $this->entity->getContentDisplayName(), TRUE);
    foreach ($dataFields['content'] as $field => $info) {
      $form['content']['table'][$field] = $this->buildFieldIndexRow($info['label'], [
        'content_data',
        $field
      ], $this->entity->getContentData()[$field] ?? []);
    }

    $form['product'] = [
      '#type' => 'details',
      '#title' => $this->t('Product'),
      '#open' => TRUE
    ];
    $form['product']['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Field'), $this->t('Include'), $this->t('Weight'), $this->t('Prediction Source Type'), $this->t('Parser')],
      '#empty' => $this->t('There are no fields yet.'),
      '#tableselect' => FALSE,
      '#attributes' => ['class' => ["responsive-enabled"]],
    ];
    $form['product']['table']['product_id'] = $this->buildFieldIndexRow($this->t('Id'), ['product_id'], $this->entity->getProductId(), TRUE);
    $form['product']['table']['product_display_name'] = $this->buildFieldIndexRow($this->t('Display name'), ['product_display_name'], $this->entity->getProductDisplayName(), TRUE);
    $form['product']['table']['product_brand_id'] = $this->buildFieldIndexRow($this->t('Brand Id'), ['product_brand', 'id'], $this->entity->getProductBrand()['id'] ?? [], TRUE);
    $form['product']['table']['product_brand_display_name'] = $this->buildFieldIndexRow($this->t('Brand name'), ['product_brand', 'display_name'], $this->entity->getProductBrand()['display_name'] ?? [], TRUE);
    foreach ($dataFields['product'] as $field => $info) {
      $form['product']['table'][$field] = $this->buildFieldIndexRow($info['label'], [
        'product_data',
        $field
      ], $this->entity->getProductData()[$field] ?? []);
    }

    $form['languages'] = [
      '#type' => 'details',
      '#title' => $this->t('Languages'),
      '#open' => TRUE
    ];
    $languages = \Drupal::languageManager()->getLanguages();
    $options = [];
    foreach ($languages as $language) {
      $options[$language->getId()] = $language->getName();
    }
    $form['languages']['list'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#parents' => ['languages'],
      '#default_value' => $this->entity->getLanguages(),
    ];


    return parent::buildForm($form, $form_state);
  }

  protected function buildFieldIndexRow($title, array $parents, array $config, $emphasis = FALSE) {
    $form = [
      '#parents' => $parents,
    ];
    $form['field'] = [
      '#markup' => $title,
    ];
    if ($emphasis) {
      $form['field']['#prefix'] = '<b>';
      $form['field']['#suffix'] = '</b>';
    }
    $form['include'] = [
      '#type' => 'checkbox',
      '#default_value' => $config['include'] ?? FALSE
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#maxlength' => 3,
      '#default_value' => $config['weight'] ?? 0
    ];
    $form['prediction'] = [
      '#type' => 'select',
      '#options' => $this->getPredictionSourceTypeOptions(),
      '#default_value' => $config['prediction'] ?? 'Disabled'
    ];
    $form['parser'] = [
      '#type' => 'select',
      '#options' => $this->getParserOptions(),
      '#default_value' => $config['parser'] ?? 'ClearText'
    ];
    return $form;
  }

  protected function getPredictionSourceTypeOptions() {
    $prediction_options = &drupal_static(__FUNCTION__, []);

    if (empty($prediction_options)) {
      foreach (PredictionSourceType::cases() as $sourceType) {
        $prediction_options[$sourceType->value] = $this->t($sourceType->name);
      }
    }

    return $prediction_options;
  }

  protected function getParserOptions() {
    $parser_options = &drupal_static(__FUNCTION__, []);

    if (empty($parser_options)) {
      $parser_options = [
        'ClearText' => $this->t('Plain text'),
        'HTML' => $this->t('HTML')
      ];
    }

    return $parser_options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\relewise\RelewiseIndexInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $entity->setDefault(!empty($form_state->getValue('default')));

    $entity->setContentId($this->optimizeFieldIndexValues($entity->getContentId()));
    $entity->setContentDisplayName($this->optimizeFieldIndexValues($entity->getContentDisplayName()));
    $entity->setContentData($this->optimizeDataFieldValues($entity->getContentData()));

    $entity->setProductId($this->optimizeFieldIndexValues($entity->getProductId()));
    $entity->setProductDisplayName($this->optimizeFieldIndexValues($entity->getProductDisplayName()));
    $brand = $entity->getProductBrand();
    $brand['id'] = $this->optimizeFieldIndexValues($brand['id'] ?? []);
    $brand['display_name'] = $this->optimizeFieldIndexValues($brand['display_name'] ?? []);
    $entity->setProductBrand($brand);
    $entity->setProductData($this->optimizeDataFieldValues($entity->getProductData()));

    $languages = [];
    foreach ($entity->getLanguages() as $langcode => $value) {
      if (!empty($value)) {
        $languages[] = $langcode;
      }
    }
    $entity->setLanguages($languages);

    return $entity;
  }

  protected function optimizeFieldIndexValues($config) {
    $config['include'] = !empty($config['include'] ?? FALSE);
    $config['weight'] = intval($config['weight'] ?? 0);
    return $config;
  }

  protected function optimizeDataFieldValues($dataFields) {
    foreach ($dataFields as $field => $config) {
      if (empty($config['include'] ?? FALSE)) {
        unset($dataFields[$field]);
      } else {
        $dataFields[$field] = $this->optimizeFieldIndexValues($config);
      }
    }
    return $dataFields;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    $this->messenger()->addMessage($this->t('Index %label saved.', [
      '%label' => $this->entity->label(),
    ]));
  }

}
