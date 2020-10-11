<?php
namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

class EnumVCalendarType extends EnumType
{
    protected $name = 'enumvcalendartype';
    protected $values = ['VEVENT','VTODO','VJOURNAL','VCARD'];
}
