<?php

namespace App\Core\Traits;

use Inertia\Inertia;

trait HandleInertia
{
    protected function rendering(string $view, array $data = []): \Inertia\Response
    {
        return Inertia::render($view, $data);
    }
}
