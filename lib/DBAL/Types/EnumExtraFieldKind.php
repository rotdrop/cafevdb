<?php
namespace OCA\CAFEVDB\DBAL\Types;

class EnumExtraFieldKind extends EnumType
{
    protected $name = 'enumextrafieldkind';
    protected $values = ['choices', 'surcharge', 'general', 'special'];
}
