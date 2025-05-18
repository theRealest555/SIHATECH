import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import ApiService from "../services/api";
import moment from "moment";

const PatientAppointments = () => {
    const { doctorId } = useParams(); // Get doctorId from the route
    const [appointments, setAppointments] = useState([]);
    const [error, setError] = useState(null);
    const patientId = 1; // Hardcoded for now; replace with authenticated user's ID
    // Track the updated status for each appointment
    const [statusUpdates, setStatusUpdates] = useState({});

    useEffect(() => {
        const fetchAppointments = async () => {
            try {
                let response;
                if (doctorId) {
                    // Fetch appointments for the doctor (used in /doctor/:doctorId/appointments)
                    response = await ApiService.getAppointments(doctorId);
                } else {
                    // Fetch appointments for the patient (used in /my-appointments)
                    response = await ApiService.getPatientAppointments(patientId);
                }
                setAppointments(response.data.data);
                setError(null);
            } catch (error) {
                console.error("Error fetching appointments:", error);
                setError("Failed to fetch appointments. Please try again later.");
            }
        };

        fetchAppointments();
    }, [doctorId]);

    // Handle status change in the dropdown
    const handleStatusChange = (appointmentId, newStatus) => {
        setStatusUpdates((prev) => ({
            ...prev,
            [appointmentId]: newStatus,
        }));
    };

    // Update the appointment status via API
    const handleUpdateStatus = async (appointmentId) => {
        const newStatus = statusUpdates[appointmentId];
        if (!newStatus) {
            alert("Please select a new status.");
            return;
        }

        try {
            await ApiService.updateAppointmentStatus(appointmentId, { statut: newStatus });
            alert("Status updated successfully!");
            // Refresh the appointments list
            const response = doctorId
                ? await ApiService.getAppointments(doctorId)
                : await ApiService.getPatientAppointments(patientId);
            setAppointments(response.data.data);
            // Clear the status update for this appointment
            setStatusUpdates((prev) => {
                const updated = { ...prev };
                delete updated[appointmentId];
                return updated;
            });
        } catch (error) {
            console.error("Error updating appointment status:", error);
            setError("Failed to update appointment status. Please try again.");
        }
    };

    return (
        <div className="container mt-4">
            <h2>{doctorId ? `Patient Appointments for Doctor ID: ${doctorId}` : "My Appointments"}</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            {appointments.length > 0 ? (
                <table className="table table-striped">
                    <thead>
                        <tr><th>Patient Name</th><th>Date & Time</th><th>Status</th>{doctorId && <th>Actions</th>}</tr>
                    </thead>
                    <tbody>
                        {appointments.map((appointment) => (
                            <tr key={appointment.id}>
                                <td>{appointment.patient_name || 'N/A'}</td>
                                <td>{moment(appointment.date_heure).format("YYYY-MM-DD HH:mm")}</td>
                                <td>{appointment.statut}</td>
                                {doctorId && (
                                    <td>
                                        <select
                                            value={statusUpdates[appointment.id] || ""}
                                            onChange={(e) => handleStatusChange(appointment.id, e.target.value)}
                                            className="form-select d-inline-block w-auto me-2"
                                        >
                                            <option value="">Select Status</option>
                                            <option value="confirmé">Confirmé</option>
                                            <option value="en_attente">En Attente</option>
                                            <option value="annulé">Annulé</option>
                                            <option value="terminé">Terminé</option>
                                        </select>
                                        <button
                                            className="btn btn-primary btn-sm"
                                            onClick={() => handleUpdateStatus(appointment.id)}
                                            disabled={!statusUpdates[appointment.id]}
                                        >
                                            Save
                                        </button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <p>{doctorId ? "No appointments found for this doctor." : "You have no appointments booked."}</p>
            )}
        </div>
    );
};

export default PatientAppointments;