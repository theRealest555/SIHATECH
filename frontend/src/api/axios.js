import axios from 'axios';

const baseURL = 'http://localhost:8000';

const axiosInstance = axios.create({
  baseURL,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Function to get CSRF token from cookies
const getCSRFToken = () => {
  const match = document.cookie.match(new RegExp('(^| )XSRF-TOKEN=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
};

// Add request interceptor to add token and CSRF
axiosInstance.interceptors.request.use(
  async (config) => {
    // Add auth token if available
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    // Add CSRF token for non-GET requests
    if (config.method !== 'get') {
      const csrfToken = getCSRFToken();
      if (csrfToken) {
        config.headers['X-XSRF-TOKEN'] = csrfToken;
      } else {
        // If no CSRF token, try to get one
        try {
          await axios.get(`${baseURL}/sanctum/csrf-cookie`, { withCredentials: true });
          const newCsrfToken = getCSRFToken();
          if (newCsrfToken) {
            config.headers['X-XSRF-TOKEN'] = newCsrfToken;
          }
        } catch (error) {
          console.error('Failed to get CSRF token:', error);
        }
      }
    }
    
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle auth errors
axiosInstance.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    // If 419 error (CSRF token mismatch), try to refresh CSRF token
    if (error.response?.status === 419 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        // Get new CSRF token
        await axios.get(`${baseURL}/sanctum/csrf-cookie`, { withCredentials: true });
        
        // Retry the original request
        const csrfToken = getCSRFToken();
        if (csrfToken) {
          originalRequest.headers['X-XSRF-TOKEN'] = csrfToken;
        }
        
        return axiosInstance(originalRequest);
      } catch (csrfError) {
        console.error('Failed to refresh CSRF token:', csrfError);
      }
    }
    
    // Handle 401 (Unauthorized) errors
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    
    return Promise.reject(error);
  }
);

// Initialize CSRF token
export const initializeCSRF = async () => {
  try {
    await axios.get(`${baseURL}/sanctum/csrf-cookie`, { withCredentials: true });
    return true;
  } catch (error) {
    console.error('Failed to initialize CSRF token:', error);
    return false;
  }
};

export default axiosInstance;