<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

trait FactoryTrait {

    use LogTrait;

    public static function create()
    {
        self::log(__METHOD__);
        $name = __CLASS__;
        return new $name();
    }

}
