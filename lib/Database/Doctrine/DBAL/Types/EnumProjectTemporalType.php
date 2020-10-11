<?php
namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

class EnumProjectTemporalType extends EnumType
{
    protected $name = 'enumprojecttemporaltype';
    protected $values = ['temporary','permanent'];
}
