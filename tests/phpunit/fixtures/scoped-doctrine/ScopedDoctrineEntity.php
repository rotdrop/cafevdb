<?php

declare(strict_types=1);

namespace OCA\CAFEVDB\Tests\ScopedDoctrine;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'scoped_doctrine_smoke')]
class ScopedDoctrineEntity
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  private ?int $id = null;

  #[ORM\Column(type: 'string', length: 255)]
  private string $name;

  public function __construct(string $name)
  {
    $this->name = $name;
  }

  public function getName():string
  {
    return $this->name;
  }
}
