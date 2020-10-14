<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

trait ArrayTrait {
    private $keys;

    /**Has to be called explicitly by the using class. */
    private function arrayCTOR() {
        $this->keys = (new \ReflectionClass(get_class($this)))
                    ->getProperties(\ReflectionProperty::IS_PRIVATE);

        $this->keys = array_map(function($property) {
            $doc = $property->getDocComment();
            $name = $property->getName();
            if (strpos($doc, '@ORM\Column') !== false) {
                return $name;
            }
            return false;
        }, $this->keys);

        unset($this->keys['keys']);
        $this->keys = array_filter($this->keys);
    }

    public function offsetExists($offset):bool {
        $offset = strtolower($offset);
        $method = self::methodName('get', $offset);
        return in_array($offset, $this->keys);
    }

    public function offsetGet($offset) {
        if ($this->offsetExists($offset)) {
            $method = self::methodName('get', $offset);
            return $this->$method();
        }
        return null;
    }

    public function offsetSet($offset, $value):void
    {
        if ($this->offsetExists($offset)) {
            $method = self::methodName('set', $offset);
            $this->$method($value);
        } else {
            throw new \Exception("$offset does not exist");
        }
    }

    public function offsetUnset($offset):void
    {
        $this->offsetSet($offset, null);
    }

    private static function methodName($prefix, $offset) {
        return $prefix . ucfirst(strtolower($offset));
    }
}
