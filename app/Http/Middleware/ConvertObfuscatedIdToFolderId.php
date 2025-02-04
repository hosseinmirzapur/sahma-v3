<?php

namespace App\Http\Middleware;

use App\Models\Folder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ConvertObfuscatedIdToFolderId
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
        $folderObfuscatedId = $request->route('folderId');
        if (is_null($folderObfuscatedId)) {
            throw ValidationException::withMessages(['message' => 'folder_obfuscate_id is required']);
        }

        $folderId = Folder::convertObfuscatedIdToFolderId(strval($folderObfuscatedId));

        $folder = Folder::query()->find($folderId);
        if (is_null($folder)) {
            abort(404, 'FOLDER_NOT_FOUND');
        }

        $request->attributes->add(['folder' => $folder]);
        return $next($request);
    }
}
