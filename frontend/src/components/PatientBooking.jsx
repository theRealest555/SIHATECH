import DoctorSearch from "./DoctorSearch";
import DoctorCalendar from "./DoctorCalendar";
import { useState } from "react";

const PatientBooking = ({ patientId }) => {
  const [selectedDoctorId, setSelectedDoctorId] = useState(null);

  return (
    <div>
      {!selectedDoctorId ? (
        <DoctorSearch onSelectDoctor={setSelectedDoctorId} />
      ) : (
        <>
          <button
            onClick={() => setSelectedDoctorId(null)}
            className="btn btn-secondary mb-4"
          >
            Back to Search
          </button>
          <DoctorCalendar doctorId={selectedDoctorId} mode="patient" patientId={patientId} />
        </>
      )}
    </div>
  );
};

export default PatientBooking;