<?php

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Panel\AgoraController;
use App\Http\Resources\SessionResource;
use App\Mixins\Logs\UserLoginHistoryMixin;
use App\Models\AgoraHistory;
use App\Models\Api\WebinarChapter;
use App\Models\File;
use App\Models\Sale;
use App\Models\Api\Session;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function show($id)
    {
        $session = Session::where('id', $id)
            ->where('status', WebinarChapter::$chapterActive)->first();
        abort_unless($session, 404);
        if ($error = $session->canViewError()) {
            return $this->failure($error, 403, 403);
        }
        $resource = new SessionResource($session);
        return apiResponse2(1, 'retrieved', trans('api.public.retrieved'), $resource);
    }

    public function BigBlueButton(Request $request, $session_id)
    {
        $user = $this->webJoinUser($request);

        if (empty($user)) {
            abort(403);
        }

        Auth::login($user, true);

        $userLoginHistoryMixin = new UserLoginHistoryMixin();
        $userLoginHistoryMixin->storeUserLoginHistory($user);

        return redirect(url('panel/sessions/' . $session_id . '/joinToBigBlueButton'));

    }

    public function agora(Request $request, $session_id)
    {
        $user = $this->webJoinUser($request);

        if (empty($user)) {
            abort(403);
        }

        Auth::login($user, true);

        $userLoginHistoryMixin = new UserLoginHistoryMixin();
        $userLoginHistoryMixin->storeUserLoginHistory($user);

        return redirect(url('panel/sessions/' . $session_id . '/joinToAgora'));
    }

    /**
     * The user a browser join link belongs to: a signed, unexpired link (built in
     * Session::getJoinLink) or a valid API token. Anything else is refused.
     */
    private function webJoinUser(Request $request)
    {
        if ($request->hasValidSignature() and !empty($request->query('user'))) {
            return User::find($request->query('user'));
        }

        return apiAuth();
    }
}
