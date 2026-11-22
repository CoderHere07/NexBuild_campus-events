export function StatusBadge({ status }) {
  const map = {
    confirmed: ['Confirmed', 'ok'],
    waitlisted: ['Waitlisted', 'warn'],
    cancelled: ['Cancelled', 'muted'],
    draft: ['Draft', 'muted'],
    published: ['Published', 'ok'],
    closed: ['Closed', 'bad'],
  };
  const [label, tone] = map[status] || [status, 'muted'];
  return <span className={`badge ${tone}`}>{label}</span>;
}

export const Alert = ({ kind = 'error', children }) =>
  children ? <div className={`alert ${kind}`} role={kind === 'error' ? 'alert' : 'status'}>{children}</div> : null;

export const Loading = ({ label = 'Loading…' }) => <p className="state" role="status">{label}</p>;

export const Empty = ({ title, children }) => (
  <div className="state empty"><h2>{title}</h2><p>{children}</p></div>
);

export const fieldError = (errors, name) => (errors?.[name] ? errors[name][0] : null);
