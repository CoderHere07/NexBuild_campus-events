import { Link, NavLink, Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth';
import Events from './pages/Events';
import EventDetail from './pages/EventDetail';
import MyRegistrations from './pages/MyRegistrations';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import { Loading } from './ui';

function RequireRole({ role, children }) {
  const { user, ready } = useAuth();
  if (!ready) return <Loading />;
  if (!user) return <Navigate to="/login" replace />;
  if (user.role !== role) return <Navigate to="/" replace />;
  return children;
}

export default function App() {
  const { user, logout } = useAuth();
  return (
    <>
      <a href="#main" className="skip">Skip to content</a>
      <header className="top">
        <div className="wrap bar">
          <Link to="/" className="brand">🎓 Campus Events</Link>
          <nav aria-label="Main">
            <NavLink to="/" end>Events</NavLink>
            {user?.role === 'student' && <NavLink to="/my-registrations">My registrations</NavLink>}
            {user?.role === 'organizer' && <NavLink to="/dashboard">Dashboard</NavLink>}
            {user ? (
              <button className="link" onClick={logout}>Log out ({user.name.split(' ')[0]})</button>
            ) : (
              <NavLink to="/login">Log in</NavLink>
            )}
          </nav>
        </div>
      </header>
      <main id="main" className="wrap">
        <Routes>
          <Route path="/" element={<Events />} />
          <Route path="/events/:id" element={<EventDetail />} />
          <Route path="/login" element={<Login />} />
          <Route path="/my-registrations" element={<RequireRole role="student"><MyRegistrations /></RequireRole>} />
          <Route path="/dashboard" element={<RequireRole role="organizer"><Dashboard /></RequireRole>} />
          <Route path="*" element={<p className="state">Page not found. <Link to="/">Back to events</Link></p>} />
        </Routes>
      </main>
    </>
  );
}
