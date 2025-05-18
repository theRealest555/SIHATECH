import React, { useState, useEffect } from 'react';
import { Form } from 'react-bootstrap';
import ApiService from '../services/ApiService';

function DoctorSelector({ doctorId, setDoctorId }) {
  const [doctors, setDoctors] = useState([]);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchDoctors = async () => {
      try {
        const response = await ApiService.searchDoctors();
        setDoctors(Array.isArray(response.data) ? response.data : []);
        setError(null);
      } catch (err) {
        console.error('Erreur lors du chargement des médecins', err);
        setError('Impossible de charger les médecins');
        setDoctors([]);
      }
    };
    fetchDoctors();
  }, []);

  return (
    <Form.Group className="mb-3">
      <Form.Label>Sélectionner un médecin</Form.Label>
      {error && <Form.Text className="text-danger">{error}</Form.Text>}
      <Form.Select value={doctorId || ''} onChange={(e) => setDoctorId(Number(e.target.value) || '')}>
        <option value="">Choisir...</option>
        {doctors.length === 0 ? (
          <option value={3} disabled>Dr. Bernard (Pédiatrie) - Indisponible</option>
        ) : (
          doctors.map((doctor) => (
            <option key={doctor.id} value={doctor.id}>
              {doctor.user.prenom} {doctor.user.nom} ({doctor.speciality.nom})
            </option>
          ))
        )}
      </Form.Select>
    </Form.Group>
  );
}

export default DoctorSelector;