<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Event\LifecycleEventArgs;

class ArrayConstructor
{
    use ArrayTrait;

    /** @ORM\PostLoad */
    public function postLoad($entity, LifecycleEventArgs $event) {
        $entity->arrayCTOR();
    }
}
