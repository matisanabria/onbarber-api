import { Hono } from "hono";
import { and, eq } from "drizzle-orm";
import { drizzle } from "drizzle-orm/d1";
import type { Env } from "../index";
import { appointments, barbers, scheduleOverrides, schedules } from "../db/schema";
import { notFound, validationError } from "../lib/errors";
import { buildHourlySlots, dayOfWeek } from "../lib/slots";
import { rateLimit } from "../middleware/rateLimit";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

export const barbersRoute = new Hono<{ Bindings: Env }>();

const apiLimiter = rateLimit({
  bucket: "api",
  limit: 60,
  windowSeconds: 60,
  bypassWithBearerToken: true,
});

barbersRoute.get("/", apiLimiter, async (c) => {
  const db = drizzle(c.env.DB);
  const rows = await db
    .select({
      id: barbers.id,
      name: barbers.name,
      phone: barbers.phone,
      photo_url: barbers.photoUrl,
    })
    .from(barbers)
    .where(eq(barbers.active, true));

  return c.json(rows);
});

barbersRoute.get("/:id/slots", apiLimiter, async (c) => {
  const id = Number(c.req.param("id"));
  const date = c.req.query("date");

  if (!date || !DATE_RE.test(date)) {
    throw validationError({ date: ["The date field is required."] });
  }

  const db = drizzle(c.env.DB);
  const [barber] = await db
    .select()
    .from(barbers)
    .where(and(eq(barbers.id, id), eq(barbers.active, true)))
    .limit(1);

  if (!barber) throw notFound();

  const dow = dayOfWeek(date);

  const [override] = await db
    .select()
    .from(scheduleOverrides)
    .where(and(eq(scheduleOverrides.barberId, id), eq(scheduleOverrides.date, date)))
    .limit(1);

  let hours: { openTime: string; closeTime: string; breakStart: string | null; breakEnd: string | null } | null =
    null;

  if (override) {
    if (!override.isOpen) return c.json({ slots: [] });
    hours = {
      openTime: override.openTime!,
      closeTime: override.closeTime!,
      breakStart: override.breakStart,
      breakEnd: override.breakEnd,
    };
  } else {
    const [schedule] = await db
      .select()
      .from(schedules)
      .where(and(eq(schedules.barberId, id), eq(schedules.dayOfWeek, dow)))
      .limit(1);

    if (!schedule || !schedule.isOpen) return c.json({ slots: [] });
    hours = {
      openTime: schedule.openTime!,
      closeTime: schedule.closeTime!,
      breakStart: schedule.breakStart,
      breakEnd: schedule.breakEnd,
    };
  }

  const slotTimes = buildHourlySlots(hours);

  const booked = await db
    .select({ time: appointments.appointmentTime, status: appointments.status })
    .from(appointments)
    .where(and(eq(appointments.barberId, id), eq(appointments.appointmentDate, date)));

  const takenTimes = new Set(
    booked.filter((r) => r.status !== "cancelled").map((r) => r.time.slice(0, 5))
  );

  const result = slotTimes.map((time) => ({
    time,
    available: !takenTimes.has(time),
  }));

  return c.json({ slots: result });
});
