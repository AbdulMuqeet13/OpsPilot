<?php

namespace App\Http\Middleware;

use App\Actions\Settings\RotateWorkspaceApiKey;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWorkspaceApiKey
{
    /**
     * Resolve the workspace from "Authorization: Bearer ops_…" and expose it as the "workspace" request attribute.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $workspace = is_string($token) && str_starts_with($token, RotateWorkspaceApiKey::PREFIX)
            ? Workspace::query()->where('api_key_hash', RotateWorkspaceApiKey::hash($token))->first()
            : null;

        if ($workspace === null) {
            return response()->json(['message' => 'Missing or invalid API key. Send it as "Authorization: Bearer <key>".'], 401);
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
