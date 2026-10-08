// src/main.jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router } from 'react-router-dom';
import App from './App.jsx';
import { Provider } from 'react-redux';
import store from './redux/store';
import 'bootstrap/dist/css/bootstrap.min.css'; // Bootstrap CSS
import 'bootstrap-icons/font/bootstrap-icons.css'; // Bootstrap Icons
import './index.css'; // Tailwind CSS
import './App.css'; // Design system overrides follow Bootstrap and Tailwind.
import { AuthProvider } from './contexts/AuthContext.jsx';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <Provider store={store}>
    <Router>
      <AuthProvider>
        <App />
      </AuthProvider>
    </Router>
    </Provider>
  </React.StrictMode>
);
