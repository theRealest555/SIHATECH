import React from 'react';
import { ListGroup, Button, Spinner } from 'react-bootstrap';

function SlotList({ slots, onBook, loading }) {
  return (
    <div>
      <h4>Créneaux disponibles</h4>
      {loading ? (
        <Spinner animation="border" />
      ) : !Array.isArray(slots) || slots.length === 0 ? (
        <p>Aucun créneau disponible</p>
      ) : (
        <ListGroup>
          {slots.map((slot) => (
            <ListGroup.Item key={slot}>
              {slot}
              <Button
                variant="primary"
                size="sm"
                className="ms-2"
                onClick={() => onBook(slot)}
                disabled={loading}
              >
                Réserver
              </Button>
            </ListGroup.Item>
          ))}
        </ListGroup>
      )}
    </div>
  );
}

export default SlotList;