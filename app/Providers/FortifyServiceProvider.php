<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\TbUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // VOLTIX: login cukup username + password.
        // Tenant otomatis ikut user->id_sekolah (lihat App\Support\Tenant).
        // Super admin (id_sekolah NULL) melihat semua sekolah.
        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ], [
                'username.required' => 'Username wajib diisi.',
                'password.required' => 'Password wajib diisi.',
            ]);

            /** @var TbUser|null $user */
            $user = TbUser::with(['sekolah', 'role'])
                ->whereNull('deleted_at')
                ->where('username', $request->input('username'))
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'username' => 'Username atau password salah.',
                ]);
            }

            $stored = (string) $user->password;
            $input = (string) $request->input('password');

            // Data lama db_zian menyimpan password plaintext (mis. 12345).
            // Dukung keduanya: bcrypt via Hash::check, atau plaintext legacy.
            $isBcrypt = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2');
            $valid = $isBcrypt ? Hash::check($input, $stored) : hash_equals($stored, $input);

            if (! $valid) {
                throw ValidationException::withMessages([
                    'username' => 'Username atau password salah.',
                ]);
            }

            // Upgrade otomatis password plaintext ke bcrypt agar aman ke depan.
            if (! $isBcrypt) {
                $user->password = Hash::make($input);
                $user->save();
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'username' => 'Akun nonaktif. Hubungi administrator.',
                ]);
            }

            if ($user->sekolah && ! $user->sekolah->is_active) {
                throw ValidationException::withMessages([
                    'username' => 'Sekolah/tenant nonaktif.',
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/PosLogin', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
