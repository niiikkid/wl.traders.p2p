<?php

namespace App\Support;

class LoginLogger
{
    /**
     * Флаг, указывающий, включено ли логирование входа.
     *
     * @var bool
     */
    protected $enabled = true;

    /**
     * Отключить логирование входа.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Включить логирование входа.
     *
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Проверить, включено ли логирование входа.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
} 