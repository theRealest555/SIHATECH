import { useState, useEffect } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import ApiService from "../services/api";

const ScheduleForm = () => {
    const navigate = useNavigate();
    const location = useLocation();
    const doctorId = location.state?.doctorId;

    const [schedule, setSchedule] = useState({
        monday: [],
        tuesday: [],
        wednesday: [],
        thursday: [],
        friday: [],
        saturday: [],
        sunday: [],
    });
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchSchedule = async () => {
            if (!doctorId) return;
            try {
                const response = await ApiService.getAvailability(doctorId);
                const currentSchedule = response.data.data.schedule || {};
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
            } catch (error) {
                console.error("Error fetching schedule:", error);
                setError("Failed to load current schedule. Please try again.");
            }
        };
        fetchSchedule();
    }, [doctorId]);

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
            setError("No doctor selected.");
            return;
        }
        try {
            await ApiService.updateSchedule(doctorId, schedule);
            navigate(`/doctor-calendar/${doctorId}`);
        } catch (error) {
            console.error("Error updating schedule:", error);
            setError("Failed to update schedule. Please try again.");
        }
    };

    return (
        <div>
            <h2>Update Schedule for Doctor ID {doctorId}</h2>
            {error && <div className="alert alert-danger">{error}</div>}
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
                                />
                                <button
                                    type="button"
                                    className="btn btn-danger"
                                    onClick={() => handleRemoveTimeRange(day, index)}
                                >
                                    Remove
                                </button>
                            </div>
                        ))}
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={() => handleAddTimeRange(day)}
                        >
                            Add Time Range
                        </button>
                    </div>
                ))}
                <button type="submit" className="btn btn-primary">
                    Save Schedule
                </button>
            </form>
        </div>
    );
};

export default ScheduleForm;