import * as yup from 'yup';

// Common validation rules based on Laravel FormRequests
export const loginSchema = yup.object({
  email: yup.string()
    .email('Must be a valid email')
    .required('Email is required'),
  password: yup.string()
    .required('Password is required')
});

export const registerSchema = yup.object({
  nom: yup.string()
    .required('Last name is required')
    .max(50, 'Last name must be at most 50 characters'),
  prenom: yup.string()
    .required('First name is required')
    .max(50, 'First name must be at most 50 characters'),
  email: yup.string()
    .email('Must be a valid email')
    .required('Email is required')
    .max(50, 'Email must be at most 50 characters'),
  password: yup.string()
    .required('Password is required')
    .min(8, 'Password must be at least 8 characters'),
  password_confirmation: yup.string()
    .oneOf([yup.ref('password'), null], 'Passwords must match')
    .required('Please confirm your password'),
  telephone: yup.string()
    .nullable()
    .max(20, 'Phone number must be at most 20 characters'),
  role: yup.string()
    .required('Role is required')
    .oneOf(['patient', 'medecin', 'admin'], 'Invalid role'),
  speciality_id: yup.number()
    .when('role', {
      is: 'medecin',
      then: () => yup.number().required('Speciality is required'),
      otherwise: () => yup.number().nullable()
    })
});

export const patientProfileSchema = yup.object({
  nom: yup.string()
    .required('Last name is required')
    .max(255, 'Last name must be at most 255 characters'),
  prenom: yup.string()
    .required('First name is required')
    .max(255, 'First name must be at most 255 characters'),
  email: yup.string()
    .email('Must be a valid email')
    .required('Email is required')
    .max(255, 'Email must be at most 255 characters'),
  telephone: yup.string()
    .nullable()
    .max(20, 'Phone number must be at most 20 characters'),
  adresse: yup.string()
    .nullable(),
  sexe: yup.string()
    .nullable()
    .oneOf(['homme', 'femme', null], 'Gender must be male or female'),
  date_de_naissance: yup.date()
    .nullable()
    .max(new Date(), 'Date of birth cannot be in the future'),
  medecin_favori_id: yup.number()
    .nullable()
});

export const doctorProfileSchema = yup.object({
  nom: yup.string()
    .required('Last name is required')
    .max(255, 'Last name must be at most 255 characters'),
  prenom: yup.string()
    .required('First name is required')
    .max(255, 'First name must be at most 255 characters'),
  email: yup.string()
    .email('Must be a valid email')
    .required('Email is required')
    .max(255, 'Email must be at most 255 characters'),
  telephone: yup.string()
    .nullable()
    .max(20, 'Phone number must be at most 20 characters'),
  adresse: yup.string()
    .nullable(),
  sexe: yup.string()
    .nullable()
    .oneOf(['homme', 'femme', null], 'Gender must be male or female'),
  date_de_naissance: yup.date()
    .nullable()
    .max(new Date(), 'Date of birth cannot be in the future'),
  speciality_id: yup.number()
    .required('Speciality is required'),
  description: yup.string()
    .nullable(),
  horaires: yup.mixed()
    .nullable()
});

export const passwordUpdateSchema = yup.object({
  current_password: yup.string()
    .required('Current password is required'),
  password: yup.string()
    .required('New password is required')
    .min(8, 'Password must be at least 8 characters'),
  password_confirmation: yup.string()
    .oneOf([yup.ref('password'), null], 'Passwords must match')
    .required('Please confirm your password')
});

export const appointmentSchema = yup.object({
  patient_id: yup.number()
    .required('Patient is required'),
  date_heure: yup.date()
    .required('Date and time are required')
    .min(new Date(), 'Date and time must be in the future')
});

export const leaveSchema = yup.object({
  start_date: yup.date()
    .required('Start date is required')
    .min(new Date(), 'Start date must be in the future'),
  end_date: yup.date()
    .required('End date is required')
    .min(yup.ref('start_date'), 'End date must be after start date'),
  reason: yup.string()
    .nullable()
    .max(255, 'Reason must be at most 255 characters')
});

export const scheduleSchema = yup.object({
  schedule: yup.object({
    monday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    tuesday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    wednesday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    thursday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    friday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    saturday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
    sunday: yup.array().of(yup.string().matches(/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Format must be HH:MM-HH:MM')),
  }).required('Schedule is required')
});