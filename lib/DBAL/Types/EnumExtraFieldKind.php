<?php
namespace OCA\CAFEVDB\DBAL\Types;

class EnumExtraFieldKind extends EnumType
{
    protected $name = 'enumextrafieldkind';
    protected $values = ['simple','single','multiple','parallel','groupofpeople','groupsofpeople'];
}
