import { createError, getRequestHeaders, readRawBody, sendProxy } from 'h3'
import type { H3Event } from 'h3'
import { proxyHeaders } from '#shared/utils/proxyHeaders'

const PAYLOAD_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

// What a visitor sends that must not reach the API: the forwarding headers and the secret are
// this proxy's to set (a browser-sent one would spoof the client ip or the gate), and the
// rest are hop-by-hop or recomputed by fetch.
const DROPPED = /^(x-forwarded-.*|x-vercel-.*|x-real-ip|forwarded|x-pesonal-proxy|host|connection|keep-alive|upgrade|expect|transfer-encoding|content-length|accept-encoding)$/

const HOP_BY_HOP_RESPONSE = ['connection', 'keep-alive', 'transfer-encoding', 'host']

// Production only: the browser talks to this server alone, and /api and /sanctum are passed on to
// the API with the proxy secret. Without a target (local dev) the browser calls the API directly
// and these routes don't exist.
export async function proxyToApi(event: H3Event) {
    const { apiProxyTarget, apiProxySecret } = useRuntimeConfig(event)

    if (!apiProxyTarget) {
        throw createError({ statusCode: 404 })
    }

    const target = new URL(apiProxyTarget)
    const url = new URL(target.origin + event.path)

    // The secret goes to the API's own /api and /sanctum, never anywhere a crafted path points.
    if (url.origin !== target.origin || !/^\/(api|sanctum)\//.test(url.pathname)) {
        throw createError({ statusCode: 404 })
    }

    const incoming = getRequestHeaders(event)
    const headers: Record<string, string> = {}

    for (const [name, value] of Object.entries(incoming)) {
        if (value !== undefined && !DROPPED.test(name)) {
            headers[name] = value
        }
    }

    Object.assign(headers, proxyHeaders({
        headers: incoming,
        socketIp: event.node.req.socket.remoteAddress,
        onVercel: Boolean(process.env.VERCEL),
    }, apiProxySecret))

    const raw = PAYLOAD_METHODS.has(event.method) ? await readRawBody(event, false) : undefined
    // A copy, because TypeScript 5.9 won't take a Node Buffer as a fetch body (its backing
    // ArrayBufferLike could be shared memory). Bodies here are small JSON.
    const body = raw ? new Uint8Array(raw) : undefined

    // sendProxy keeps every Set-Cookie (it splits and re-sets them as a list) and leaves cookie
    // domains alone: the API's cookies are host-only, so they land on this server's host.
    // redirect: 'manual' hands the API's own 302 and Location back to the browser (the social
    // sign-in hops) instead of fetch following it here.
    return sendProxy(event, url.toString(), {
        fetchOptions: { method: event.method, headers, body, redirect: 'manual' },
        // sendProxy copies every response header; these describe the API's own connection, not
        // this one (php's dev server even answers with a Host header).
        onResponse: (proxied) => {
            for (const name of HOP_BY_HOP_RESPONSE) {
                proxied.node.res.removeHeader(name)
            }
        },
    })
}