import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import './Navbar.css';

export default function Navbar() {
    const { user, logout, loading, authError } = useAuth();
    const [open, setOpen] = useState(false);
    const rolePath = { patient: 'patient', medecin: 'doctor', admin: 'admin' }[user?.role];
    const links = [
        { to: '/', label: 'Home' },
        { to: '/doctors', label: 'Find a doctor' },
        { to: '/subscription-plans', label: 'Plans' },
    ];
    if (user) {
        links.push({ to: '/my-subscription', label: 'Subscription' });
        links.push({ to: rolePath ? `/${rolePath}/dashboard` : '/dashboard', label: 'Dashboard' });
        if (['patient', 'doctor'].includes(rolePath)) {
            links.push({ to: `/${rolePath}/appointments`, label: 'Appointments' });
            links.push({ to: `/${rolePath}/profile`, label: 'Profile' });
        }
        if (rolePath === 'doctor') {
            links.push({ to: '/doctor/availability', label: 'Availability' }, { to: '/doctor/documents', label: 'Documents' }, { to: '/doctor/statistics', label: 'Statistics' });
        }
        if (rolePath === 'admin') links.push({ to: '/admin/users', label: 'Users' }, { to: '/admin/doctors-verification', label: 'Doctor verification' }, { to: '/admin/audit-logs', label: 'Audit history' }, { to: '/admin/specialities', label: 'Specialities' }, { to: '/admin/languages', label: 'Languages' }, { to: '/admin/reviews', label: 'Review moderation' }, { to: '/admin/subscription-plans', label: 'Plan management' }, { to: '/admin/reports', label: 'Reports' });
    } else {
        links.push({ to: '/login', label: 'Sign in' }, { to: '/register', label: 'Create account' });
    }

    async function signOut() {
        if (await logout()) setOpen(false);
    }

    return <header className="site-navigation">
        <div className="site-navigation-inner">
            <Link to="/" className="site-brand" onClick={() => setOpen(false)}>SIHATECH</Link>
            <button type="button" className="site-menu-toggle" aria-controls="site-navigation-links" aria-expanded={open} onClick={() => setOpen(value => !value)}>{open ? 'Close menu' : 'Menu'}</button>
            <nav id="site-navigation-links" aria-label="Main navigation" className={`site-navigation-links ${open ? 'is-open' : ''}`}>
                {links.map(link => <NavLink key={link.to} to={link.to} end={link.to === '/'} onClick={() => setOpen(false)}>{link.label}</NavLink>)}
                {user && <button disabled={loading} onClick={signOut}>{loading ? 'Signing out…' : 'Sign out'}</button>}
            </nav>
        </div>
        {user && authError && <p role="alert" className="site-navigation-error">{authError}</p>}
    </header>;
}
