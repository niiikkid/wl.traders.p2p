<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserTeam\StoreRequest;
use App\Http\Requests\Admin\UserTeam\UpdateRequest;
use App\Models\UserTeam;

class UserTeamController extends Controller
{
    public function index()
    {
        $teams = UserTeam::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $teams,
        ]);
    }

    public function store(StoreRequest $request)
    {
        $team = UserTeam::create([
            'name' => trim($request->validated('name')),
        ]);

        return response()->json([
            'success' => true,
            'data' => $team->loadCount('users'),
        ]);
    }

    public function update(UpdateRequest $request, UserTeam $userTeam)
    {
        $userTeam->update([
            'name' => trim($request->validated('name')),
        ]);

        return response()->json([
            'success' => true,
            'data' => $userTeam->loadCount('users'),
        ]);
    }

    public function destroy(UserTeam $userTeam)
    {
        $userTeam->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
