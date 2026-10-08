import PropTypes from 'prop-types';
import { Link } from 'react-router-dom';
export default function AuthLayout({ children, title, subtitle }) {
    return <div className="auth-layout">
        <aside className="auth-story"><p className="eyebrow">A LITTLE LESS COMPLEXITY.</p><h1>Your care.<br />A clearer path.</h1><p>Find the right doctor, make time for your health, and keep your appointments in one place.</p><div className="auth-art" aria-hidden="true"><div className="art-ring" /><span className="art-cross">+</span></div><div className="auth-story-footer"><span>SIHATECH</span><span>Care, connected.</span></div></aside>
        <section className="auth-panel" aria-label={title}><div className="auth-form-wrap"><Link to="/" aria-label="SihaTech home" className="auth-wordmark">sihatech<span> / Your account</span></Link><h2>{title}</h2>{subtitle && <p className="auth-subtitle">{subtitle}</p>}<div className="auth-form">{children}</div><p className="auth-copyright">© {new Date().getFullYear()} SihaTech</p></div></section>
    </div>;
}
AuthLayout.propTypes = { children: PropTypes.node.isRequired, title: PropTypes.string.isRequired, subtitle: PropTypes.node };
