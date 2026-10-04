<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * トークンの持ち主。疎通確認と、トークンの権限の確認に使う。
 */
class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return (new UserResource($request->user()))->additional([
            'token' => [
                'name' => $request->user()->currentAccessToken()->name,
                'abilities' => $request->user()->currentAccessToken()->abilities,
            ],
        ]);
    }
}
