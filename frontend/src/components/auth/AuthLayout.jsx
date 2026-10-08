import PropTypes from 'prop-types';
// src/components/auth/AuthLayout.jsx
import { Link } from 'react-router-dom';

const AuthLayout = ({ children, title, subtitle }) => {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-100 to-blue-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <Link to="/" aria-label="SihaTech home" className="block text-center text-3xl font-black tracking-wide text-indigo-700 no-underline">
            SIHATECH
        </Link>
        <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
          {title}
        </h2>
        {subtitle && (
            <p className="mt-2 text-center text-sm text-gray-600">
                {subtitle}
            </p>
        )}
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-xl sm:rounded-lg sm:px-10">
          {children}
        </div>
      </div>
       <p className="mt-8 text-center text-xs text-gray-500">
        &copy; {new Date().getFullYear()} SihaTech. All rights reserved.
      </p>
    </div>
  );
};

AuthLayout.propTypes = { children: PropTypes.node.isRequired, title: PropTypes.string.isRequired, subtitle: PropTypes.node };

export default AuthLayout;
