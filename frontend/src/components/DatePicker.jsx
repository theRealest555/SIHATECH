import React from 'react';
import { Form } from 'react-bootstrap';
import { format, parse } from 'date-fns';

function DatePicker({ date, setDate }) {
  return (
    <Form.Group className="mb-3">
      <Form.Label>Date</Form.Label>
      <Form.Control
        type="date"
        value={format(date, 'yyyy-MM-dd')}
        onChange={(e) => setDate(parse(e.target.value, 'yyyy-MM-dd', new Date()))}
      />
    </Form.Group>
  );
}

export default DatePicker;