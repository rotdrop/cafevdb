<?php


enum Foo: string
{
  case FOO = 'bar';
  case BAR = 'foo';
}

enum Blah
{
  case FOO;
  case BAR;
}

print_r(FOO::cases());
print_r(BLAH::cases());
