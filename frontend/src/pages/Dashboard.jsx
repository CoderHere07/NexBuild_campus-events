import { useCallback, useEffect, useState } from 'react';
import { api, fmt, toLocalInput } from '../api';
import { useAuth } from '../auth';
import { Alert, Empty, Loading, StatusBadge, fieldError } from '../ui';

const blank = { title: '', description: '', location: '', category_id: '', starts_at: '', registration_closes_at: '', cancellation_closes_at: '', capacity: 30 };

export default function Dashboard() {
  const { token } = useAuth();
  const [events, setEvents] = useState(null);
  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState(null);     // null = hidden, {id?, ...fields}
  const [attendeesFor, setAttendeesFor] = useState(null);
  const [error, setError] = useState('');
  const [msg, setMsg] = useState('');

  const load = useCallback(() => {
    api('/organizer/events', { token }).then(setEvents).catch((e) => setError(e.message));
  }, [token]);
  useEffect(() => { load(); api('/categories').then(setCategories); }, [load]);

  const action = async (e, verb) => {
    setError(''); setMsg('');
    try { await api(`/organizer/events/${e.id}/${verb}`, { method: 'POST', token }); setMsg(`Event ${verb === 'publish' ? 'published' : 'closed'}.`); }
    catch (err) { setError(err.message); }
    load();
  };

  return (
    <>
      <div className="row"><h1>Organizer dashboard</h1>
        <button onClick={() => { setForm({ ...blank }); setAttendeesFor(null); }}>+ New event</button></div>
      <Alert>{error}</Alert>
      <Alert kind="success">{msg}</Alert>

      {form && <EventForm key={form.id || 'new'} initial={form} categories={categories} token={token}
        onDone={(m) => { setForm(null); setMsg(m); load(); }} onCancel={() => setForm(null)} />}

      {attendeesFor && <Attendees event={attendeesFor} token={token} onClose={() => { setAttendeesFor(null); load(); }} />}

      {!events && !error && <Loading />}
      {events?.length === 0 && <Empty title="No events yet">Create your first event to get started.</Empty>}

      <div className="table-wrap">
        {events?.length > 0 && (
          <table>
            <caption className="sr">Your events</caption>
            <thead><tr><th>Event</th><th>Starts</th><th>Status</th><th>Seats</th><th>Waitlist</th><th>Actions</th></tr></thead>
            <tbody>
              {events.map((e) => (
                <tr key={e.id}>
                  <td data-label="Event"><strong>{e.title}</strong><br /><small>{e.category?.name}</small></td>
                  <td data-label="Starts">{fmt(e.starts_at)}</td>
                  <td data-label="Status"><StatusBadge status={e.status} /></td>
                  <td data-label="Seats">{e.confirmed_count} / {e.capacity}</td>
                  <td data-label="Waitlist">{e.waitlist_count}</td>
                  <td data-label="Actions" className="actions">
                    <button className="small" onClick={() => { setForm({ ...e, starts_at: toLocalInput(e.starts_at), registration_closes_at: toLocalInput(e.registration_closes_at), cancellation_closes_at: toLocalInput(e.cancellation_closes_at) }); setAttendeesFor(null); }}>Edit</button>
                    {e.status === 'draft' && <button className="small" onClick={() => action(e, 'publish')}>Publish</button>}
                    {e.status === 'published' && <button className="small danger" onClick={() => window.confirm('Close this event? Students will no longer be able to register.') && action(e, 'close')}>Close</button>}
                    <button className="small" onClick={() => { setAttendeesFor(e); setForm(null); }}>Attendees</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}

function EventForm({ initial, categories, token, onDone, onCancel }) {
  const [f, setF] = useState(initial);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const validate = () => {
    const e = {};
    ['title', 'description', 'location', 'category_id', 'starts_at', 'registration_closes_at', 'cancellation_closes_at']
      .forEach((k) => { if (!String(f[k]).trim()) e[k] = ['This field is required.']; });
    if (!(Number(f.capacity) >= 1)) e.capacity = ['Capacity must be at least 1.'];
    if (f.starts_at && f.registration_closes_at && new Date(f.registration_closes_at) > new Date(f.starts_at)) e.registration_closes_at = ['Must be on or before the start time.'];
    if (f.starts_at && f.cancellation_closes_at && new Date(f.cancellation_closes_at) > new Date(f.starts_at)) e.cancellation_closes_at = ['Must be on or before the start time.'];
    return e;
  };

  const submit = async (ev) => {
    ev.preventDefault();
    const v = validate(); setErrors(v); setError('');
    if (Object.keys(v).length) return;
    setBusy(true);
    const iso = (s) => new Date(s).toISOString();
    const body = { title: f.title, description: f.description, location: f.location, category_id: Number(f.category_id),
      capacity: Number(f.capacity), starts_at: iso(f.starts_at), registration_closes_at: iso(f.registration_closes_at), cancellation_closes_at: iso(f.cancellation_closes_at) };
    try {
      await api(f.id ? `/organizer/events/${f.id}` : '/organizer/events', { method: f.id ? 'PUT' : 'POST', body, token });
      onDone(f.id ? 'Event updated.' : 'Event created as draft. Publish it when ready.');
    } catch (err) { setErrors(err.errors); setError(err.message); }
    finally { setBusy(false); }
  };

  const input = (name, label, type = 'text') => (
    <label>
      <span>{label}</span>
      <input type={type} value={f[name]} onChange={set(name)} aria-invalid={!!fieldError(errors, name)} min={type === 'number' ? 1 : undefined} />
      <small className="err">{fieldError(errors, name)}</small>
    </label>
  );

  return (
    <form className="panel" onSubmit={submit} noValidate>
      <h2>{f.id ? 'Edit event' : 'New event'}</h2>
      <Alert>{error}</Alert>
      <div className="formgrid">
        {input('title', 'Title')}
        {input('location', 'Location')}
        <label><span>Category</span>
          <select value={f.category_id} onChange={set('category_id')} aria-invalid={!!fieldError(errors, 'category_id')}>
            <option value="">Select…</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select><small className="err">{fieldError(errors, 'category_id')}</small></label>
        {input('capacity', 'Capacity', 'number')}
        {input('starts_at', 'Starts at', 'datetime-local')}
        {input('registration_closes_at', 'Registration closes', 'datetime-local')}
        {input('cancellation_closes_at', 'Cancellation closes', 'datetime-local')}
      </div>
      <label><span>Description</span>
        <textarea rows="4" value={f.description} onChange={set('description')} aria-invalid={!!fieldError(errors, 'description')} />
        <small className="err">{fieldError(errors, 'description')}</small></label>
      <div className="actions">
        <button disabled={busy}>{busy ? 'Saving…' : 'Save event'}</button>
        <button type="button" className="secondary" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  );
}

function Attendees({ event, token, onClose }) {
  const [rows, setRows] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => { api(`/organizer/events/${event.id}/registrations`, { token }).then(setRows).catch((e) => setError(e.message)); }, [event.id, token]);

  const toggle = async (r) => {
    setError('');
    try {
      await api(`/organizer/registrations/${r.id}/attendance`, { method: 'PUT', body: { attended: !r.attended }, token });
      setRows(rows.map((x) => (x.id === r.id ? { ...x, attended: !r.attended } : x)));
    } catch (e) { setError(e.message); }
  };

  return (
    <section className="panel" aria-labelledby="att">
      <div className="row"><h2 id="att">Attendance — {event.title}</h2><button className="secondary small" onClick={onClose}>Close</button></div>
      <Alert>{error}</Alert>
      {!rows && !error && <Loading />}
      {rows?.length === 0 && <Empty title="No registrations yet">Nobody has registered for this event.</Empty>}
      {rows?.length > 0 && (
        <ul className="list">
          {rows.map((r) => (
            <li key={r.id} className="row">
              <span>{r.student.name} <small>({r.student.email})</small> <StatusBadge status={r.status} /></span>
              {r.status === 'confirmed' && (
                <label className="check"><input type="checkbox" checked={r.attended} onChange={() => toggle(r)} /> Attended</label>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
