<?php

namespace App\Services\SYSTEM\Governance\Authorization\Scope;

use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;

class ScopeApplicationEngine
{
    /**
     * Apply standard scope filter to a query builder.
     *
     * The source object parameter is reserved for future multi-source rules.
     * Keeping it now makes controller signatures stable for next iterations.
     */
    public static function apply($query, ?string $sourceObject = null)
    {
        return FunctionalRole::scope($query);
    }
}
