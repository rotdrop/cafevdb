<?php
namespace OCA\CAFEVDB\DBAL\Types;

class EnumProjectTemporalType extends EnumType
{
    protected $name = 'enumprojecttemporaltype';
    protected $values = ['temporary','permanent'];
}
