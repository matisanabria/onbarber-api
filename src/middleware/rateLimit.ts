import type { MiddlewareHandler } from "hono";
import type { Env } from "../index";
import { tooManyRequests } from "../lib/errors";

interface RateLimitOptions {
  bucket: string;
  limit: number;
  windowSeconds: number;
  /** Skip the check entirely when a bearer token is present (mirrors Laravel's `api` limiter). */
  bypassWithBearerToken?: boolean;
}

function clientIp(c: { req: { header: (name: string) => string | undefined } }): string {
  return c.req.header("CF-Connecting-IP") ?? "unknown";
}

/**
 * D1-backed sliding-window rate limiter. Cloudflare's native rate-limiting binding only
 * supports 10s/60s windows, but the `booking` rule needs an hourly window, so both rules
 * are implemented the same way for consistency.
 */
export function rateLimit(options: RateLimitOptions): MiddlewareHandler<{ Bindings: Env }> {
  return async (c, next) => {
    if (options.bypassWithBearerToken && c.req.header("Authorization")?.startsWith("Bearer ")) {
      await next();
      return;
    }

    const ip = clientIp(c);
    const now = Date.now();
    const windowStart = now - options.windowSeconds * 1000;

    const { results } = await c.env.DB.prepare(
      "SELECT COUNT(*) as count FROM rate_limit_hits WHERE bucket = ? AND ip = ? AND created_at_ms > ?"
    )
      .bind(options.bucket, ip, windowStart)
      .all<{ count: number }>();

    const count = results[0]?.count ?? 0;
    if (count >= options.limit) {
      throw tooManyRequests();
    }

    await c.env.DB.prepare(
      "INSERT INTO rate_limit_hits (bucket, ip, created_at_ms) VALUES (?, ?, ?)"
    )
      .bind(options.bucket, ip, now)
      .run();

    await next();
  };
}
