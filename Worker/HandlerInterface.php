<?php

namespace Laelaps\GearmanBundle\Worker;

interface HandlerInterface
{
    /**
     * @param mixed $workload Please note: serialize/unserialize objects when sending/receiving them
     *
     * @return bool true on succes
     */
    public function handle($workload);
}
