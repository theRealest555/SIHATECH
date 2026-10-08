import PropTypes from 'prop-types';
import { ListGroup, Form } from 'react-bootstrap';

function AppointmentList({ appointments, onStatusUpdate }) {
  return (
    <div className="bg-white rounded-3 shadow p-4 mb-4">
      <h4 className="mb-4 fw-bold text-primary">Rendez-vous</h4>
      {!Array.isArray(appointments) || appointments.length === 0 ? (
        <p className="text-muted">Aucun rendez-vous</p>
      ) : (
        <ListGroup>
          {appointments.map((appt, index) => (
            <ListGroup.Item key={appt.id || index} className="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
              <div>
                <span className="fw-semibold">{appt.date_heure || 'Date inconnue'}</span>
                <span className="ms-3">
                  <span className={`badge rounded-pill 
                    ${appt.statut === 'confirmé' ? 'bg-success' : 
                      appt.statut === 'annulé' ? 'bg-danger' : 
                      appt.statut === 'terminé' ? 'bg-secondary' : 
                      'bg-warning text-dark'}`}>
                    {appt.statut || 'Inconnu'}
                  </span>
                </span>
              </div>
              <Form.Select
                size="sm"
                className="mt-2 mt-md-0 w-auto"
                value={appt.statut || 'en_attente'}
                onChange={(e) => appt.id && onStatusUpdate(appt.id, e.target.value)}
                disabled={!appt.id}
                style={{ minWidth: 120 }}
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

AppointmentList.propTypes = { appointments: PropTypes.arrayOf(PropTypes.shape({ id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]), date_heure: PropTypes.string, statut: PropTypes.string })), onStatusUpdate: PropTypes.func.isRequired };

export default AppointmentList;