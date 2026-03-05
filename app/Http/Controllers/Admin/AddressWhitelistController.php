<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AddressWhitelistController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();

        return Inertia::render('Withdrawal/WhiteList/Index', compact('filters'));
    }
}
