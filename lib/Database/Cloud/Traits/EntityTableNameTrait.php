<?php

namespace OCA\CAFEVDB\Database\Cloud\Traits;

trait EntityTableNameTrait
{

  private function makeEntityClass()
  {
    $backSlashPos = strrpos(__CLASS__, '\\');
    $myName = substr(__CLASS__, $backSlashPos + 1);

    // construct from class-name of child
    $instanceClass = \get_class($this);
    $nameSpaces = explode('\\', $instanceClass);
    $nameSpaceIdx = count($nameSpaces) - 2;
    $classNameIdx = count($nameSpaces) - 1;

    $nameSpaces[$nameSpaceIdx] = 'Entities';
    $nameSpaces[$classNameIdx] = str_replace($myName, '', $nameSpaces[$classNameIdx]);

    $entityClass = implode('\\', $nameSpaces);

    return $entityClass;
  }

  private function makeTableName(string $appName, string $entityClass)
  {
    // construct from $entityClass
    $backSlashPos = strrpos($entityClass, '\\');
    $entityName = substr($entityClass, $backSlashPos + 1);

    // Convert camel-case to underscores
    $words = array_map(lcfirst, preg_split('/(?=[A-Z])/', $entityName));
    $tableName = $appName.implode('_', $words);

    return $tableName;
  }

}
