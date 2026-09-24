import { Hono } from "hono";
import { and, asc, eq } from "drizzle-orm";
import { drizzle } from "drizzle-orm/d1";
import type { Env } from "../index";
import { barbers, scheduleOverrides } from "../db/schema";
import { notFound, validationError } from "../lib/errors";
import { toLaravelDate, toLaravelDateTime } from "../lib/serialize";
import { barbmanAuth } from "../middleware/auth";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^\d{2}:\d{2}$/;

export const scheduleOverridesRoute = new Hono<{ Bindings: Env }>();

function serializeOverride(row: typeof scheduleOverrides.$inferSelect) {
  return {
    id: row.id,
    barber_id: row.barberId,
    date: toLaravelDate(row.date),
    is_open: row.isOpen,
    open_time: row.openTime,
    close_time: row.closeTime,
    break_start: row.breakStart,
    break_end: row.breakEnd,
    created_at: toLaravelDateTime(row.createdAt),
    updated_at: toLaravelDateTime(row.updatedAt),
  };
}

// GET /barbers/:barberId/overrides
scheduleOverridesRoute.get("/barbers/:barberId/overrides", barbmanAuth, async (c) => {
  const barberId = Number(c.req.param("barberId"));
  const db = drizzle(c.env.DB);

  const [barber] = await db.select().from(barbers).where(eq(barbers.id, barberId)).limit(1);
  if (!barber) throw notFound();

  const rows = await db
    .select()
    .from(scheduleOverrides)
    .where(eq(scheduleOverrides.barberId, barberId))
    .orderBy(asc(scheduleOverrides.date));

  return c.json(rows.map(serializeOverride));
});

// POST /schedule-overrides
scheduleOverridesRoute.post("/schedule-overrides", barbmanAuth, async (c) => {
  const body = await c.req.json().catch(() => ({}));

  const errors: Record<string, string[]> = {};
  if (!body.barber_id) errors.barber_id = ["The barber id field is required."];
  if (!body.date || !DATE_RE.test(body.date)) errors.date = ["The date field is required."];
  if (typeof body.is_open !== "boolean") errors.is_open = ["The is open field is required."];
  for (const field of ["open_time", "close_time", "break_start", "break_end"] as const) {
    if (body[field] != null && !TIME_RE.test(body[field])) {
      errors[field] = [`The ${field.replace("_", " ")} field format is invalid.`];
    }
  }
  if (Object.keys(errors).length > 0) throw validationError(errors);

  const db = drizzle(c.env.DB);
  const barberId = Number(body.barber_id);

  const [barber] = await db.select().from(barbers).where(eq(barbers.id, barberId)).limit(1);
  if (!barber) throw validationError({ barber_id: ["The selected barber id is invalid."] });

  const [existing] = await db
    .select()
    .from(scheduleOverrides)
    .where(and(eq(scheduleOverrides.barberId, barberId), eq(scheduleOverrides.date, body.date)))
    .limit(1);

  const values = {
    barberId,
    date: body.date as string,
    isOpen: body.is_open as boolean,
    openTime: body.open_time ?? null,
    closeTime: body.close_time ?? null,
    breakStart: body.break_start ?? null,
    breakEnd: body.break_end ?? null,
  };

  let row: typeof scheduleOverrides.$inferSelect;
  if (existing) {
    [row] = await db
      .update(scheduleOverrides)
      .set({ ...values, updatedAt: new Date().toISOString().replace("T", " ").slice(0, 19) })
      .where(eq(scheduleOverrides.id, existing.id))
      .returning();
  } else {
    [row] = await db.insert(scheduleOverrides).values(values).returning();
  }

  return c.json(serializeOverride(row), 201);
});

// DELETE /schedule-overrides/:id
scheduleOverridesRoute.delete("/schedule-overrides/:id", barbmanAuth, async (c) => {
  const id = Number(c.req.param("id"));
  const db = drizzle(c.env.DB);

  const [existing] = await db.select().from(scheduleOverrides).where(eq(scheduleOverrides.id, id)).limit(1);
  if (!existing) throw notFound();

  await db.delete(scheduleOverrides).where(eq(scheduleOverrides.id, id));

  return c.json({ message: "Override removed." });
});
