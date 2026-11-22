import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, fmt } from '../api';
import { useAuth } from '../auth';
import { Alert, Empty, Loading, StatusBadge } from '../ui';

export default function MyRegistrations() {
  const { token } = useAuth();
  const [rows, setRows] = useState(null);
  const [error, setError] = useState('');
  const [msg, setMsg] = useState('');

  const load = useCallback(() => {
    api('/my/registrations', { token }).then(setRows).catch((e) => setError(e.message));
  }, [token]);
  useEffect(load, [load]);

  const cancel = async (r) => {
    if (!window.confirm(`Cancel your registration for "${r.event.title}"?`)) return;
    setError(''); setMsg('');
    try { setMsg((await api(`/registrations/${r.id}`, { method: 'DELETE', token })).message); }
    catch (e) { setError(e.message); }
    load();
  };

  return (
    <>
      <h1>My registrations</h1>
      <Alert>{error}</Alert>
      <Alert kind="success">{msg}</Alert>
      {!rows && !error && <Loading />}
      {rows?.length === 0 && <Empty title="No registrations yet"><Link to="/">Browse events</Link> and register for one.</Empty>}
      <ul className="list">
        {rows?.map((r) => (
          <li key={r.id} className="card row">
            <div>
              <h2><Link to={`/events/${r.event.id}`}>{r.event.title}</Link></h2>
              <p className="meta">📅 {fmt(r.event.starts_at)} · 📍 {r.event.location}</p>
              <StatusBadge status={r.status} />
              {r.status === 'waitlisted' && <span> position #{r.waitlist_position}</span>}
            </div>
            {r.status !== 'cancelled' && (
              r.can_cancel
                ? <button className="danger" onClick={() => cancel(r)}>Cancel</button>
                : <span className="hint">Cancellation closed</span>
            )}
          </li>
        ))}
      </ul>
    </>
  );
}
