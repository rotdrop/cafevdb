<?php

class Foo
{
  public $foo;
}

$array = [ 'foo' => 'bar' ];

print_r((object)$array);
