<?php
namespace OCA\CAFEVDB\Database\DBAL\Types;

class EnumProjectTemporalType extends EnumType
{
    protected $name = 'enumprojecttemporaltype';
    protected $values = ['temporary','permanent'];
}
