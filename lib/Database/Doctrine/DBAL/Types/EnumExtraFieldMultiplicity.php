<?php
namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

class EnumExtraFieldMultiplicity extends EnumType
{
    protected $name = 'enumextrafieldmultiplicity';
    protected $values = ['simple','single','multiple','parallel','groupofpeople','groupsofpeople'];
}
