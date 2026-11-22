import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, fmt } from '../api';
import { Alert, Empty, Loading } from '../ui';

export default function Events() {
  const [q, setQ] = useState('');
  const [debouncedQ, setDebouncedQ] = useState('');
  const [category, setCategory] = useState('');
  const [page, setPage] = useState(1);
  const [categories, setCategories] = useState([]);
  const [data, setData] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => { api('/categories').then(setCategories).catch(() => {}); }, []);
  useEffect(() => {
    const t = setTimeout(() => { setDebouncedQ(q); setPage(1); }, 300);
    return () => clearTimeout(t);
  }, [q]);

  useEffect(() => {
    let live = true;
    setData(null); setError('');
    const params = new URLSearchParams({ page });
    if (debouncedQ) params.set('q', debouncedQ);
    if (category) params.set('category', category);
    api(`/events?${params}`)
      .then((d) => live && setData(d))
      .catch((e) => live && setError(e.message));
    return () => { live = false; };
  }, [debouncedQ, category, page]);

  return (
    <>
      <h1>Upcoming events</h1>
      <form className="filters" role="search" onSubmit={(e) => e.preventDefault()}>
        <label>
          <span>Search</span>
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Title, description or place" />
        </label>
        <label>
          <span>Category</span>
          <select value={category} onChange={(e) => { setCategory(e.target.value); setPage(1); }}>
            <option value="">All categories</option>
            {categories.map((c) => <option key={c.id} value={c.slug}>{c.name}</option>)}
          </select>
        </label>
      </form>

      <Alert>{error}</Alert>
      {!data && !error && <Loading />}
      {data && data.data.length === 0 && (
        <Empty title="No events found">Try a different search or clear the category filter.</Empty>
      )}

      {data && data.data.length > 0 && (
        <>
          <ul className="grid" aria-live="polite">
            {data.data.map((e) => (
              <li key={e.id} className="card">
                <span className="chip">{e.category?.name}</span>
                <h2><Link to={`/events/${e.id}`}>{e.title}</Link></h2>
                <p className="meta">📅 {fmt(e.starts_at)}<br />📍 {e.location}</p>
                <p>
                  {!e.registration_open ? <span className="badge muted">Registration closed</span>
                    : e.is_full ? <span className="badge warn">Full · waitlist ({e.waitlist_count})</span>
                    : <span className="badge ok">{e.seats_available} of {e.capacity} seats left</span>}
                </p>
              </li>
            ))}
          </ul>
          <nav className="pager" aria-label="Pagination">
            <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Previous</button>
            <span>Page {data.current_page} of {data.last_page}</span>
            <button disabled={page >= data.last_page} onClick={() => setPage(page + 1)}>Next →</button>
          </nav>
        </>
      )}
    </>
  );
}
