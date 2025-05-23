// Updated main.jsx with CSRF token setup
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom';
import './index.css'
import App from './App.jsx'
import 'bootstrap/dist/css/bootstrap.min.css';
import "react-big-calendar/lib/css/react-big-calendar.css";
import axios from 'axios';

// Add FontAwesome CSS
const fontAwesomeLink = document.createElement('link');
fontAwesomeLink.rel = 'stylesheet';
fontAwesomeLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
document.head.appendChild(fontAwesomeLink);

// Configure axios defaults
axios.defaults.baseURL = 'http://localhost:8000';
axios.defaults.withCredentials = true;

// Get CSRF token before making any requests
const initializeApp = async () => {
  try {
    // Get CSRF cookie from Laravel
    await axios.get('/sanctum/csrf-cookie');
    
    // Now render the app
    createRoot(document.getElementById('root')).render(
      <StrictMode>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </StrictMode>,
    );
  } catch (error) {
    console.error('Failed to initialize CSRF token:', error);
    // Still render the app even if CSRF fails
    createRoot(document.getElementById('root')).render(
      <StrictMode>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </StrictMode>,
    );
  }
};

initializeApp();