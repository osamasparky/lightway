<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use App\Models\Affiliate;
use App\Models\Verification;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{

    public function checkConfirmed($user = null, $username, $value)
    {
        $disableRegistrationVerificationProcess = getGeneralOptionsSettings('disable_registration_verification_process');

        if (!empty($disableRegistrationVerificationProcess)) {
            return [
                'status' => 'verified'
            ];
        }

        if (!empty($value)) {
            $value = ltrim($value, '+');

            $verification = Verification::where($username, $value)
                ->where('expired_at', '>', time())
                ->where(function ($query) {
                    $query->whereNull('user_id')
                        ->orWhereHas('user');
                })
                ->first();

            $data = [];
            $time = time();

            if (!empty($verification)) {
                if (!empty($verification->verified_at)) {
                    return [
                        'status' => 'verified'
                    ];

                } else {
                    $data['created_at'] = $time;
                    $data['expired_at'] = $time + Verification::EXPIRE_TIME;

                    if (time() > $verification->expired_at) {
                        $data['code'] = $this->getNewCode();
                    } else {
                        $data['code'] = $verification->code;
                    }
                }
            } else {
                $data[$username] = $value;
                $data['code'] = $this->getNewCode();
                $data['user_id'] = !empty($user) ? $user->id : (auth('api')->check() ? auth()->id() : null);
                $data['created_at'] = $time;
                $data['expired_at'] = $time + Verification::EXPIRE_TIME;
            }

            $data['verified_at'] = null;

            $verification = Verification::updateOrCreate([$username => $value], $data);

            try {
                if ($username == 'mobile') {
                    $verification->sendSMSCode();
                } else {
                    $verification->sendEmailCode();
                }
            } catch (\Exception $exception) {
            }

            return [
                'status' => 'send'
            ];
        }

        abort(404);
    }


    public function confirmCode(Request $request, $username = null)
    {

        $value = $username;
        if (!$username) {
            $value = $request->input('username');
        }
        $code = $request->get('code');
        $username = $this->username($value);
        $request[$username] = $value;
        $time = time();

        // Codes are 5 digits: stop guessing after 5 wrong codes for the same email/mobile.
        $limiterKey = 'api-verification:' . strtolower(ltrim((string)$value, '+'));

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return apiResponse2(0, 'too_many_attempts', trans('auth.throttle', ['seconds' => RateLimiter::availableIn($limiterKey)]));
        }

        $verifiedRows = Verification::where($username, ltrim($value, '+'))
            ->whereNull('verified_at')
            ->where('code', $code)
            ->where('created_at', '>', $time - 24 * 60 * 60)
            ->update([
                'verified_at' => $time,
                'expired_at' => $time + 50,
            ]);

        if ($verifiedRows) {
            RateLimiter::clear($limiterKey);
        } else {
            RateLimiter::hit($limiterKey, 15 * 60);
        }

        $rules = [
            'code' => [
                'required',
                Rule::exists('verifications')->where(function ($query) use ($value, $code, $time, $username) {
                    $query->where($username, $value)
                        ->where('code', $code)
                        ->whereNotNull('verified_at')
                        ->where('expired_at', '>', $time);
                }),
            ],
        ];

        if ($username == 'mobile') {
            $rules['mobile'] = 'required';
            $value = ltrim($value, '+');
        } else {
            $rules['email'] = 'required|email';
        }

        validateParam($request->all(), $rules);
        $authUser = auth('api')->check() ? auth('api')->user() : null;
        $referralCode = $request->input('referral_code', null);
        // dd($authUser);
        if (empty($authUser)) {
            $authUser = User::where($username, $value)
                ->first();
            $loginController = new LoginController();


            if (!empty($authUser)) {
                if (!empty($referralCode)) {
                    Affiliate::storeReferral($authUser, $referralCode);
                }
                $authUser->update([
                    'status' => User::$active,
                ]);
                return apiResponse2(1, 'verified', trans('api.auth.verified'));
            }

            // return $loginController->sendFailedLoginResponse($request);
        }

    }

    private function username($value)
    {
        $username = 'email';
        $email_regex = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i";

        if (preg_match($email_regex, $value)) {
            $username = 'email';
        } elseif (is_numeric($value)) {
            $username = 'mobile';
        }
        return $username;
    }

    private function getNewCode()
    {
        return random_int(10000, 99999);
    }
}
