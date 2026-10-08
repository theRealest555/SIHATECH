// src/components/layouts/MainLayout.jsx
import { Outlet } from 'react-router-dom';
import Navbar from './Navbar';
// import Footer from './Footer'; // If you have a separate Footer component

const MainLayout = () => {
    return (
        <div className="flex flex-col min-h-screen">
            <Navbar />
            <main className="flex-grow bg-gradient-to-br from-indigo-50 via-blue-100 to-white py-4 px-2 md:px-6">
                <Outlet />
            </main>
            {/* <Footer /> // Uncomment if you have a Footer component */}
            {/* Basic footer example if not using a separate component, can be removed if HomePage has its own or Navbar includes it */}
            {/* <footer className="bg-gray-800 text-white py-8 text-center">
                <p>&copy; {new Date().getFullYear()} SihaTech. All rights reserved.</p>
            </footer> */}
        </div>
    );
};

export default MainLayout;
