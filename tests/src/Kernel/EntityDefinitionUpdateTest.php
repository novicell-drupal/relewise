<?php

namespace Drupal\Tests\relewise\Kernel;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests missing definition repairs without changing configuration or queues.
 *
 * @group relewise
 */
#[Group('relewise')]
#[RunTestsInSeparateProcesses]
class EntityDefinitionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'node', 'text', 'filter', 'relewise'];

  /**
   * The module does not yet provide configuration schema for its indexes.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Tests missing, partially installed, and already installed definitions.
   *
   * @dataProvider installedDefinitionsProvider
   */
  #[DataProvider('installedDefinitionsProvider')]
  public function testDefinitionRepair(array $installed): void {
    $this->container->get('module_handler')->loadInclude('relewise', 'install');
    $this->container->get('keyvalue')->get('system.schema')->set('relewise', 9001);
    $this->installSchema('relewise', ['relewise_queue']);
    $database = $this->container->get('database');
    $database->insert('relewise_queue')->fields([
      'entity_type_id' => 'node',
      'entity_id' => '123',
      'timestamp' => 123456789,
    ])->execute();
    $queue_before = $database->select('relewise_queue')->fields('relewise_queue')->execute()->fetchAll();
    $repository = $this->container->get('entity.last_installed_schema.repository');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $definitions_before = [];
    foreach ($installed as $entity_type_id) {
      $repository->setLastInstalledDefinition($entity_type_manager->getDefinition($entity_type_id));
      $definitions_before[$entity_type_id] = $repository->getLastInstalledDefinition($entity_type_id);
    }

    // Write the old configuration directly: creating a Relewise index entity
    // through normal hooks would send an API request, unrelated to this repair.
    $config_storage = $this->container->get('config.storage');
    $configs = [
      'relewise.entity_indexer.node.article.content' => [
        'id' => 'node.article.content',
        'uuid' => '2d493be4-59aa-4cc0-b41c-c861de20205e',
        'target_entity_type' => 'node',
        'target_bundle' => 'article',
        'type' => 'content',
        'data_fields' => ['existing' => ['value' => 'preserve this setting']],
      ],
      'relewise.relewise_index.existing' => [
        'id' => 'existing',
        'uuid' => '6d3cb2d9-551c-41bb-a1ad-66d16c320c0d',
        'label' => 'Existing index',
        'enabled' => TRUE,
        'languages' => ['en', 'da'],
      ],
    ];
    foreach ($configs as $name => $data) {
      $config_storage->write($name, $data);
    }
    $update_manager = $this->container->get('entity.definition_update_manager');
    foreach (array_diff(['entity_indexer', 'relewise_index'], $installed) as $entity_type_id) {
      $this->assertSame(EntityDefinitionUpdateManagerInterface::DEFINITION_CREATED, $update_manager->getChangeList()[$entity_type_id]['entity_type']);
    }

    for ($run = 0; $run < 2; $run++) {
      relewise_update_9002();
      // The translation repair must also be safe when Locale is not enabled.
      relewise_update_9003();
      foreach (['entity_indexer', 'relewise_index'] as $entity_type_id) {
        $this->assertNotNull($repository->getLastInstalledDefinition($entity_type_id));
        if (isset($definitions_before[$entity_type_id])) {
          $this->assertEquals($definitions_before[$entity_type_id], $repository->getLastInstalledDefinition($entity_type_id));
        }
        $this->assertArrayNotHasKey($entity_type_id, $update_manager->getChangeList());
      }
      foreach ($configs as $name => $data) {
        $this->assertSame($data, $config_storage->read($name));
      }
      foreach (['entity_indexer' => 'node.article.content', 'relewise_index' => 'existing'] as $entity_type_id => $id) {
        $storage = $entity_type_manager->getStorage($entity_type_id);
        $storage->resetCache();
        $this->assertNotNull($storage->load($id));
      }
      $this->assertEquals($queue_before, $database->select('relewise_queue')->fields('relewise_queue')->execute()->fetchAll());
    }
  }

  /**
   * Provides every combination of installed config entity definitions.
   */
  public static function installedDefinitionsProvider(): array {
    return [
      'both missing' => [[]],
      'indexer installed' => [['entity_indexer']],
      'index installed' => [['relewise_index']],
      'both installed' => [['entity_indexer', 'relewise_index']],
    ];
  }

}
