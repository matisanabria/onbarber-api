import type { MiddlewareHandler } from "hono";
import type { Env } from "../index";
import { unauthorized } from "../lib/errors";

function timingSafeEqual(a: string, b: string): boolean {
  const enc = new TextEncoder();
  const bufA = enc.encode(a);
  const bufB = enc.encode(b);
  if (bufA.length !== bufB.length) return false;
  let diff = 0;
  for (let i = 0; i < bufA.length; i++) diff |= bufA[i] ^ bufB[i];
  return diff === 0;
}

export const barbmanAuth: MiddlewareHandler<{ Bindings: Env }> = async (c, next) => {
  const authHeader = c.req.header("Authorization") ?? "";
  const token = authHeader.startsWith("Bearer ") ? authHeader.slice(7) : null;
  const expected = c.env.BARBMAN_TOKEN;

  if (!token || !expected || !timingSafeEqual(expected, token)) {
    throw unauthorized();
  }

  await next();
};
