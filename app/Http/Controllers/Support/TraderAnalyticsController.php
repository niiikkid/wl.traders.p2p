<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Admin\TraderAnalyticsController as AdminTraderAnalyticsController;

class TraderAnalyticsController extends AdminTraderAnalyticsController
{
    protected function getPageComponent(): string
    {
        return 'Support/TraderAnalytics/Index';
    }

    protected function getIndexRouteName(): string
    {
        return 'support.traders-analytics.index';
    }

    protected function getUpdateThresholdRouteName(): string
    {
        return 'support.traders-analytics.operations-threshold.update';
    }

    protected function getSearchTradersRouteName(): string
    {
        return 'support.traders-analytics.traders.search';
    }

    protected function getDashboardRouteName(): ?string
    {
        return null;
    }
}
