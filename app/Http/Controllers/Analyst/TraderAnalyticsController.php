<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analyst;

use App\Http\Controllers\Admin\TraderAnalyticsController as AdminTraderAnalyticsController;

class TraderAnalyticsController extends AdminTraderAnalyticsController
{
    protected function getPageComponent(): string
    {
        return 'Analyst/TraderAnalytics/Index';
    }

    protected function getIndexRouteName(): string
    {
        return 'analyst.traders-analytics.index';
    }

    protected function getUpdateThresholdRouteName(): string
    {
        return 'analyst.traders-analytics.operations-threshold.update';
    }

    protected function getSearchTradersRouteName(): string
    {
        return 'analyst.traders-analytics.traders.search';
    }
}
