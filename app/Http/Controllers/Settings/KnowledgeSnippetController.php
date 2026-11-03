<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\KnowledgeSnippetRequest;
use App\Jobs\EmbedKnowledgeSnippet;
use App\Models\KnowledgeSnippet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeSnippetController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        return Inertia::render('settings/knowledge', [
            'snippets' => $workspace->knowledgeSnippets()
                ->orderBy('title')
                ->orderBy('id')
                ->get()
                ->map(fn (KnowledgeSnippet $snippet): array => [
                    'id' => $snippet->id,
                    'title' => $snippet->title,
                    'content' => $snippet->content,
                    'isEmbedded' => $snippet->embedding !== null,
                    'updatedAt' => $snippet->updated_at?->toIso8601String(),
                ]),
            'maxSnippets' => KnowledgeSnippetRequest::MAX_SNIPPETS,
        ]);
    }

    public function store(KnowledgeSnippetRequest $request): RedirectResponse
    {
        $snippet = $request->user()->workspace->knowledgeSnippets()->create($request->safe()->only(['title', 'content']));

        EmbedKnowledgeSnippet::dispatch($snippet);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Snippet added. New reply drafts can use it straight away.']);

        return back();
    }

    public function update(KnowledgeSnippetRequest $request, KnowledgeSnippet $snippet): RedirectResponse
    {
        $snippet->fill($request->safe()->only(['title', 'content']));

        if ($snippet->isDirty(['title', 'content'])) {
            $snippet->embedding = null;
            $snippet->embedding_model = null;
            $snippet->save();
            EmbedKnowledgeSnippet::dispatch($snippet);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Snippet updated.']);

        return back();
    }

    public function destroy(Request $request, KnowledgeSnippet $snippet): RedirectResponse
    {
        Gate::authorize('manage', $snippet);

        $snippet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Snippet deleted.']);

        return back();
    }
}
