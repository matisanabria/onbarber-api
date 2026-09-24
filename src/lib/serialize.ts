/** Laravel/Carbon serializes date-cast columns as midnight-UTC ISO8601 with microsecond precision. */
export function toLaravelDate(dateStr: string): string {
  return `${dateStr}T00:00:00.000000Z`;
}

/** SQLite CURRENT_TIMESTAMP yields "YYYY-MM-DD HH:MM:SS" (UTC) — reshape to match Carbon's toJSON(). */
export function toLaravelDateTime(sqliteTimestamp: string): string {
  return `${sqliteTimestamp.replace(" ", "T")}.000000Z`;
}
