import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import './Navbar.css';

const item = (to, label, icon) => ({ to, label, icon });
const publicLinks = [item('/', 'Home', 'house'), item('/doctors', 'Find a doctor', 'search'), item('/subscription-plans', 'Plans', 'layers')];
const roleNames = { patient: 'Patient workspace', medecin: 'Doctor workspace', admin: 'Administration' };

export default function Navbar() {
    const { user, logout, loading, authError } = useAuth();
    const [open, setOpen] = useState(false);
    const toggleRef = useRef(null);
    const location = useLocation();
    const rolePath = { patient: 'patient', medecin: 'doctor', admin: 'admin' }[user?.role];
    useEffect(() => {
        if (!open) return;
        const close = event => { if (event.key === 'Escape') { setOpen(false); toggleRef.current?.focus(); } };
        document.addEventListener('keydown', close);
        return () => document.removeEventListener('keydown', close);
    }, [open]);
    const groups = user ? [
        { title: 'Workspace', links: [item(rolePath ? `/${rolePath}/dashboard` : '/dashboard', 'Dashboard', 'grid'),
            ...(rolePath === 'admin' ? [item('/admin/users', 'Users', 'people'), item('/admin/doctors-verification', 'Doctor verification', 'patch-check'), item('/admin/reviews', 'Review moderation', 'chat-square-text')]
                : [item(`/${rolePath}/appointments`, 'Appointments', 'calendar2-week'), ...(rolePath === 'doctor' ? [item('/doctor/availability', 'Availability', 'clock'), item('/doctor/documents', 'Documents', 'file-earmark-text'), item('/doctor/statistics', 'Statistics', 'bar-chart')] : [item('/doctors', 'Find a doctor', 'search')])]) ] },
        { title: rolePath === 'admin' ? 'Management' : 'Account', links: rolePath === 'admin'
            ? [item('/admin/reports', 'Reports', 'bar-chart'), item('/admin/audit-logs', 'Audit history', 'shield-check'), item('/admin/specialities', 'Specialities', 'heart-pulse'), item('/admin/languages', 'Languages', 'translate'), item('/admin/subscription-plans', 'Plan management', 'layers')]
            : [item(`/${rolePath}/profile`, 'Profile', 'person'), item('/my-subscription', 'Subscription', 'credit-card')] },
        { title: 'Explore', links: rolePath === 'admin' ? [...publicLinks, item('/my-subscription', 'Subscription', 'credit-card')] : [item('/', 'Home', 'house'), item('/subscription-plans', 'Plans', 'layers')] },
    ] : [{ title: '', links: [...publicLinks, item('/login', 'Sign in', 'person'), item('/register', 'Create account', 'arrow-right')] }];
    const current = groups.flatMap(group => group.links).find(link => link.to === location.pathname)?.label || 'Workspace';
    const name = [user?.prenom, user?.nom].filter(Boolean).join(' ') || 'Your account';
    const initials = name.split(' ').slice(0, 2).map(part => part[0]).join('');
    const brand = <Link to="/" className="site-brand" onClick={() => setOpen(false)}><span className="brand-mark" aria-hidden="true">+</span><span>siha<span className="brand-light">tech</span><small>CARE, CONNECTED.</small></span></Link>;
    const menu = <nav id="site-navigation-links" aria-label="Main navigation" className={`site-navigation-links ${open ? 'is-open' : ''}`}>
        {groups.map(group => <div className="nav-group" key={group.title}>{group.title && <p className="nav-group-title">{group.title}</p>}{group.links.map(link => <NavLink key={link.to} to={link.to} end={link.to === '/' || link.to.endsWith('/dashboard')} onClick={() => setOpen(false)}><i aria-hidden="true" className={`bi bi-${link.icon}`} /><span>{link.label}</span></NavLink>)}</div>)}
        {user && <div className="sidebar-account"><span className="account-avatar" aria-hidden="true">{initials}</span><div><strong>{name}</strong><small>{roleNames[user.role]}</small></div><button type="button" disabled={loading} onClick={async () => { if (await logout()) setOpen(false); }} aria-label={loading ? 'Signing out…' : 'Sign out'} title="Sign out"><i aria-hidden="true" className="bi bi-box-arrow-right" /></button></div>}
    </nav>;
    return <>
        <header className={`site-navigation ${user ? 'workspace-sidebar' : 'public-navigation'}`}>
            <div className="site-navigation-inner">{brand}<button ref={toggleRef} type="button" className="site-menu-toggle" aria-controls="site-navigation-links" aria-expanded={open} onClick={() => setOpen(value => !value)}><i aria-hidden="true" className={`bi bi-${open ? 'x-lg' : 'list'}`} /> {open ? 'Close menu' : 'Menu'}</button>{menu}</div>
            {user && authError && <p role="alert" className="site-navigation-error">{authError}</p>}
        </header>
        {user && <div className="workspace-topbar"><div className="workspace-breadcrumb"><span>{roleNames[user.role]}</span><i className="bi bi-chevron-right" aria-hidden="true" /><strong>{current}</strong></div><div className="topbar-account"><span className="workspace-indicator" aria-hidden="true" />{roleNames[user.role]}<span className="account-avatar" aria-hidden="true">{initials}</span></div></div>}
    </>;
}
