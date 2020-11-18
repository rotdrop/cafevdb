<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use Doctrine\ORM\Mapping as ORM;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

trait UuidTrait
{
    /**
     * @var \Ramsey\Uuid\UuidInterface
     *
     * @ORM\Column(name="UUID", type="uuid_binary", unique=true)
     */
    private $uuid;

    /**
     * Set uuid.
     *
     * @param string|\Ramsey\Uuid\UuidInterface $uuid
     *
     * @return Musiker
     */
    public function setUuid($uuid)
    {
        if (is_string($uuid)) {
            if (strlen($uuid) == 36) {
                $uuid = Uuid::fromString($uuid);
            } else if (strlen($uuid) == 16) {
                $uuid = Uuid::fromBytes($uuid);
            } else {
                // @Todo throw exception
                return null;
            }
        }
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Get uuid.
     *
     * @return \Ramsey\Uuid\UuidInterface
     */
    public function getUuid():UuidInterface
    {
        return $this->uuid;
    }

    /** @ORM\prePersist */
    public function prePersistUuid()
    {
        $this->ensureUuid();
    }

    /** @ORM\preUpdate */
    public function preUpdateUuid()
    {
        $this->ensureUuid();
    }

    private function ensureUuid()
    {
        if (empty($this->getUuid())) {
            $this->uuid = Uuid::uuid4();
        }
    }
}
