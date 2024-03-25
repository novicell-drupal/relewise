<?php

namespace Drupal\relewise;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class DataFieldBase extends PluginSettingsBase implements DataFieldInterface {

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setSettings($configuration['settings'] ?? []);
    $this->fieldDefinition = $configuration['field_definition'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * Returns the field definition.
   *
   * @return FieldDefinitionInterface
   *   The array of settings.
   */
  protected function getFieldDefinition() {
    return $this->fieldDefinition;
  }

  /**
   * Returns the array of field settings.
   *
   * @return array
   *   The array of settings.
   */
  protected function getFieldSettings() {
    return $this->fieldDefinition->getSettings();
  }

  /**
   * Returns the value of a field setting.
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getFieldSetting($setting_name) {
    return $this->fieldDefinition->getSetting($setting_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // By default, data fields are available for all fields.
    return TRUE;
  }
}
