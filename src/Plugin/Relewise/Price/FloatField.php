<?php

namespace Drupal\relewise\Plugin\Relewise\Price;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\Annotation\BrandProvider;
use Drupal\relewise\Annotation\DisplayNameProvider;
use Drupal\relewise\Annotation\PriceProvider;
use Drupal\relewise\BrandProviderBase;
use Drupal\relewise\DisplayNameProviderBase;
use Drupal\relewise\PriceProviderBase;
use Money\Money;
use MoneyFactory;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\Brand;
use Relewise\Models\MultiCurrency;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;

/**
 * @PriceProvider(
 *  id = "float_field",
 *  title = "Float field",
 * )
 */
class FloatField extends PriceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getPrice(EntityInterface $entity): ?MultiCurrency {
    $items = $entity->get($this->getSetting('field'));
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    $value = floatval($items->first()->getValue()['value']);
    $currency = $this->getSetting('currency');
    return MultiCurrency::create(Money::$currency($value));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'field' => '',
      'currency' => 'DKK'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($this->getEntityTypeDefinition()->id(), $this->getBundleEntity()->id());
    foreach ($definitions as $field_name => $definition) {
      // TODO: Support more entity types than taxonomy
      if ($definition->getType() == 'float') {
        $options[$field_name] = $definition->getLabel();
      }
    }

    $form['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#options' => $options,
      '#default_value' => $this->getSetting('field') ?? ''
    ];

    $options = [
      'DKK' => 'DKK'
    ];
    $form['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => $options,
      '#default_value' => $this->getSetting('field') ?? ''
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [$this->getSetting('field')];
  }
}
