<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Hydrators;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Internal\Hydration\AbstractHydrator, PDO;

class ColumnHydrator extends AbstractHydrator
{
    protected function hydrateAllData()
    {
        return $this->_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
