<?php

// v1.0 — 2026-06-06 | Reusable email template library — CRUD (index, store, show-html, destroy)

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $templates = EmailTemplate::orderByDesc('updated_at')->get();

        return view('marketing.email-templates.index', compact('templates'));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'html' => 'required|string',
        ]);

        $template = EmailTemplate::create($data);

        return response()->json(['id' => $template->id, 'name' => $template->name]);
    }

    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return response()->json(['id' => $emailTemplate->id, 'name' => $emailTemplate->name, 'html' => $emailTemplate->html]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'html' => 'sometimes|string',
        ]);

        $emailTemplate->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $emailTemplate->delete();

        return response()->json(['ok' => true]);
    }
}
