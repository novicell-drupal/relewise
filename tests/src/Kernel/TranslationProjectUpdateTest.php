<?php

namespace Drupal\Tests\relewise\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\locale\LocaleProjectRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests repairing and rebuilding the autocomplete translation project.
 *
 * @group relewise
 */
#[Group('relewise')]
#[RunTestsInSeparateProcesses]
class TranslationProjectUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'node', 'text', 'filter', 'language', 'locale',
    'relewise', 'relewise_autocomplete',
  ];

  /**
   * Tests that old metadata is removed without changing other projects.
   */
  public function testTranslationProjectRepair(): void {
    $this->installConfig(['locale']);
    $this->container->get('module_handler')->loadInclude('relewise', 'install');
    $store = $this->container->get('keyvalue')->get('locale.project');
    $path = $this->container->get('extension.list.module')->getPath('relewise_autocomplete');
    $pattern = $path . '/translations/%language.po';

    // This is the incomplete metadata cached by the old alter hook. In Drupal
    // 11.4, reading it throws a TypeError because the project type is missing.
    $store->set('relewise_autocomplete', [
      'name' => 'relewise_autocomplete',
      'core' => 'all',
      'version' => '',
      'server_pattern' => $pattern,
      'info' => ['interface translation server pattern' => $pattern],
      'status' => 0,
    ]);
    $other_project = [
      'name' => 'other_project',
      'project_type' => 'module',
      'core' => 'all',
      'version' => '1.0',
      'server_pattern' => 'translations/%language.po',
      'status' => 0,
    ];
    $store->set('other_project', $other_project);

    for ($run = 0; $run < 2; $run++) {
      relewise_update_9003();
      $this->assertFalse($store->has('relewise_autocomplete'));
      $this->assertSame($other_project, $store->get('other_project'));
    }

    // Rebuild using Locale's project discovery, including the alter hooks.
    if ($this->container->has(LocaleProjectRepository::class)) {
      $repository = $this->container->get(LocaleProjectRepository::class);
      $repository->buildProjects();
      $project = $repository->getAll()['relewise_autocomplete']->toArray();
    }
    else {
      $this->container->get('module_handler')->loadInclude('locale', 'inc', 'locale.compare');
      locale_translation_build_projects();
      $project = $this->container->get('locale.project')->get('relewise_autocomplete');
    }
    $this->assertSame('relewise_autocomplete', $project['name']);
    $this->assertSame('module', $project['type'] ?? $project['project_type']);
    $this->assertSame($pattern, $project['server_pattern']);
    $this->assertNotEmpty($project['status']);
    $this->assertFileExists(DRUPAL_ROOT . '/' . str_replace('%language', 'da', $project['server_pattern']));
  }

}
