import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
    fetchPatientAppointments, 
    updateAppointmentStatus,
    selectPatientAppointments,
    selectPatientStatus,
    selectPatientError
} from "../redux/slices/patientSlice";
import { selectCurrentUser } from "../redux/slices/authSlice";
import moment from "moment";

const PatientAppointments = () => {
    const { doctorId } = useParams(); // Get doctorId from the route
    const dispatch = useDispatch();
    
    // Get user and appointments from Redux
    const user = useSelector(selectCurrentUser);
    const appointments = useSelector(selectPatientAppointments);
    const status = useSelector(selectPatientStatus);
    const error = useSelector(selectPatientError);
    
    // Track the updated status for each appointment
    const [statusUpdates, setStatusUpdates] = useState({});
    
    // Determine which ID to use for fetching appointments
    const patientId = user?.id;
    const fetchId = doctorId || patientId;

    useEffect(() => {
        if (fetchId) {
            dispatch(fetchPatientAppointments(fetchId));
        }
    }, [dispatch, fetchId]);

    // Handle status change in the dropdown
    const handleStatusChange = (appointmentId, newStatus) => {
        setStatusUpdates((prev) => ({
            ...prev,
            [appointmentId]: newStatus,
        }));
    };

    // Update the appointment status via Redux
    const handleUpdateStatus = async (appointmentId) => {
        const newStatus = statusUpdates[appointmentId];
        if (!newStatus) {
            alert("Please select a new status.");
            return;
        }

        dispatch(updateAppointmentStatus({ appointmentId, status: newStatus }))
            .unwrap()
            .then(() => {
                alert("Status updated successfully!");
                // Refresh the appointments list
                dispatch(fetchPatientAppointments(fetchId));
                // Clear the status update for this appointment
                setStatusUpdates((prev) => {
                    const updated = { ...prev };
                    delete updated[appointmentId];
                    return updated;
                });
            })
            .catch((error) => {
                alert(`Failed to update status: ${error.message || "Please try again."}`);
            });
    };

    const isLoading = status === 'loading';

    return (
        <div className="container mt-4">
            <h2>{doctorId ? `Patient Appointments for Doctor ID: ${doctorId}` : "My Appointments"}</h2>
            
            {error && <div className="alert alert-danger">{error}</div>}
            
            {isLoading ? (
                <div className="text-center my-4">
                    <output className="spinner-border text-primary">
                        <span className="visually-hidden">Loading...</span>
                    </output>
                </div>
            ) : appointments.length > 0 ? (
                <table className="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            {doctorId && <th>Actions</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {appointments.map((appointment) => (
                            <tr key={appointment.id}>
                                <td>{appointment.patient_name || 'N/A'}</td>
                                <td>{moment(appointment.date_heure).format("YYYY-MM-DD HH:mm")}</td>
                                <td>
                                    <span className={`badge bg-${
                                        appointment.statut === 'confirmé' ? 'success' :
                                        appointment.statut === 'en_attente' ? 'warning' :
                                        appointment.statut === 'annulé' ? 'danger' : 'secondary'
                                    }`}>
                                        {appointment.statut}
                                    </span>
                                </td>
                                {doctorId && (
                                    <td>
                                        <select
                                            value={statusUpdates[appointment.id] || ""}
                                            onChange={(e) => handleStatusChange(appointment.id, e.target.value)}
                                            className="form-select d-inline-block w-auto me-2"
                                            disabled={isLoading}
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
                                            disabled={!statusUpdates[appointment.id] || isLoading}
                                        >
                                            {isLoading ? (
                                                <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            ) : (
                                                "Save"
                                            )}
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