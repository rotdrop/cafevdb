<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Doctrine\ORM\Mapping as ORM;

trait ArrayTrait
{
    private $keys;

    /**
     * Use reflection inspection to expost all of the private keys;
     * automatically called on post-load.
     *
     * @ORM\PostLoad
     */
    protected function arrayCTOR() {
        $this->keys = (new \ReflectionClass(get_class($this)))
                    ->getProperties(\ReflectionProperty::IS_PRIVATE);

        $this->keys = array_map(function($property) {
            $doc = $property->getDocComment();
            $name = $property->getName();
            if (preg_match('/@ORM\\\\(Column|(Many|One)To(Many|One))/', $doc)) {
                return $name;
            }
            return false;
        }, $this->keys);

        unset($this->keys['keys']);
        $this->keys = array_filter($this->keys);
    }

    public function offsetExists($offset):bool {
        if (empty($this->keys)) {
            $this->arrayCTOR();
        }
        $offset = strtolower((string)$offset);
        return is_array($this->keys) && in_array($offset, $this->keys);
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
