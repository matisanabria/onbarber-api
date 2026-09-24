-- Datos reales extraídos vía mariadb-dump del MariaDB de producción (2026-09-23).
-- No incluye `appointments` (data transaccional de clientes, se migra aparte en el cutover).

INSERT INTO barbers (id, name, phone, photo_url, active, created_at, updated_at) VALUES
(1, 'Kevin', '0983168022', 'https://pub-d4a51ea026f44dabb421b777eebd1025.r2.dev/kevin-photo.webp', 1, '2026-03-14 20:12:52', '2026-03-14 20:26:50');

INSERT INTO schedules (id, barber_id, day_of_week, is_open, open_time, close_time, break_start, break_end, created_at, updated_at) VALUES
(1, 1, 1, 1, '09:00:00', '20:00:00', '12:00:00', '14:00:00', '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(2, 1, 2, 1, '09:00:00', '20:00:00', '12:00:00', '14:00:00', '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(3, 1, 3, 1, '09:00:00', '20:00:00', '12:00:00', '14:00:00', '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(4, 1, 4, 1, '09:00:00', '20:00:00', '12:00:00', '14:00:00', '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(5, 1, 5, 1, '09:00:00', '20:00:00', '12:00:00', '14:00:00', '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(6, 1, 6, 1, '09:00:00', '20:00:00', NULL, NULL, '2026-03-14 20:13:12', '2026-03-14 20:13:12'),
(7, 1, 0, 1, '09:00:00', '09:00:00', NULL, NULL, '2026-03-14 20:13:12', '2026-03-14 20:13:12');

INSERT INTO schedule_overrides (id, barber_id, date, is_open, open_time, close_time, break_start, break_end, created_at, updated_at) VALUES
(7, 1, '2026-04-30', 0, NULL, NULL, NULL, NULL, '2026-04-30 23:12:19', '2026-04-30 23:12:19'),
(8, 1, '2026-05-01', 0, NULL, NULL, NULL, NULL, '2026-04-30 23:12:48', '2026-04-30 23:12:48');
