import { Hono } from "hono";
import { and, asc, eq, ne } from "drizzle-orm";
import { drizzle } from "drizzle-orm/d1";
import type { Env } from "../index";
import { appointments, barbers, scheduleOverrides, schedules } from "../db/schema";
import { notFound, unprocessable, validationError } from "../lib/errors";
import { toLaravelDate, toLaravelDateTime } from "../lib/serialize";
import { dayOfWeek } from "../lib/slots";
import { barbmanAuth } from "../middleware/auth";
import { rateLimit } from "../middleware/rateLimit";

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^\d{2}:\d{2}$/;
const STATUSES = ["pending", "confirmed", "cancelled", "completed"] as const;

export const appointmentsRoute = new Hono<{ Bindings: Env }>();

function serializeAppointment(
  row: typeof appointments.$inferSelect,
  barber: { id: number; name: string } | null
) {
  return {
    id: row.id,
    barber_id: row.barberId,
    client_name: row.clientName,
    client_phone: row.clientPhone,
    appointment_date: toLaravelDate(row.appointmentDate),
    appointment_time: row.appointmentTime,
    status: row.status,
    created_at: toLaravelDateTime(row.createdAt),
    updated_at: toLaravelDateTime(row.updatedAt),
    barber,
  };
}

appointmentsRoute.get("/", barbmanAuth, async (c) => {
  const db = drizzle(c.env.DB);
  const rows = await db
    .select()
    .from(appointments)
    .orderBy(asc(appointments.appointmentDate), asc(appointments.appointmentTime));

  const barberIds = [...new Set(rows.map((r) => r.barberId))];
  const barberRows = barberIds.length
    ? await db.select({ id: barbers.id, name: barbers.name }).from(barbers)
    : [];
  const barberById = new Map(barberRows.map((b) => [b.id, b]));

  return c.json(rows.map((r) => serializeAppointment(r, barberById.get(r.barberId) ?? null)));
});

appointmentsRoute.post(
  "/",
  rateLimit({ bucket: "api", limit: 60, windowSeconds: 60, bypassWithBearerToken: true }),
  rateLimit({ bucket: "booking", limit: 5, windowSeconds: 3600 }),
  async (c) => {
    const body = await c.req.json().catch(() => ({}));

    const errors: Record<string, string[]> = {};
    if (!body.barber_id) errors.barber_id = ["The barber id field is required."];
    if (!body.client_name || typeof body.client_name !== "string" || body.client_name.length > 100)
      errors.client_name = ["The client name field is required."];
    if (!body.client_phone || typeof body.client_phone !== "string" || body.client_phone.length > 30)
      errors.client_phone = ["The client phone field is required."];
    if (!body.appointment_date || !DATE_RE.test(body.appointment_date))
      errors.appointment_date = ["The appointment date field is required."];
    if (!body.appointment_time || !TIME_RE.test(body.appointment_time))
      errors.appointment_time = ["The appointment time field is required."];

    if (Object.keys(errors).length > 0) throw validationError(errors);

    const today = new Date().toISOString().slice(0, 10);
    if (body.appointment_date < today) {
      throw validationError({
        appointment_date: ["The appointment date field must be a date after or equal to today."],
      });
    }

    const db = drizzle(c.env.DB);
    const barberId = Number(body.barber_id);
    const date: string = body.appointment_date;
    const time: string = body.appointment_time;

    const [barber] = await db
      .select()
      .from(barbers)
      .where(and(eq(barbers.id, barberId), eq(barbers.active, true)))
      .limit(1);
    if (!barber) throw notFound();

    const dow = dayOfWeek(date);

    const [override] = await db
      .select()
      .from(scheduleOverrides)
      .where(and(eq(scheduleOverrides.barberId, barberId), eq(scheduleOverrides.date, date)))
      .limit(1);

    let openTime: string;
    let closeTime: string;

    if (override) {
      if (!override.isOpen) throw unprocessable("The barber is not available on that date.");
      openTime = override.openTime!;
      closeTime = override.closeTime!;
    } else {
      const [schedule] = await db
        .select()
        .from(schedules)
        .where(and(eq(schedules.barberId, barberId), eq(schedules.dayOfWeek, dow)))
        .limit(1);
      if (!schedule || !schedule.isOpen) throw unprocessable("The barber is not available on that day.");
      openTime = schedule.openTime!;
      closeTime = schedule.closeTime!;
    }

    const slotStartMin = toMinutes(time);
    const slotEndMin = slotStartMin + 60;
    const openMin = toMinutes(openTime.slice(0, 5));
    const closeMin = toMinutes(closeTime.slice(0, 5));

    if (slotStartMin < openMin || slotEndMin > closeMin) {
      throw unprocessable("The selected time slot is outside working hours.");
    }

    const conflict = await db
      .select({ id: appointments.id })
      .from(appointments)
      .where(
        and(
          eq(appointments.barberId, barberId),
          eq(appointments.appointmentDate, date),
          eq(appointments.appointmentTime, `${time}:00`),
          ne(appointments.status, "cancelled")
        )
      )
      .limit(1);

    if (conflict.length > 0) throw unprocessable("That time slot is already taken.");

    const [created] = await db
      .insert(appointments)
      .values({
        barberId,
        clientName: body.client_name,
        clientPhone: body.client_phone,
        appointmentDate: date,
        appointmentTime: `${time}:00`,
        status: "pending",
      })
      .returning();

    return c.json(serializeAppointment(created, { id: barber.id, name: barber.name }), 201);
  }
);

appointmentsRoute.patch("/:id", barbmanAuth, async (c) => {
  const id = Number(c.req.param("id"));
  const body = await c.req.json().catch(() => ({}));

  if (!body.status || !STATUSES.includes(body.status)) {
    throw validationError({ status: ["The selected status is invalid."] });
  }

  const db = drizzle(c.env.DB);
  const [existing] = await db.select().from(appointments).where(eq(appointments.id, id)).limit(1);
  if (!existing) throw notFound();

  const [updated] = await db
    .update(appointments)
    .set({ status: body.status, updatedAt: new Date().toISOString().replace("T", " ").slice(0, 19) })
    .where(eq(appointments.id, id))
    .returning();

  const [barber] = await db
    .select({ id: barbers.id, name: barbers.name })
    .from(barbers)
    .where(eq(barbers.id, updated.barberId))
    .limit(1);

  return c.json(serializeAppointment(updated, barber ?? null));
});

function toMinutes(hhmm: string): number {
  const [h, m] = hhmm.split(":").map(Number);
  return h * 60 + m;
}
