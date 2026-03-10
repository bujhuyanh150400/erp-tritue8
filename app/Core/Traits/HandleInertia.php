<?php

namespace App\Core\Traits;

use Inertia\Inertia;

trait HandleInertia
{
    /**
     * @param string $component
     * @param array $props
     * @return \Inertia\Response
     */
    protected function rendering(string $component, array $props = []): \Inertia\Response
    {
        return inertia($component, $props);
    }
}
