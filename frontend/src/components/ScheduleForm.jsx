import { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
    fetchDoctorAvailability, 
    updateDoctorSchedule,
    selectDoctorAvailability,
    selectDoctorStatus,
    selectDoctorError
} from "../redux/slices/doctorSlice";

const ScheduleForm = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const dispatch = useDispatch();
    
    const doctorId = location.state?.doctorId;

    // Redux state
    const availability = useSelector(selectDoctorAvailability);
    const status = useSelector(selectDoctorStatus);
    const error = useSelector(selectDoctorError);

    // Local state
    const [schedule, setSchedule] = useState({
        monday: [],
        tuesday: [],
        wednesday: [],
        thursday: [],
        friday: [],
        saturday: [],
        sunday: [],
    });

    // Check if loading
    const isLoading = status === 'loading';

    useEffect(() => {
        if (doctorId) {
            dispatch(fetchDoctorAvailability(doctorId));
        }
    }, [dispatch, doctorId]);

    useEffect(() => {
        if (availability && availability.schedule) {
            const currentSchedule = availability.schedule || {};
            const updatedSchedule = {
                monday: currentSchedule.monday || [],
                tuesday: currentSchedule.tuesday || [],
                wednesday: currentSchedule.wednesday || [],
                thursday: currentSchedule.thursday || [],
                friday: currentSchedule.friday || [],
                saturday: currentSchedule.saturday || [],
                sunday: currentSchedule.sunday || [],
            };
            
            // Convert string format to array if needed
            Object.keys(updatedSchedule).forEach(day => {
                if (typeof updatedSchedule[day] === 'string') {
                    updatedSchedule[day] = updatedSchedule[day].split(',').map(time => time.trim());
                }
            });
            
            setSchedule(updatedSchedule);
        }
    }, [availability]);

    const daysOfWeek = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];

    const handleAddTimeRange = (day) => {
        setSchedule((prev) => ({
            ...prev,
            [day]: [...prev[day], "09:00-17:00"],
        }));
    };

    const handleTimeChange = (day, index, value) => {
        const newTimes = [...schedule[day]];
        newTimes[index] = value;
        setSchedule((prev) => ({
            ...prev,
            [day]: newTimes,
        }));
    };

    const handleRemoveTimeRange = (day, index) => {
        const newTimes = schedule[day].filter((_, i) => i !== index);
        setSchedule((prev) => ({
            ...prev,
            [day]: newTimes,
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!doctorId) {
            alert("No doctor selected.");
            return;
        }
        
        dispatch(updateDoctorSchedule({ doctorId, schedule }))
            .unwrap()
            .then(() => {
                navigate(`/doctor-calendar/${doctorId}`);
            })
            .catch((error) => {
                // Error handled by Redux
                console.error("Failed to update schedule:", error);
            });
    };

    return (
        <div>
            <h2>Update Schedule for Doctor ID {doctorId}</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            {isLoading && !error ? (
                <div className="text-center my-4">
                    <div className="spinner-border text-primary" role="status">
                        <span className="visually-hidden">Loading...</span>
                    </div>
                    <p className="mt-2">Loading schedule...</p>
                </div>
            ) : (
                <form onSubmit={handleSubmit}>
                    {daysOfWeek.map((day) => (
                        <div key={day} className="mb-3">
                            <h5>{day.charAt(0).toUpperCase() + day.slice(1)}</h5>
                            {schedule[day].map((timeRange, index) => (
                                <div key={index} className="input-group mb-2">
                                    <input
                                        type="text"
                                        className="form-control"
                                        value={timeRange}
                                        onChange={(e) => handleTimeChange(day, index, e.target.value)}
                                        placeholder="e.g., 09:00-17:00"
                                        disabled={isLoading}
                                    />
                                    <button
                                        type="button"
                                        className="btn btn-danger"
                                        onClick={() => handleRemoveTimeRange(day, index)}
                                        disabled={isLoading}
                                    >
                                        Remove
                                    </button>
                                </div>
                            ))}
                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={() => handleAddTimeRange(day)}
                                disabled={isLoading}
                            >
                                Add Time Range
                            </button>
                        </div>
                    ))}
                    <button 
                        type="submit" 
                        className="btn btn-primary"
                        disabled={isLoading}
                    >
                        {isLoading ? (
                            <>
                                <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Saving...
                            </>
                        ) : (
                            "Save Schedule"
                        )}
                    </button>
                </form>
            )}
        </div>
    );
};

export default ScheduleForm;