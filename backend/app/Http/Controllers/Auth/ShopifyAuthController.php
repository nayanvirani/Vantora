<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Shopify\ShopifyAuthService;
use App\Services\Shopify\ShopProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopifyAuthController extends Controller
{
    public function __construct(
        private readonly ShopifyAuthService $auth,
        private readonly ShopProvisioningService $provisioning,
    ) {}

    public function install(Request $request): RedirectResponse
    {
        $shop = (string) $request->query('shop');

        abort_unless($this->auth->isValidShopDomain($shop), 400, 'Invalid shop domain.');

        $state = $this->auth->generateState();
        $request->session()->put('shopify_oauth_state', $state);
        $request->session()->put('shopify_oauth_shop', $shop);

        $redirectUri = route('shopify.auth.callback');

        return redirect($this->auth->authorizeUrl($shop, $state, $redirectUri));
    }

    public function callback(Request $request): RedirectResponse
    {
        $shop = (string) $request->query('shop');
        $code = (string) $request->query('code');
        $state = (string) $request->query('state');

        abort_unless($this->auth->isValidShopDomain($shop), 400, 'Invalid shop domain.');
        abort_unless($this->auth->verifyCallbackHmac($request->query()), 401, 'Invalid HMAC.');
        abort_unless(
            $state && hash_equals((string) $request->session()->pull('shopify_oauth_state'), $state),
            401,
            'Invalid state.'
        );

        $token = $this->auth->exchangeCodeForToken($shop, $code);

        $this->provisioning->install($shop, $token['access_token']);

        $host = (string) $request->query('host');

        return redirect("https://{$shop}/admin/apps/".config('shopify.app_handle').'?host='.$host);
    }
}
