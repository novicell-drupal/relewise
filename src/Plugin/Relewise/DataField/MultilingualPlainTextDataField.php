<?php

namespace Drupal\relewise\Plugin\Relewise\DataField;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relewise\Annotation\DataField;
use Drupal\relewise\DataFieldBase;
use Drupal\relewise\Relewise;
use Relewise\Factory\DataValueFactory;
use Relewise\Models\DataValue;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @DataField(
 *  id = "multilingual_plain_text",
 *  label = @Translation("Multilingual plain text"),
 *  field_types = {
 *    "text",
 *    "text_long",
 *    "text_with_summary",
 *  }
 * )
 */
class MultilingualPlainTextDataField extends DataFieldBase {

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

  public function getDataValue(FieldItemListInterface $items, $langcode = NULL): ?DataValue {
    if ($items->isEmpty() || empty($items->first()) || empty($items->first()->getValue())) {
      return NULL;
    }
    return DataValueFactory::multilingual(Multilingual::create(
      MultilingualValue::create(
        $this->relewise->languageFromLanguageCode($items->getLangcode()),
        PlainTextOutput::renderFromHtml($items->first()->getValue()['value'] ?? '')
      )
    ));
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // By default, formatters are available for all fields.
    return $field_definition->isTranslatable();
  }

}
