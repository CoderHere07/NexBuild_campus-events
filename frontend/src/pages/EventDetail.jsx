import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, fmt } from '../api';
import { useAuth } from '../auth';
import { Alert, Loading, StatusBadge } from '../ui';

export default function EventDetail() {
  const { id } = useParams();
  const { token, user } = useAuth();
  const [event, setEvent] = useState(null);
  const [error, setError] = useState('');
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api(`/events/${id}`, { token }).then(setEvent).catch((e) => setError(e.status === 404 ? 'This event does not exist or is not published.' : e.message));
  }, [id, token]);
  useEffect(load, [load]);

  const act = async (fn) => {
    setBusy(true); setError(''); setMsg('');
    try { const r = await fn(); setMsg(r.message); }
    catch (e) { setError(e.message); }
    finally { setBusy(false); load(); }
  };
  const register = () => act(() => api(`/events/${id}/register`, { method: 'POST', token }));
  const cancel = () => {
    if (!window.confirm('Cancel your registration? Your seat may be given to someone else.')) return;
    act(() => api(`/registrations/${event.my_registration.id}`, { method: 'DELETE', token }));
  };

  if (error && !event) return <><Alert>{error}</Alert><Link to="/">← Back to events</Link></>;
  if (!event) return <Loading />;

  const mine = event.my_registration;
  const active = mine && mine.status !== 'cancelled';

  return (
    <article className="detail">
      <Link to="/">← All events</Link>
      <span className="chip">{event.category?.name}</span>
      <h1>{event.title}</h1>
      <p className="meta">📅 {fmt(event.starts_at)} · 📍 {event.location}</p>
      <p className="desc">{event.description}</p>

      <section className="panel" aria-labelledby="seats">
        <h2 id="seats">Seats &amp; rules</h2>
        <dl>
          <dt>Seats available</dt><dd><strong>{event.seats_available}</strong> of {event.capacity}</dd>
          <dt>Waitlist</dt><dd>{event.waitlist_count} waiting</dd>
          <dt>Register by</dt><dd>{fmt(event.registration_closes_at)}</dd>
          <dt>Cancel by</dt><dd>{fmt(event.cancellation_closes_at)}</dd>
        </dl>
        <p className="hint">If the event is full you join the waitlist. When a confirmed student cancels, the first person on the waitlist is promoted automatically.</p>
      </section>

      <Alert>{error}</Alert>
      <Alert kind="success">{msg}</Alert>

      <section className="panel" aria-labelledby="status">
        <h2 id="status">Your registration</h2>
        {!user && <p>Please <Link to="/login">log in</Link> as a student to register.</p>}
        {user?.role === 'organizer' && <p>Organizer accounts cannot register for events.</p>}
        {user?.role === 'student' && (
          <>
            {mine && <p>Status: <StatusBadge status={mine.status} />
              {mine.status === 'waitlisted' && <> — position <strong>#{mine.waitlist_position}</strong></>}</p>}
            {active ? (
              mine.can_cancel
                ? <button className="danger" onClick={cancel} disabled={busy}>Cancel registration</button>
                : <p className="hint">The cancellation window closed on {fmt(mine.cancellation_closes_at)}.</p>
            ) : event.registration_open ? (
              <button onClick={register} disabled={busy}>
                {busy ? 'Working…' : event.is_full ? 'Join waitlist' : 'Register now'}
              </button>
            ) : (
              <p className="hint">Registration is closed for this event.</p>
            )}
          </>
        )}
      </section>
    </article>
  );
}
