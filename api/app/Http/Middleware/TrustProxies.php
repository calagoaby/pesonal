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
    // Only these three: the proxy forwards incoming headers, so a trusted X-Forwarded-Prefix or
    // -Port from the browser would reshape url() and the signed links.
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO;

    protected function setTrustedProxyIpAddresses(Request $request)
    {
        // The parent's handle() has already reset the trusted list to empty, so returning here
        // trusts nothing — including its config('trustedproxy.proxies') and Laravel Cloud fallbacks.
        if ($request->attributes->get(RequireProxySecret::PASSED) !== true) {
            return;
        }

        // Every hop, behind the gate: the client ip is then the leftmost X-Forwarded-For entry,
        // which the proxy sets. A list of Vercel/Render ips would make it Vercel's egress, one
        // rate-limit bucket for everybody.
        $this->proxies = '*';

        parent::setTrustedProxyIpAddresses($request);
    }
}
