<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Shopify\ShopProvisioningService;
use App\Services\Shopify\ShopifyAuthService;
use Illuminate\Http\Request;

/**
 * Classic OAuth install/callback. Kept as a fallback/manual-install path --
 * shopify.app.toml has no use_legacy_install_flow = true, so Shopify's
 * current default ("managed installation") grants scopes and embeds the app
 * without ever calling /auth/callback in the normal case. The primary
 * provisioning path is Token Exchange in VerifyShopifySessionToken, which
 * fires on the first authenticated request from an unknown shop instead.
 */
class ShopifyAuthController extends Controller
{
    public function __construct(protected ShopifyAuthService $auth, protected ShopProvisioningService $provisioning)
    {
    }

    /**
     * Entry point Shopify hits (and the App Store "Install" link).
     * Redirects the merchant to Shopify's OAuth consent screen.
     */
    public function install(Request $request)
    {
        $shop = (string) $request->query('shop');

        abort_unless($shop && $this->auth->isValidShopDomain($shop), 400, 'Invalid shop parameter.');

        if ($request->query('hmac') && ! $this->auth->verifyHmac($request->query())) {
            abort(400, 'Invalid HMAC signature.');
        }

        $state = $this->auth->generateState();
        session(['shopify_oauth_state' => $state, 'shopify_oauth_shop' => $shop]);

        return redirect()->away($this->auth->buildInstallUrl($shop, $state));
    }

    /**
     * OAuth callback: exchanges the code for an access token, upserts the
     * shop record, registers webhooks, and hands off to the embedded app.
     */
    public function callback(Request $request)
    {
        $shop = (string) $request->query('shop');
        $code = (string) $request->query('code');
        $state = (string) $request->query('state');

        abort_unless($shop && $this->auth->isValidShopDomain($shop), 400, 'Invalid shop parameter.');
        abort_unless(! empty($code), 400, 'Missing authorization code.');
        abort_unless(
            $state && $state === session('shopify_oauth_state'),
            400,
            'Invalid OAuth state.'
        );
        abort_unless($this->auth->verifyHmac($request->query()), 400, 'Invalid HMAC signature.');

        $tokenData = $this->auth->exchangeCodeForToken($shop, $code);

        $record = Shop::query()->updateOrCreate(
            ['domain' => $shop],
            [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'access_token_expires_at' => isset($tokenData['expires_in'])
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
                'refresh_token_expires_at' => isset($tokenData['refresh_token_expires_in'])
                    ? now()->addSeconds($tokenData['refresh_token_expires_in'])
                    : null,
                'scopes' => explode(',', $tokenData['scope'] ?? ''),
                'installed_at' => now(),
                'uninstalled_at' => null,
            ]
        );

        $this->provisioning->syncShopDetails($record);
        $this->provisioning->registerWebhooks($record);

        session()->forget(['shopify_oauth_state', 'shopify_oauth_shop']);

        return redirect()->to("/?shop={$shop}&host=" . $request->query('host'));
    }
}
