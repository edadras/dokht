<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** ردّ کار کارگاه: چه کسی چه کرد. */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = Activity::query()
            ->with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->query('user')))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->query('subject')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('workshop.activity', [
            'activities' => $activities,
            'members' => User::query()->where('workshop_id', $request->user()->workshop_id)->orderBy('name')->get(),
            'subjects' => Activity::SUBJECTS,
            'actions' => Activity::ACTIONS,
            'filters' => $request->only(['user', 'subject', 'action']),
        ]);
    }
}
