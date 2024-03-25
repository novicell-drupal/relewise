<?php

namespace Drupal\relewise\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\relewise\Relewise;
use Drupal\relewise\RelewiseIndexer;
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
   * @var \Drupal\relewise\RelewiseIndexer
   */
  protected $relewiseIndexer;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('relewise'),
      $container->get('relewise.indexer'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('messenger'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, Relewise $relewise, RelewiseIndexer $relewiseIndexer, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, MessengerInterface $messenger, DateFormatterInterface $dateFormatter) {
    parent::__construct($config_factory);
    $this->relewise = $relewise;
    $this->relewiseIndexer = $relewiseIndexer;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->dateFormatter = $dateFormatter;
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

    $remaining = $this->relewiseIndexer->getSizeOfIndexQueue();
    $total = $this->relewiseIndexer->getTotalSizeOfIndexQueue();
    $done = $total - $remaining;
    $percentage = $total > 0 ? floor(100 * $done / $total) : 100;
    $form['index_progress'] = [
      '#theme' => 'progress_bar',
      '#label' => $this->t('Search index progress'),
      '#percent' => $percentage,
      '#message' => $this->t('%num_indexed of %num_total indexed', ['%num_indexed' => $done, '%num_total' => $total])
    ];

    $options = [
      10 => 10,
      25 => 25,
      50 => 50,
      100 => 100,
      200 => 200,
      300 => 300,
      400 => 400,
      500 => 500
    ];
    $form['index_batch_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Index batch size'),
      '#description' => $this->t('How many content items to index per batch when resetting index.'),
      '#options' => $options,
      '#default_value' => $config->get('index_batch_size') ?? 50
    ];

    // Identical options to the ones for block caching.
    // @see \Drupal\Core\Block\BlockBase::buildConfigurationForm()
    $period = [0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400];
    $period = array_map([$this->dateFormatter, 'formatInterval'], array_combine($period, $period));
    $period[0] = '<' . $this->t('no caching') . '>';
    $form['cache_default_max_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Default cache maximum age'),
      '#default_value' => $config->get('cache_default_max_age') ?? 0,
      '#options' => $period,
    ];
    $form['cache_personal_max_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache maximum age for requests involving personalisation'),
      '#default_value' => $config->get('cache_personal_max_age') ?? 0,
      '#options' => $period,
    ];


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

    $config->set('index_batch_size', intval($form_state->getValue('index_batch_size')));
    $config->set('cache_default_max_age', intval($form_state->getValue('cache_default_max_age')));
    $config->set('cache_personal_max_age', intval($form_state->getValue('cache_personal_max_age')));

    $config->save();

    parent::submitForm($form, $form_state);
  }
}
