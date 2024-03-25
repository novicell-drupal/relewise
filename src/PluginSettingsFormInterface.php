<?php

namespace Drupal\relewise;

use Drupal\Core\Form\FormStateInterface;

interface PluginSettingsFormInterface extends PluginSettingsInterface {

  /**
   * Returns a form to configure settings for the provider.
   *
   * Invoked from \Drupal\field_ui\Form\EntityDisplayFormBase to allow
   * administrators to configure the provider. The relewise module takes care
   * of handling submitted form values.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the provider settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Returns a short summary for the current provider settings.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the provider has configurable settings.
   *
   * @return string[]
   *   A short summary of the provider settings.
   */
  public function settingsSummary();
}
