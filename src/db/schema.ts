import { sql } from "drizzle-orm";
import { sqliteTable, integer, text, uniqueIndex } from "drizzle-orm/sqlite-core";

export const barbers = sqliteTable("barbers", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  name: text("name").notNull(),
  phone: text("phone"),
  photoUrl: text("photo_url"),
  active: integer("active", { mode: "boolean" }).notNull().default(true),
  createdAt: text("created_at").notNull().default(sql`CURRENT_TIMESTAMP`),
  updatedAt: text("updated_at").notNull().default(sql`CURRENT_TIMESTAMP`),
});

export const schedules = sqliteTable(
  "schedules",
  {
    id: integer("id").primaryKey({ autoIncrement: true }),
    barberId: integer("barber_id")
      .notNull()
      .references(() => barbers.id, { onDelete: "cascade" }),
    dayOfWeek: integer("day_of_week").notNull(), // 0=Sunday … 6=Saturday
    isOpen: integer("is_open", { mode: "boolean" }).notNull().default(true),
    openTime: text("open_time"),
    closeTime: text("close_time"),
    breakStart: text("break_start"),
    breakEnd: text("break_end"),
    createdAt: text("created_at").notNull().default(sql`CURRENT_TIMESTAMP`),
    updatedAt: text("updated_at").notNull().default(sql`CURRENT_TIMESTAMP`),
  },
  (t) => [uniqueIndex("schedules_barber_id_day_of_week_unique").on(t.barberId, t.dayOfWeek)]
);

export const scheduleOverrides = sqliteTable(
  "schedule_overrides",
  {
    id: integer("id").primaryKey({ autoIncrement: true }),
    barberId: integer("barber_id")
      .notNull()
      .references(() => barbers.id, { onDelete: "cascade" }),
    date: text("date").notNull(), // YYYY-MM-DD
    isOpen: integer("is_open", { mode: "boolean" }).notNull(),
    openTime: text("open_time"),
    closeTime: text("close_time"),
    breakStart: text("break_start"),
    breakEnd: text("break_end"),
    createdAt: text("created_at").notNull().default(sql`CURRENT_TIMESTAMP`),
    updatedAt: text("updated_at").notNull().default(sql`CURRENT_TIMESTAMP`),
  },
  (t) => [uniqueIndex("schedule_overrides_barber_id_date_unique").on(t.barberId, t.date)]
);

export const appointments = sqliteTable("appointments", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  barberId: integer("barber_id")
    .notNull()
    .references(() => barbers.id, { onDelete: "cascade" }),
  clientName: text("client_name").notNull(),
  clientPhone: text("client_phone").notNull(),
  appointmentDate: text("appointment_date").notNull(), // YYYY-MM-DD
  appointmentTime: text("appointment_time").notNull(), // HH:MM:SS
  status: text("status", { enum: ["pending", "confirmed", "cancelled", "completed"] })
    .notNull()
    .default("pending"),
  createdAt: text("created_at").notNull().default(sql`CURRENT_TIMESTAMP`),
  updatedAt: text("updated_at").notNull().default(sql`CURRENT_TIMESTAMP`),
});

export const rateLimitHits = sqliteTable("rate_limit_hits", {
  id: integer("id").primaryKey({ autoIncrement: true }),
  bucket: text("bucket").notNull(),
  ip: text("ip").notNull(),
  createdAtMs: integer("created_at_ms").notNull(),
});
