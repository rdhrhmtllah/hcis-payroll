<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeResourceAccess
{
    public function handle(
        Request $request,
        Closure $next,
        string $contextCode = 'auto',
        string $pageType = 'auto',
        string $resourceType = 'auto',
        string $resourceCode = 'auto',
        string $action = 'auto',
    ): Response {
        $user = $request->user();
        if (! $user) {
            return $this->deny($request, 'Unauthorized');
        }

        [$resolvedContext, $resolvedPage, $resolvedType, $resolvedCode, $resolvedAction] = $this->resolveInput(
            $request,
            $contextCode,
            $pageType,
            $resourceType,
            $resourceCode,
            $action,
        );

        $allowed = AuthorizationScopeResolver::authorizeResource($user, [
            'contextCode' => $resolvedContext,
            'pageType' => $resolvedPage,
            'resourceType' => $resolvedType,
            'resourceCode' => $resolvedCode,
            'action' => $resolvedAction,
        ]);

        if (! $allowed) {
            return $this->deny($request, 'Anda tidak memiliki izin untuk resource ini.');
        }

        return $next($request);
    }

    private function resolveInput(
        Request $request,
        string $contextCode,
        string $pageType,
        string $resourceType,
        string $resourceCode,
        string $action,
    ): array {
        $path = '/'.ltrim(strtolower($request->path()), '/');

        $resolvedContext = $contextCode === 'auto' ? 'admin' : trim($contextCode);
        $resolvedPage =
            $pageType === 'auto' ? $this->resolvePageType($path) : $this->replaceRouteParams($request, $pageType);
        $resolvedType = $resourceType === 'auto' ? 'tab' : trim($resourceType);
        $resolvedCode =
            $resourceCode === 'auto'
                ? $this->resolveResourceCode($path)
                : $this->replaceRouteParams($request, $resourceCode);
        $resolvedAction = $action === 'auto' ? $this->resolveAction($request->method()) : trim($action);

        return [$resolvedContext, $resolvedPage, $resolvedType, $resolvedCode, $resolvedAction];
    }

    private function resolvePageType(string $path): string
    {
        if (str_starts_with($path, '/api/v1/system-auth') || $path === '/admin/system-manager') {
            return 'systemAuthManagerPage';
        }

        return 'superAdminManajemenPeranPage';
    }

    private function resolveResourceCode(string $path): string
    {
        if (str_contains($path, '/distribution')) {
            return 'distribution';
        }

        if (str_contains($path, '/permission-matrix')) {
            return 'matrix';
        }

        if (str_contains($path, '/monitoring')) {
            return 'monitoring';
        }

        if (str_contains($path, '/dashboard')) {
            return 'dashboard';
        }

        if (
            str_contains($path, '/users') ||
            str_contains($path, '/user-roles') ||
            str_contains($path, '/permissions/')
        ) {
            return 'users';
        }

        if (str_contains($path, '/roles') || str_contains($path, '/scopes') || str_contains($path, '/grup-fitur')) {
            return 'master';
        }

        return 'governance';
    }

    private function resolveAction(string $method): string
    {
        $method = strtoupper(trim($method));

        if ($method === 'GET' || $method === 'HEAD') {
            return 'VIEW';
        }

        if ($method === 'POST') {
            return 'CREATE';
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return 'EDIT';
        }

        if ($method === 'DELETE') {
            return 'DELETE';
        }

        return 'VIEW';
    }

    private function replaceRouteParams(Request $request, string $value): string
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) use ($request) {
                $param = $request->route($matches[1]);

                return $param !== null ? (string) $param : $matches[0];
            },
            $value,
        ) ?? $value;
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || str_starts_with('/'.ltrim(strtolower($request->path()), '/'), '/api/')) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }
}
