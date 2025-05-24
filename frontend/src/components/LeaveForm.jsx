import { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
    fetchDoctorAvailability, 
    createDoctorLeave,
    deleteDoctorLeave,
    selectDoctorAvailability,
    selectDoctorStatus,
    selectDoctorError
} from "../redux/slices/doctorSlice";

const LeaveForm = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const dispatch = useDispatch();
    
    const doctorId = location.state?.doctorId || 1; // Fallback to 1 if not provided

    // Get data from Redux
    const availability = useSelector(selectDoctorAvailability);
    const status = useSelector(selectDoctorStatus);
    const error = useSelector(selectDoctorError);
    
    // Local state
    const [startDate, setStartDate] = useState(location.state?.startDate || "");
    const [endDate, setEndDate] = useState(location.state?.endDate || "");
    const [reason, setReason] = useState("");
    
    // Derived state
    const leaves = availability?.leaves || [];
    const isLoading = status === 'loading';

    useEffect(() => {
        if (doctorId) {
            dispatch(fetchDoctorAvailability(doctorId));
        }
    }, [dispatch, doctorId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        
        // Dispatch action to create leave
        dispatch(createDoctorLeave({
            doctorId,
            leaveData: { start_date: startDate, end_date: endDate, reason }
        }))
        .unwrap()
        .then(() => {
            // Clear form and navigate on success
            setStartDate("");
            setEndDate("");
            setReason("");
            navigate(`/doctor-calendar/${doctorId}`);
        })
        .catch((error) => {
            // Error is handled by the Redux slice and displayed from state
            console.error("Failed to create leave:", error);
        });
    };

    const handleDelete = async (leaveId) => {
        dispatch(deleteDoctorLeave({ doctorId, leaveId }))
            .unwrap()
            .then(() => {
                // Success message handled by Redux
            })
            .catch((error) => {
                console.error("Failed to delete leave:", error);
            });
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
                            disabled={isLoading}
                            min={new Date().toISOString().split('T')[0]}
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
                            disabled={isLoading}
                            min={startDate || new Date().toISOString().split('T')[0]}
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
                            disabled={isLoading}
                        />
                    </div>
                </div>
                <button 
                    type="submit" 
                    className="btn btn-primary mt-3"
                    disabled={isLoading}
                >
                    {isLoading ? (
                        <>
                            <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Processing...
                        </>
                    ) : (
                        "Add Leave"
                    )}
                </button>
            </form>
            <h3>Existing Leaves</h3>
            {isLoading ? (
                <div className="text-center my-4">
                    <div className="spinner-border text-primary" role="status">
                        <span className="visually-hidden">Loading...</span>
                    </div>
                </div>
            ) : leaves.length ? (
                <ul className="list-group">
                    {leaves.map((leave) => (
                        <li key={leave.id} className="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                {leave.start_date} to {leave.end_date} - {leave.reason}
                            </div>
                            <button
                                onClick={() => handleDelete(leave.id)}
                                className="btn btn-danger btn-sm"
                                disabled={isLoading}
                            >
                                {isLoading ? (
                                    <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                ) : (
                                    "Delete"
                                )}
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