<?php
namespace OCA\CAFEVDB\DBAL\Types;

class EnumMemberStatus extends EnumType
{
    protected $name = 'enummemberstatus';
    protected $values = ['regular','passive','soloist','conductor','temporary'];
}
