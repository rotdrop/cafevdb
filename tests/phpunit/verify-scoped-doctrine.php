<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor-wrapped/autoload.php';
require_once __DIR__ . '/fixtures/scoped-doctrine/ScopedDoctrineEntity.php';

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\DriverManager;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Configuration;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManager;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping\Driver\AttributeDriver;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\SchemaTool;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Tools\SchemaValidator;
use OCA\CAFEVDB\Tests\ScopedDoctrine\ScopedDoctrineEntity;

$configuration = new Configuration();
$configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/fixtures/scoped-doctrine']));
$configuration->enableNativeLazyObjects(true);

$connection = DriverManager::getConnection([
  'driver' => 'pdo_sqlite',
  'memory' => true,
]);
$entityManager = new EntityManager($connection, $configuration);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();

if (array_map(static fn($classMetadata) => $classMetadata->getName(), $metadata) !== [ScopedDoctrineEntity::class]) {
  throw new RuntimeException('Scoped Doctrine entity metadata was not discovered.');
}

$mappingErrors = (new SchemaValidator($entityManager))->validateMapping();
if ($mappingErrors !== []) {
  throw new RuntimeException('Scoped Doctrine mapping is invalid: ' . json_encode($mappingErrors, JSON_THROW_ON_ERROR));
}

(new SchemaTool($entityManager))->createSchema($metadata);

$entityManager->persist(new ScopedDoctrineEntity('Scoped Doctrine works'));
$entityManager->flush();
$entities = $entityManager->createQuery(
  'SELECT entity FROM ' . ScopedDoctrineEntity::class . ' entity'
)->getResult();

if (count($entities) !== 1 || $entities[0]->getName() !== 'Scoped Doctrine works') {
  throw new RuntimeException('Scoped Doctrine query returned unexpected results.');
}

$entityManager->getConnection()->close();
