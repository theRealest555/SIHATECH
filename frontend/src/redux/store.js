import { combineReducers, configureStore } from '@reduxjs/toolkit';
import authReducer from './slices/authSlice';
import userReducer from './slices/userSlice';
import doctorReducer from './slices/doctorSlice';
import patientReducer from './slices/patientSlice';

const combined = combineReducers({
    auth: authReducer,
    user: userReducer,
    doctor: doctorReducer,
    patient: patientReducer,
});

export function createAppReducer() {
  let generation = 0;
  const requests = new Map();
  return (state, action) => {
    const id = action.meta?.requestId;
    if (id && !action.type.startsWith('auth/')) {
      if (action.meta.requestStatus === 'pending') {
        requests.set(id, generation);
      } else if (requests.has(id)) {
        const started = requests.get(id);
        requests.delete(id);
        // A response from an old account must never restore its private data.
        if (started !== generation) return state;
      }
    }
    let next = combined(state, action);
    if (state?.auth.user?.id !== next.auth.user?.id) {
      generation += 1;
      next = combined(undefined, action);
    }
    return next;
  };
}

export const store = configureStore({
  reducer: createAppReducer(),
});

export default store;
