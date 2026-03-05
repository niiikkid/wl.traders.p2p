<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNoteResource;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNoteController extends Controller
{
    /**
     * Получить список заметок о пользователе
     */
    public function index(User $user): JsonResponse
    {
        $notes = $user->notes()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => UserNoteResource::collection($notes),
        ]);
    }

    /**
     * Сохранить новую заметку о пользователе
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $note = new UserNote([
            'user_id' => $user->id,
            'content' => $request->input('content'),
            'created_by' => auth()->id(),
        ]);

        $note->save();

        return response()->json([
            'success' => true,
            'data' => new UserNoteResource($note->load('creator')),
        ]);
    }
}
