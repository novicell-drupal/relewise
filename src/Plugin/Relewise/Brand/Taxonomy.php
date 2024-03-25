<?php

namespace Drupal\relewise\Plugin\Relewise\Brand;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\Annotation\BrandProvider;
use Drupal\relewise\Annotation\DisplayNameProvider;
use Drupal\relewise\BrandProviderBase;
use Drupal\relewise\DisplayNameProviderBase;
use Relewise\Models\Brand;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;

/**
 * @BrandProvider(
 *  id = "taxonomy",
 *  title = "Taxonomy",
 * )
 */
class Taxonomy extends BrandProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getBrand(EntityInterface $entity): ?Brand {
    // TODO: Implement getBrand() method.
    return Brand::create('article');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['field' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($this->getEntityTypeDefinition()->id(), $this->getBundleEntity()->id());
    foreach ($definitions as $field_name => $definition) {
      // TODO: Support more entity types than taxonomy
      if ($definition->getType() == 'entity_reference' && in_array($definition->getSetting('target_type'), [
          'taxonomy_term'
        ])) {
        $options[$field_name] = $definition->getLabel();
      }
    }

    $form['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
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
