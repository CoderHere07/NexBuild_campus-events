import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useAuth } from '../auth';
import { Alert, fieldError } from '../ui';

export default function Login() {
  const [mode, setMode] = useState('login');
  const [f, setF] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const { login } = useAuth();
  const nav = useNavigate();
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

  const validate = () => {
    const e = {};
    if (mode === 'register' && !f.name.trim()) e.name = ['Name is required.'];
    if (!/^\S+@\S+\.\S+$/.test(f.email)) e.email = ['Enter a valid email address.'];
    if (!f.password) e.password = ['Password is required.'];
    else if (mode === 'register' && f.password.length < 8) e.password = ['Use at least 8 characters.'];
    if (mode === 'register' && f.password !== f.password_confirmation) e.password_confirmation = ['Passwords do not match.'];
    return e;
  };

  const submit = async (ev) => {
    ev.preventDefault();
    const e = validate();
    setErrors(e); setError('');
    if (Object.keys(e).length) return;
    setBusy(true);
    try {
      const body = mode === 'login' ? { email: f.email, password: f.password } : f;
      const r = await api(`/auth/${mode}`, { method: 'POST', body });
      login(r.token, r.user);
      nav(r.user.role === 'organizer' ? '/dashboard' : '/');
    } catch (err) {
      setErrors(err.errors); setError(err.message);
    } finally { setBusy(false); }
  };

  const Field = ({ name, label, type = 'text', auto }) => (
    <label>
      <span>{label}</span>
      <input type={type} value={f[name]} onChange={set(name)} autoComplete={auto}
        aria-invalid={!!fieldError(errors, name)} aria-describedby={`${name}-err`} />
      <small id={`${name}-err`} className="err">{fieldError(errors, name)}</small>
    </label>
  );

  return (
    <form className="panel narrow" onSubmit={submit} noValidate>
      <h1>{mode === 'login' ? 'Log in' : 'Create student account'}</h1>
      <Alert>{error}</Alert>
      {mode === 'register' && Field({ name: 'name', label: 'Full name', auto: 'name' })}
      {Field({ name: 'email', label: 'Email', type: 'email', auto: 'email' })}
      {Field({ name: 'password', label: 'Password', type: 'password', auto: mode === 'login' ? 'current-password' : 'new-password' })}
      {mode === 'register' && Field({ name: 'password_confirmation', label: 'Confirm password', type: 'password', auto: 'new-password' })}
      <button disabled={busy}>{busy ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Sign up'}</button>
      <p>
        <button type="button" className="link" onClick={() => { setMode(mode === 'login' ? 'register' : 'login'); setErrors({}); setError(''); }}>
          {mode === 'login' ? 'New student? Create an account' : 'Have an account? Log in'}
        </button>
      </p>
      {mode === 'login' && <p className="hint">Demo: student1@campus.test or organizer@campus.test — password: <code>password</code></p>}
    </form>
  );
}
