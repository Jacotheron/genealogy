<?php

declare(strict_types=1);

// ----------------------------------------------------------------
// Settings helper function
// ----------------------------------------------------------------
if (! function_exists('settings')) {
    /**
     * @throws Illuminate\Contracts\Container\CircularDependencyException
     * @throws Psr\Container\NotFoundExceptionInterface
     * @throws Illuminate\Container\EntryNotFoundException
     * @throws Psr\Container\ContainerExceptionInterface
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app('settings');
        }

        return app('settings')->get($key, $default);
    }
}
