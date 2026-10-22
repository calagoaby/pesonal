<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

// Forwarded headers are believed only on a request that passed RequireProxySecret, which runs
// first. Trusting by REMOTE_ADDR can't work here: that's Render's router, which every caller
// reaches, so "trust the caller" would believe anyone's X-Forwarded-For. The secret is the
// proof the request came through Vercel. Without a secret (local) nothing is trusted, as before.
class TrustProxies extends Middleware
{
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        // The parent's handle() has already reset the trusted list to empty, so returning here
        // trusts nothing — including its config('trustedproxy.proxies') and Laravel Cloud fallbacks.
        if ($request->attributes->get(RequireProxySecret::PASSED) !== true) {
            return;
        }

        $this->proxies = config('pesonal.trusted_proxies');

        parent::setTrustedProxyIpAddresses($request);
    }
}
