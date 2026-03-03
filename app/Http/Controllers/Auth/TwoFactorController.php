<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $request->user()->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(
                collect(range(1, 8))->map(fn () => Str::random(10) . '-' . Str::random(10))->all()
            )),
        ]);

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string']);

        $google2fa = new Google2FA();
        $secret = decrypt($request->user()->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->code)) {
            return back()->withErrors(['code' => 'The provided two factor code was invalid.']);
        }

        $request->user()->update(['two_factor_confirmed_at' => now()]);

        return back()->with('success', 'Two-factor authentication has been enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    public function challenge(): Response
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = $request->user();

        if ($request->code) {
            $google2fa = new Google2FA();
            $secret = decrypt($user->two_factor_secret);

            if (!$google2fa->verifyKey($secret, $request->code)) {
                return back()->withErrors(['code' => 'The provided two factor code was invalid.']);
            }
        } elseif ($request->recovery_code) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (!in_array($request->recovery_code, $recoveryCodes)) {
                return back()->withErrors(['recovery_code' => 'The provided recovery code was invalid.']);
            }

            $recoveryCodes = array_diff($recoveryCodes, [$request->recovery_code]);
            $user->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
            ]);
        } else {
            return back()->withErrors(['code' => 'Please provide a two factor code or recovery code.']);
        }

        session()->put('two_factor_confirmed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function qrCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $google2fa = new Google2FA();
        $secret = decrypt($request->user()->two_factor_secret);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'svg' => $svg,
            'secret' => $secret,
            'recovery_codes' => json_decode(decrypt($request->user()->two_factor_recovery_codes), true),
        ]);
    }
}
