import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { useAuth } from '../../hooks/useAuth';

const MainLayout = () => {
  const { user } = useAuth();
  return (
    <div className={`app-shell ${user ? 'signed-in' : 'public-shell'}`}>
      <a className="skip-link" href="#page-content">Skip to content</a>
      <Navbar />
      <div id="page-content" className="page-content" tabIndex={-1}>
        <ToastContainer position="top-right" autoClose={3000} />
        <Outlet />
      </div>
    </div>
  );
};

export default MainLayout;
