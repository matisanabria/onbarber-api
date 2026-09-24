import { Hono } from "hono";
import { cors } from "hono/cors";
import { ApiError } from "./lib/errors";
import { barbersRoute } from "./routes/barbers";
import { appointmentsRoute } from "./routes/appointments";
import { scheduleOverridesRoute } from "./routes/scheduleOverrides";

export interface Env {
  DB: D1Database;
  BARBMAN_TOKEN: string;
  CORS_ALLOWED_ORIGINS: string;
}

const app = new Hono<{ Bindings: Env }>();

app.use("*", async (c, next) => {
  const allowed = c.env.CORS_ALLOWED_ORIGINS.split(",").map((o) => o.trim());
  return cors({ origin: allowed })(c, next);
});

app.get("/up", (c) => c.body(null, 200));

const api = new Hono<{ Bindings: Env }>();

api.route("/barbers", barbersRoute);
api.route("/appointments", appointmentsRoute);
api.route("/", scheduleOverridesRoute);

app.route("/api", api);

app.onError((err, c) => {
  if (err instanceof ApiError) {
    return c.json(
      err.errors ? { message: err.message, errors: err.errors } : { message: err.message },
      err.status as 400
    );
  }

  console.error(err);
  return c.json({ message: "Error interno del servidor." }, 500);
});

app.notFound((c) => c.json({ message: "Not Found." }, 404));

export default app;
