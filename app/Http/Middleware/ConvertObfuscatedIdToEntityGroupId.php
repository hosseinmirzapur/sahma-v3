<?php

namespace App\Http\Middleware;

use App\Models\EntityGroup;
use App\Models\Folder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ConvertObfuscatedIdToEntityGroupId
{
  /**
   * Handle an incoming request.
   *
   * @param Request $request
   * @param Closure(Request): (Response) $next
   * @return Response
   * @throws ValidationException
   */
    public function handle(Request $request, Closure $next): Response
    {
        $entityGroupObfuscatedId = $request->route('fileId');

        if (is_null($entityGroupObfuscatedId)) {
            throw ValidationException::withMessages(['message' => 'entity_group_obfuscate_id is required']);
        }

        $entityGroupId = EntityGroup::convertObfuscatedIdToEntityGroupId(strval($entityGroupObfuscatedId));

        $entityGroup = EntityGroup::query()->find($entityGroupId);

        if (is_null($entityGroup)) {
            abort(404, 'FILE_NOT_FOUND');
        }
        $request->attributes->add(['entityGroup' => $entityGroup]);

        return $next($request);
    }
}
