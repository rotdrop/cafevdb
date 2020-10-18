<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

trait FactoryTrait {

    public static function create()
    {
        $name = __CLASS__;
        return new $name();
    }

}
