<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserActivityLogResource;
use Inertia\Inertia;
use Inertia\Response;

class UserActivityLogController extends Controller
{
    public function index(): Response
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();
        $logs = UserActivityLogResource::collection(
            queries()->userActivityLog()->paginateForAdmin($filters)
        );

        return Inertia::render('Admin/ActivityLogs/Index', compact('logs', 'filters', 'filtersVariants'));
    }
}
