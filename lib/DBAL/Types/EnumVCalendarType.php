<?php
namespace OCA\CAFEVDB\DBAL\Types;

class EnumVCalendarType extends EnumType
{
    protected $name = 'enumvcalendartype';
    protected $values = ['VEVENT','VTODO','VJOURNAL','VCARD'];
}
