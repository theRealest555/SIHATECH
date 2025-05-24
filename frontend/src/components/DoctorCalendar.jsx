import { useState, useEffect } from "react";
import { useNavigate, useParams, useLocation } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
    fetchDoctorAvailability,
    fetchDoctorSlots, 
    selectDoctorAvailability,
    selectDoctorSlots,
    selectDoctorStatus,
    selectDoctorError
} from "../redux/slices/doctorSlice";
import { 
    bookAppointment,
    selectPatientStatus,
    selectPatientError 
} from "../redux/slices/patientSlice";
import { selectCurrentUser } from "../redux/slices/authSlice";
import { Calendar, momentLocalizer } from "react-big-calendar";
import moment from "moment";
import "react-big-calendar/lib/css/react-big-calendar.css";
import PropTypes from "prop-types";

const localizer = momentLocalizer(moment);

const DoctorCalendar = ({ doctorId: propDoctorId, mode }) => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { doctorId: paramDoctorId } = useParams();
    const location = useLocation();
    const { date: initialDate, doctorId: stateDoctorId } = location.state || {};

    // Get the doctor ID from params, props, or state
    const doctorId = paramDoctorId ? Number(paramDoctorId) : (stateDoctorId || propDoctorId);
    
    // Local state
    const [selectedDate, setSelectedDate] = useState(initialDate ? new Date(initialDate) : new Date());
    const [events, setEvents] = useState([]);
    
    // Redux state
    const user = useSelector(selectCurrentUser);
    const availability = useSelector(selectDoctorAvailability);
    const slotsMap = useSelector(selectDoctorSlots);
    const doctorStatus = useSelector(selectDoctorStatus);
    const doctorError = useSelector(selectDoctorError);
    const bookingStatus = useSelector(selectPatientStatus);
    const bookingError = useSelector(selectPatientError);
    
    // Derived state
    const slots = slotsMap[doctorId]?.[moment(selectedDate).format("YYYY-MM-DD")] || [];
    const isLoading = doctorStatus === 'loading';
    const isBooking = bookingStatus === 'loading';
    const error = doctorError || bookingError;

    useEffect(() => {
        if (doctorId) {
            // Fetch doctor's availability (schedule and leaves)
            dispatch(fetchDoctorAvailability(doctorId));
            
            // Fetch slots for the selected date
            const dateString = moment(selectedDate).format("YYYY-MM-DD");
            dispatch(fetchDoctorSlots({ doctorId, date: dateString }));
        }
    }, [dispatch, doctorId, selectedDate]);

    useEffect(() => {
        // Process availability data into calendar events
        if (availability) {
            const { schedule, leaves } = availability;
            
            // Create events for leaves
            const leaveEvents = leaves.map((leave) => ({
                title: `Leave: ${leave.reason}`,
                start: new Date(leave.start_date),
                end: new Date(leave.end_date),
                allDay: true,
                type: "leave",
            }));
            
            // Create events for working days
            const workingEvents = [];
            const startOfMonth = moment(selectedDate).startOf("month").toDate();
            const endOfMonth = moment(selectedDate).endOf("month").toDate();
            
            for (let day = startOfMonth; day <= endOfMonth; day = moment(day).add(1, "day").toDate()) {
                const dayOfWeek = moment(day).format("dddd").toLowerCase();
                if (schedule[dayOfWeek] && schedule[dayOfWeek].length > 0) {
                    workingEvents.push({
                        title: `Working: ${schedule[dayOfWeek].join(", ")}`,
                        start: new Date(day),
                        end: new Date(day),
                        allDay: true,
                        type: "working",
                    });
                }
            }
            
            setEvents([...leaveEvents, ...workingEvents]);
        }
    }, [availability, selectedDate]);

    const handleDateSelect = (slotInfo) => {
        const correctedDate = moment(slotInfo.start).startOf("day").toDate();
        setSelectedDate(correctedDate);
    };

    const handleBookAppointment = async (slot) => {
        if (!user) {
            navigate('/login');
            return;
        }
        
        const dateStr = moment(selectedDate).format("YYYY-MM-DD");
        const dateHeure = `${dateStr}T${slot}:00`;
        
        const patientId = user.id;
        
        // Dispatch booking action
        dispatch(bookAppointment({
            doctorId,
            patientId,
            date_heure: dateHeure
        }))
        .unwrap()
        .then(() => {
            // Refresh slots after successful booking
            dispatch(fetchDoctorSlots({ doctorId, date: dateStr }));
            alert("Appointment booked successfully!");
        })
        .catch((error) => {
            alert(`Booking failed: ${error.message || 'Please try again.'}`);
        });
    };

    const eventStyleGetter = (event) => {
        const backgroundColor = event.type === "leave" ? "#ff4d4f" : "#40c4ff";
        return {
            style: {
                backgroundColor,
                borderRadius: "5px",
                opacity: 0.8,
                color: "white",
                border: "0px",
                display: "block",
            },
        };
    };

    return (
        <div>
            <h2>Doctor Calendar {doctorId ? `(Doctor ID: ${doctorId})` : ''}</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            <div className="mb-3">
                <button
                    className="btn btn-secondary me-2"
                    onClick={() => navigate('/schedule', { state: { doctorId } })}
                >
                    Update Schedule
                </button>
                <button
                    className="btn btn-secondary"
                    onClick={() => navigate('/leaves', { state: { doctorId } })}
                >
                    Manage Leaves
                </button>
            </div>
            <div className="mb-3">
                <Calendar
                    localizer={localizer}
                    events={events}
                    startAccessor="start"
                    endAccessor="end"
                    style={{ height: 500 }}
                    onSelectSlot={handleDateSelect}
                    selectable
                    defaultDate={selectedDate}
                    eventPropGetter={eventStyleGetter}
                />
            </div>
            <h3>Available Slots for {moment(selectedDate).format("YYYY-MM-DD")}</h3>
            {isLoading ? (
                <p>Loading available slots...</p>
            ) : slots.length ? (
                <ul className="list-group">
                    {slots.map((slot, index) => (
                        <li key={index} className="list-group-item d-flex justify-content-between align-items-center">
                            {slot}
                            {mode === "patient" && (
                                <button
                                    className="btn btn-primary"
                                    onClick={() => handleBookAppointment(slot)}
                                    disabled={isBooking}
                                >
                                    {isBooking ? "Processing..." : "Take Rendezvous"}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No available slots for this date.</p>
            )}
        </div>
    );
};
DoctorCalendar.propTypes = {
    doctorId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    mode: PropTypes.string,
};

export default DoctorCalendar;