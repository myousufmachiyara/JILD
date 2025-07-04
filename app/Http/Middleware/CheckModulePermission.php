<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    public function handle($request, Closure $next, $permission)
    {

        $action = $request->route()->getActionMethod();

        // Map actions to logical permissions
        $map = [
            'store'  => 'create',
            'update' => 'edit',
            'destroy' => 'delete',
        ];

        $base = explode('.', $permission)[0]; // e.g. 'projects' from 'projects.create'

        $mappedAction = $map[$action] ?? $action;

        $finalPermission = "$base.$mappedAction";

        if (!auth()->user()->can($finalPermission)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);

    }
}
