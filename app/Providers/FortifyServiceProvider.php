<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\LoginUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Models\System\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function(Request $request){
            $request->validate(["captcha" => "required|captcha_api:" . request("captcha_key")]);
            $request->validate([
                "username" => "required|string",
                "password" => "required"
            ]);

            /** @var $user \App\Models\System\User|null */
            $user = User::where("username", $request->input("username"))->first();

            abort_if(
                empty($user),
                422,
                __("auth.failed")
            );

            abort_if(
                !$user->checkUserPassword($request->input('password')),
                422,
                __("auth.failed")
            );

            abort_if(
                !$user->is_active,
                422,
                __("auth.inactive")
            );

            abort_if(
                !$user->is_admin && empty($user->currentProfile),
                422,
                __("auth.incomplete_user")
            );

            return $user;
        });

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);

        RateLimiter::for('login', function (Request $request) {
            $username = (string)$request->username;

            return Limit::perMinute(10)->by($username . $request->ip());
        });
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
