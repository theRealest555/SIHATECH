import { useState, useEffect } from "react";
import { useNavigate, useParams, useLocation } from "react-router-dom";
import ApiService from "../services/api";
import { Calendar, momentLocalizer } from "react-big-calendar";
import moment from "moment";
import "react-big-calendar/lib/css/react-big-calendar.css";

const localizer = momentLocalizer(moment);

const DoctorCalendar = ({ doctorId: propDoctorId, mode }) => {
    const navigate = useNavigate();
    const { doctorId: paramDoctorId } = useParams();
    const location = useLocation();
    const { date: initialDate, doctorId: stateDoctorId } = location.state || {};

    const doctorId = paramDoctorId ? Number(paramDoctorId) : (stateDoctorId || propDoctorId);
    const [selectedDate, setSelectedDate] = useState(initialDate ? new Date(initialDate) : new Date());
    const [slots, setSlots] = useState([]);
    const [events, setEvents] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const patientId = 1;

    useEffect(() => {
        const fetchData = async () => {
            try {
                const availabilityResponse = await ApiService.getAvailability(doctorId);
                const { schedule, leaves } = availabilityResponse.data.data;

                const leaveEvents = leaves.map((leave) => ({
                    title: `Leave: ${leave.reason}`,
                    start: new Date(leave.start_date),
                    end: new Date(leave.end_date),
                    allDay: true,
                    type: "leave",
                }));

                const workingEvents = [];
                const daysOfWeek = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
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
                setError(null);
            } catch (error) {
                console.error("Error fetching availability:", error);
                setError("Failed to fetch availability. Please try again later.");
            }
        };

        const fetchSlots = async () => {
            setLoading(true);
            try {
                const dateString = moment(selectedDate).format("YYYY-MM-DD");
                console.log("Fetching slots for doctor ID:", doctorId, "Date:", dateString);
                const response = await ApiService.getSlots(doctorId, dateString);
                console.log("Fetched slots:", response.data.data);
                setSlots(response.data.data);
                setError(null);
            } catch (error) {
                console.error("Error fetching slots:", error);
                setError("Failed to fetch slots. Please try again later.");
            } finally {
                setLoading(false);
            }
        };

        if (doctorId) {
            fetchData();
            fetchSlots();
        }
    }, [doctorId, selectedDate]);

    const handleDateSelect = (slotInfo) => {
        const correctedDate = moment(slotInfo.start).startOf("day").toDate();
        console.log("Selected date:", correctedDate);
        setSelectedDate(correctedDate);
    };

    const handleBookAppointment = async (slot) => {
        setLoading(true);
        setError(null);
        try {
            const dateStr = moment(selectedDate).format("YYYY-MM-DD");
            const dateHeure = `${dateStr}T${slot}:00`;
            
            console.log(`Booking appointment: ${dateHeure} for doctor ${doctorId}`);
            console.log(`Selected date timezone: ${selectedDate.toString()}`);
            console.log(`Slot value: ${slot}`);

            const appointmentData = {
                doctor_id: doctorId,
                patient_id: patientId,
                date_heure: dateHeure,
            };
            
            console.log("Booking with payload:", appointmentData);
            const response = await ApiService.bookAppointment(appointmentData);
            console.log("Booking response:", response);
            
            alert("Appointment booked successfully!");
            
            const dateString = moment(selectedDate).format("YYYY-MM-DD");
            const slotsResponse = await ApiService.getSlots(doctorId, dateString);
            setSlots(slotsResponse.data.data);
        } catch (error) {
            console.error("Error booking appointment:", error);
            if (error.response) {
                if (error.response.status === 409) {
                    setError("This slot is already booked. Please select another slot.");
                } else if (error.response.status === 422) {
                    const errors = error.response.data.errors;
                    const errorMessages = Object.values(errors).flat().join(" ");
                    setError(`Failed to book appointment: ${errorMessages}`);
                } else if (error.response.status === 400) {
                    setError(`Failed to book appointment: ${error.response.data.message || 'Invalid request.'}`);
                } else {
                    setError("Failed to book appointment. Please try again later.");
                }
                try {
                    const dateString = moment(selectedDate).format("YYYY-MM-DD");
                    const response = await ApiService.getSlots(doctorId, dateString);
                    setSlots(response.data.data);
                } catch (slotError) {
                    console.error("Error refreshing slots:", slotError);
                }
            } else {
                setError("Failed to book appointment. Network error or service unavailable.");
            }
        } finally {
            setLoading(false);
        }
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
            <h2>Doctor Calendar (Doctor ID: {doctorId})</h2>
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
            {loading ? (
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
                                    disabled={loading}
                                >
                                    {loading ? "Processing..." : "Take Rendezvous"}
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

export default DoctorCalendar;