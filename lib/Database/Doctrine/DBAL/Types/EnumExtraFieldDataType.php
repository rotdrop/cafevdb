<?php
namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

class EnumExtraFieldDataType extends EnumType
{
  protected $name = 'enumextrafielddatatype';
  protected $values = [
    'text',
    'html',
    'integer',
    'float',
    'date',
    'datetime',
    'service-fee',
  ];
}
