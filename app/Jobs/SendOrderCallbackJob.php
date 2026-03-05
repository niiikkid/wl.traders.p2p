<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class SendOrderCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 10;

    // Время жизни блокировки (в секундах)
    private const LOCK_TTL = 120; // 30 секунд

    public function __construct(
        private Order $order,
    )
    {
        $this->onQueue('callback');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $lockKey = $this->getLockKey();

        // Пытаемся получить блокировку
        if ($this->acquireLock($lockKey)) {
            try {
                // Выполняем отправку колбека
                services()->callback()->sendForOrder($this->order);
            } finally {
                // Освобождаем блокировку в любом случае
                $this->releaseLock($lockKey);
            }
        } else {
            // Не удалось получить блокировку, пробуем позже
            // Повторно запускаем эту же задачу через некоторое время
            $this->release(10); // Повторная попытка через 10 секунд
        }
    }

    /**
     * Получение ключа блокировки для текущей сделки
     */
    private function getLockKey(): string
    {
        return 'order_callback_lock:' . $this->order->id;
    }

    /**
     * Попытка получения блокировки
     */
    private function acquireLock(string $key): bool
    {
        // Используем Redis для создания блокировки
        // SET с NX опцией гарантирует, что ключ будет установлен только если он еще не существует
        return (bool) Redis::set($key, 1, 'EX', self::LOCK_TTL, 'NX');
    }

    /**
     * Освобождение блокировки
     */
    private function releaseLock(string $key): void
    {
        Redis::del($key);
    }

    public function backoff(): array //8 попыток
    {
        return [10, 60, 120, 240, 480, 1800, 3600, 7200]; // Интервалы в секундах перед повторными попытками
    }
}
