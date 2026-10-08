import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

const MainLayout = () => {
  return (
    <div className="min-vh-100 d-flex flex-column">
      <Navbar />
      <main className="flex-grow-1 bg-gradient-to-br from-indigo-50 via-blue-100 to-white py-4 px-2 md:px-6">
        <ToastContainer position="top-right" autoClose={3000} />
        <Outlet />
      </main>
    </div>
  );
};

export default MainLayout;