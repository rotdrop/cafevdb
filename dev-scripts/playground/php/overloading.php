<?php

class A
{
  protected function foobar():void
  {
    $this->hello();
    exit();
  }

  public function hello():void
  {
    echo __CLASS__ . PHP_EOL;
  }
}

class B extends A
{
  public function hello():void
  {
    echo __CLASS__ . PHP_EOL;
    parent::foobar();
  }
}

$b = new B;

$b->hello();
