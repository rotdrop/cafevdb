<?php

enum Blah
{
  case FOO;
  case BAR;
}

enum Foo: string
{
  case FOO = 'bar';
  case BAR = 'foo';

  public static function caseValues():array
  {
    return array_map(fn(self $case) => $case->value, self::cases());
  }
}

print_r(FOO::cases());
print_r(FOO::caseValues());
print_r(BLAH::cases());
print_r((array)Foo::FOO);

print json_encode(Foo::cases()) . PHP_EOL;

echo "{Foo::FOO->value}" . PHP_EOL;
