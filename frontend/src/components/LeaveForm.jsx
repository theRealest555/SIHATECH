import { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import ApiService from "../services/api";

const LeaveForm = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const doctorId = location.state?.doctorId || 1; // Fallback to 1 if not provided

    const [leaves, setLeaves] = useState([]);
    const [startDate, setStartDate] = useState(location.state?.startDate || "");
    const [endDate, setEndDate] = useState(location.state?.endDate || "");
    const [reason, setReason] = useState("");
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchLeaves = async () => {
            try {
                const response = await ApiService.getAvailability(doctorId);
                setLeaves(response.data.data.leaves);
                setError(null);
            } catch (error) {
                console.error("Error fetching leaves:", error);
                setError("Failed to load leaves. Please try again later.");
            }
        };
        if (doctorId) {
            fetchLeaves();
        }
    }, [doctorId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            await ApiService.createLeave(doctorId, { start_date: startDate, end_date: endDate, reason });
            setError(null);
            navigate(`/doctor-calendar/${doctorId}`); // Redirect to the doctor's calendar
        } catch (error) {
            console.error("Error creating leave:", error);
            setError("Failed to create leave: " + (error.response?.data?.error || "Unknown error"));
        }
    };

    const handleDelete = async (leaveId) => {
        try {
            await ApiService.deleteLeave(doctorId, leaveId);
            setLeaves(leaves.filter((leave) => leave.id !== leaveId));
            setError(null);
        } catch (error) {
            console.error("Error deleting leave:", error);
            setError("Failed to delete leave: " + (error.response?.data?.error || "Unknown error"));
        }
    };

    return (
        <div>
            <h2>Manage Leaves for Doctor ID {doctorId}</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            <form onSubmit={handleSubmit} className="mb-4">
                <div className="row">
                    <div className="col-md-4">
                        <label className="form-label">Start Date</label>
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="form-control"
                            required
                        />
                    </div>
                    <div className="col-md-4">
                        <label className="form-label">End Date</label>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="form-control"
                            required
                        />
                    </div>
                    <div className="col-md-4">
                        <label className="form-label">Reason</label>
                        <input
                            type="text"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            className="form-control"
                            required
                        />
                    </div>
                </div>
                <button type="submit" className="btn btn-primary mt-3">
                    Add Leave
                </button>
            </form>
            <h3>Existing Leaves</h3>
            {leaves.length ? (
                <ul className="list-group">
                    {leaves.map((leave) => (
                        <li key={leave.id} className="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                {leave.start_date} to {leave.end_date} - {leave.reason}
                            </div>
                            <button
                                onClick={() => handleDelete(leave.id)}
                                className="btn btn-danger btn-sm"
                            >
                                Delete
                            </button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No leaves found.</p>
            )}
        </div>
    );
};

export default LeaveForm;