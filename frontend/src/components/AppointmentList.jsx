import React from 'react';
import { ListGroup, Form } from 'react-bootstrap';

function AppointmentList({ appointments, onStatusUpdate }) {
  return (
    <div>
      <h4>Rendez-vous</h4>
      {!Array.isArray(appointments) || appointments.length === 0 ? (
        <p>Aucun rendez-vous</p>
      ) : (
        <ListGroup>
          {appointments.map((appt, index) => (
            <ListGroup.Item key={appt.id || index}> {/* Fallback to index if id is missing */}
              {appt.date_heure || 'Date inconnue'} - Statut: {appt.statut || 'Inconnu'}
              <Form.Select
                size="sm"
                className="mt-2"
                value={appt.statut || 'en_attente'}
                onChange={(e) => appt.id && onStatusUpdate(appt.id, e.target.value)}
                disabled={!appt.id}
              >
                <option value="en_attente">En attente</option>
                <option value="confirmé">Confirmé</option>
                <option value="annulé">Annulé</option>
                <option value="terminé">Terminé</option>
              </Form.Select>
            </ListGroup.Item>
          ))}
        </ListGroup>
      )}
    </div>
  );
}

export default AppointmentList;