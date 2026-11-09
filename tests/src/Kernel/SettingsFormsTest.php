<?php

namespace Drupal\Tests\relewise\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\relewise\Form\RelewiseSettingsForm;
use Drupal\relewise_autocomplete\Form\SettingsForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that settings forms can be created and built using core services.
 *
 * @group relewise
 */
#[Group('relewise')]
#[RunTestsInSeparateProcesses]
class SettingsFormsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'node', 'text', 'filter',
    'relewise', 'relewise_autocomplete',
  ];

  /**
   * The existing Relewise settings do not have configuration schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Tests the index settings form without making requests to Relewise.
   */
  public function testIndexSettings(): void {
    $this->installSchema('relewise', ['relewise_queue']);
    $this->config('relewise.settings')->set('index_batch_size', 100)->save();
    $form = RelewiseSettingsForm::create($this->container)
      ->buildForm([], new FormState());

    $this->assertSame(100, $form['index_batch_size']['#default_value']);
    $this->assertSame(100, $form['index_progress']['#percent']);
    $this->assertSame('submit', $form['actions']['submit']['#type']);
  }

  /**
   * Tests that autocomplete settings retain their configured defaults.
   */
  public function testAutocompleteSettings(): void {
    $this->config('relewise_autocomplete.settings')
      ->set('multi_search', ['content' => 'content'])
      ->set('content', ['count' => 6])
      ->save();
    $form = SettingsForm::create($this->container)
      ->buildForm([], new FormState());

    $this->assertSame(['content' => 'content'], $form['multi_search']['#default_value']);
    $this->assertSame(6, $form['content']['count']['#default_value']);
    $this->assertSame('submit', $form['actions']['submit']['#type']);
  }

}
