// The headers the API needs from its proxy. Shared because both the /api proxy route and a
// server render (which calls the API directly) send them.

export const PROXY_SECRET_HEADER = 'x-pesonal-proxy'

type Visitor = {
    headers: Record<string, string | undefined>
    // The TCP peer. On Vercel that's Vercel's own infrastructure, not the visitor.
    socketIp?: string
    onVercel: boolean
}

export function proxyHeaders({ headers, socketIp, onVercel }: Visitor, secret: string): Record<string, string> {
    // Vercel sets these itself and overwrites whatever the browser sent, so they can be believed.
    // Anywhere else (the local production build) only the socket can.
    const ip = onVercel
        ? (headers['x-vercel-forwarded-for'] ?? headers['x-real-ip'])?.split(',')[0]?.trim()
        : socketIp

    const result: Record<string, string> = {
        [PROXY_SECRET_HEADER]: secret,
        // Vercel serves https only; the local build is plain http.
        'x-forwarded-proto': onVercel ? 'https' : 'http',
    }

    // Overwritten, never appended: the API takes the leftmost entry as the client.
    if (ip) {
        result['x-forwarded-for'] = ip
    }

    if (headers.host) {
        result['x-forwarded-host'] = headers.host
    }

    return result
}