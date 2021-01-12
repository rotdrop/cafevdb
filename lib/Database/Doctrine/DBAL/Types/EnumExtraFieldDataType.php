<?php
namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

class EnumExtraFieldDataType extends EnumType
{
  protected $name = 'enumextrafielddatatype';
  protected $values = [ 'text', 'html', 'boolean', 'integer', 'float', 'time', 'date', 'money', 'service-fee' ];
}
