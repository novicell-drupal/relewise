<?php
namespace Drupal\relewise;

interface ClientItem {

  /**
   * @return string
   */
  public function getFullTypeId(): string;

  /**
   * @return string
   */
  public function getTypeName(): string;

  /**
   * @return string
   */
  public function getNamespace(): string;

  /**
   * @return array
   */
  public function toArray(): array;

  /**
   * @return array
   */
  public function toItemArray(): array;

  /**
   * @return array
   */
  public function toRootArray(): array;
}
