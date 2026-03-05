<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CallbackLogResource;
use Inertia\Inertia;

class CallbackLogController extends Controller
{
    /**
     * Отображает список логов колбеков
     */
    public function index()
    {
        $filters = $this->getTableFilters();

        $logs = queries()->callbackLog()->paginateForAdmin($filters);

        return Inertia::render('CallbackLogs/Index', [
            'logs' => CallbackLogResource::collection($logs),
            'filters' => $filters,
        ]);
    }
}
