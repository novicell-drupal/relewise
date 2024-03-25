<?php

namespace Drupal\relewise\Plugin\Relewise\DisplayName;

use Drupal\Core\Entity\EntityInterface;
use Drupal\relewise\Annotation\DisplayNameProvider;
use Drupal\relewise\DisplayNameProviderBase;
use Relewise\Models\Multilingual;
use Relewise\Models\MultilingualValue;

/**
 * @DisplayNameProvider(
 *  id = "entity_label",
 *  title = "Entity label",
 * )
 */
class EntityLabel extends DisplayNameProviderBase {

  public function getMultilingualDisplayName(EntityInterface $entity): ?Multilingual {
    return Multilingual::create(
      MultilingualValue::create(
        $this->relewise->languageFromEntity($entity),
        $entity->label()
      )
    );
  }

  public function getStringDisplayName(EntityInterface $entity): ?string {
    return $entity->label();
  }

}
