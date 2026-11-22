# Campus Event Registration

Laravel 11/12 REST API + MySQL + React (Vite) SPA.
Students discover events, register, join waitlists and cancel inside a window. Organizers manage events, capacity and attendance in a protected dashboard.

## Setup (≈5 min)
Requirements: PHP 8.2+, Composer, Node 18+, MySQL 8.

```bash
mysql -u root -e "CREATE DATABASE campus_events CHARACTER SET utf8mb4;"
./setup.sh                                # creates Laravel in ./backend, applies ./backend-overlay, migrates + seeds
cd backend && php artisan serve           # API  -> http://127.0.0.1:8000
cd frontend && npm install && npm run dev # SPA  -> http://localhost:5173  (proxies /api to Laravel)
```
Change DB credentials in `backend/.env` if your MySQL user is not `root` with an empty password.

### Test accounts (fake data, password `password`)
| Role | Email |
|---|---|
| Organizer | organizer@campus.test (owns the seeded events), organizer2@campus.test |
| Student | student1@campus.test … student4@campus.test |

"Hackathon Kick-off (2 seats)" is seeded full with a waitlist: log in as student1 and cancel to see student3 promoted.

## Tests
`cd backend && php artisan test` — covers last-seat capacity, waitlist promotion, duplicate registration, cut-off times, cancellation window, 401/403/404 authorization, organizer ownership, role escalation on sign-up and capacity changes.

## API (JSON, `Authorization: Bearer <token>`)
| Method & path | Role | Purpose |
|---|---|---|
| POST `/api/auth/register` | public | Student sign-up (role is always `student`) |
| POST `/api/auth/login` | public | Returns `{token, user}` |
| POST `/api/auth/logout`, GET `/api/me` | any | Session |
| GET `/api/categories` | public | Category list |
| GET `/api/events?q=&category=&page=` | public | Published upcoming events with seats left |
| GET `/api/events/{id}` | public (+token) | Detail, plus `my_registration` when logged in |
| GET `/api/my/registrations` | student | Own registrations |
| POST `/api/events/{id}/register` | student | 201 confirmed or waitlisted · 404 · 409 duplicate · 422 closed |
| DELETE `/api/registrations/{id}` | student | Cancel own registration · 404 if not yours · 422 window closed |
| GET `/api/organizer/events` | organizer | Own events with counts |
| POST `/api/organizer/events` | organizer | Create (draft) |
| PUT `/api/organizer/events/{id}` | organizer | Edit; raising capacity promotes waitlist |
| POST `/api/organizer/events/{id}/publish` \| `/close` | organizer | Lifecycle |
| GET `/api/organizer/events/{id}/registrations` | organizer | Attendee list |
| PUT `/api/organizer/registrations/{id}/attendance` | organizer | Body `{attended: bool}` |

Errors: `{ "message": "...", "errors": { field: [..] } }` with 401, 403, 404, 409, 422.

## How the hard parts are solved
1. **No overbooking:** `RegistrationService` opens a DB transaction and runs `SELECT … FOR UPDATE` on the **event row** before counting seats. Concurrent register/cancel/capacity requests for one event queue behind that lock, so only one can take the final seat.
2. **Duplicates:** an explicit check inside the lock plus a DB unique index `(event_id, user_id, active)`, where `active` is `1` or `NULL` once cancelled (so re-registering after cancelling works).
3. **Consistency:** cancel + waitlist promotion (and capacity raise + promotion) run in one transaction and roll back together on failure.
4. **Authorization:** Sanctum tokens, `role:student` / `role:organizer` middleware, organizer ownership check on every event, and registration lookups scoped to the current user (someone else's id returns 404).
5. **Server-side validation:** all rules are re-checked by Laravel validators; client checks are only for UX. Cut-off times are enforced on the server clock.

## Concurrency demo
With one seat left, run in two terminals at the same moment (different student tokens):
`curl -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" http://127.0.0.1:8000/api/events/1/register`
Exactly one response is `confirmed`; the other is `waitlisted`.

## Suggested Git history
`git init && git add . && git commit` in steps: schema+models → registration service → controllers/routes → tests → React shell → student pages → organizer dashboard → docs.

## Evidence for submission
Screenshots to capture: events list (mobile + desktop), event detail (confirmed / waitlisted / cancel-window-closed), organizer dashboard, and the `php artisan test` output.
