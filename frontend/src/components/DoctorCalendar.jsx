import { useState, useEffect, useCallback } from "react";
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
    selectPatientStatus as selectBookingStatus, // Renamed to avoid conflict
    selectPatientError as selectBookingError   // Renamed
} from "../redux/slices/patientSlice";
import { selectCurrentUser } from "../redux/slices/authSlice";
import { Calendar, momentLocalizer } from "react-big-calendar";
import moment from "moment";
import "react-big-calendar/lib/css/react-big-calendar.css";
import { Container, Row, Col, Card, Button, ListGroup, Spinner, Alert, Modal } from 'react-bootstrap';
import { toast } from 'react-toastify';
import PropTypes from "prop-types";


const localizer = momentLocalizer(moment);

const DoctorCalendar = ({ doctorId: propDoctorId }) => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const { doctorId: paramDoctorId } = useParams(); // Get doctorId from URL parameters
    const location = useLocation(); // To get state passed during navigation

    // Determine the doctor ID to use
    const doctorId = paramDoctorId ? parseInt(paramDoctorId, 10) : 
                     (location.state?.doctorId ? parseInt(location.state.doctorId, 10) : 
                     (propDoctorId ? parseInt(propDoctorId, 10) : null));
    
    // Initial date from navigation state or default to today
    const initialDate = location.state?.date ? new Date(location.state.date) : new Date();

    // Local state
    const [selectedDate, setSelectedDate] = useState(initialDate);
    const [calendarEvents, setCalendarEvents] = useState([]);
    const [showBookingModal, setShowBookingModal] = useState(false);
    const [selectedSlot, setSelectedSlot] = useState(null);

    // Redux state selectors
    const currentUser = useSelector(selectCurrentUser);
    const availability = useSelector(selectDoctorAvailability); // Contains { schedule, leaves }
    const slotsMap = useSelector(selectDoctorSlots); // { doctorId: { date: [slots] } }
    const doctorDataStatus = useSelector(selectDoctorStatus);
    const doctorDataError = useSelector(selectDoctorError);
    const bookingOpStatus = useSelector(selectBookingStatus);
    const bookingOpError = useSelector(selectBookingError);
    
    // Derived state for current doctor's slots on selectedDate
    const currentSlots = slotsMap[doctorId]?.[moment(selectedDate).format("YYYY-MM-DD")] || [];

    // Loading states
    const isLoadingAvailability = doctorDataStatus === 'loading' && !availability.schedule;
    const isLoadingSlots = doctorDataStatus === 'loading' && !slotsMap[doctorId]?.[moment(selectedDate).format("YYYY-MM-DD")];
    const isBooking = bookingOpStatus === 'loading';

    // Fetch doctor's general info for display (optional, if not already available)
    // This can be expanded or fetched alongside availability if needed.
    // For now, we assume doctor's name might come from another source or isn't critical here.

    // Fetch availability and slots when doctorId or selectedDate changes
    useEffect(() => {
        if (doctorId) {
            dispatch(fetchDoctorAvailability(doctorId)); // Fetches schedule and leaves
            const dateString = moment(selectedDate).format("YYYY-MM-DD");
            dispatch(fetchDoctorSlots({ doctorId, date: dateString }));
        } else {
            toast.error("Doctor ID is missing. Cannot load calendar.");
            // navigate('/patient'); // Or some other appropriate redirect
        }
    }, [dispatch, doctorId, selectedDate]);

    // Process availability data (schedule, leaves) into calendar events
    useEffect(() => {
        if (availability && availability.schedule) {
            const newEvents = [];
            const { leaves } = availability;

            // Add leaves to calendar
            if (Array.isArray(leaves)) {
                leaves.forEach(leave => {
                    newEvents.push({
                        title: `On Leave: ${leave.reason || 'Unavailable'}`,
                        start: moment(leave.start_date).toDate(),
                        end: moment(leave.end_date).add(1, 'day').toDate(), // Full day event
                        allDay: true,
                        type: "leave",
                        color: '#f0ad4e' // Orange for leaves
                    });
                });
            }
            
            // Add working hours/days (optional visualization, slots are more important)
            // This part can be complex if you want to show exact working blocks.
            // For simplicity, we can mark days as "Working Day" if schedule exists.
            // The actual slots are handled separately.
            
            setCalendarEvents(newEvents);
        }
    }, [availability]);

    // Handle date selection on the calendar
    const handleDateSelect = useCallback((slotInfo) => {
        const newSelectedDate = moment(slotInfo.start).startOf("day").toDate();
        setSelectedDate(newSelectedDate);
        // Slots for this new date will be fetched by the useEffect above
    }, []);

    const handleSelectSlotForBooking = (slot) => {
        if (!currentUser) {
            toast.info("Please login to book an appointment.");
            navigate('/login', { state: { from: location } }); // Redirect to login, pass current location
            return;
        }
        if (currentUser.role !== 'patient') {
            toast.error("Only patients can book appointments.");
            return;
        }
        setSelectedSlot(slot);
        setShowBookingModal(true);
    };

    const confirmBooking = async () => {
        if (!selectedSlot || !doctorId || !currentUser) return;

        const dateStr = moment(selectedDate).format("YYYY-MM-DD");
        // Ensure time is correctly formatted for backend (e.g., YYYY-MM-DD HH:MM:SS)
        const dateHeure = `${dateStr}T${selectedSlot}:00`; // Assumes backend can parse this or adjust format

        dispatch(bookAppointment({ doctorId, date_heure: dateHeure }))
            .unwrap()
            .then(() => {
                toast.success(`Appointment booked for ${selectedSlot} on ${dateStr}!`);
                setShowBookingModal(false);
                setSelectedSlot(null);
                // Re-fetch slots for the current date to reflect the booking
                dispatch(fetchDoctorSlots({ doctorId, date: dateStr }));
            })
            .catch((error) => {
                toast.error(error.message || 'Booking failed. Please try again.');
                setShowBookingModal(false);
                setSelectedSlot(null);
            });
    };
    
    const eventStyleGetter = (event) => {
        return {
            style: {
                backgroundColor: event.color || '#3174ad', // Default blue
                borderRadius: '5px',
                opacity: 0.8,
                color: 'white',
                border: '0px',
                display: 'block',
            },
        };
    };

    if (!doctorId) {
        return <Container className="py-4 text-center"><Alert variant="danger">Doctor not specified.</Alert></Container>;
    }
    
    return (
        <Container className="py-4 doctor-calendar-container">
            <Card className="shadow-sm">
                <Card.Header as="h4" className="bg-primary text-white">
                    <i className="fas fa-calendar-alt me-2"></i>
                    Doctor&apos;s Calendar (ID: {doctorId})
                </Card.Header>
                <Card.Body>
                    {doctorDataError && <Alert variant="danger">{doctorDataError}</Alert>}
                    {bookingOpError && <Alert variant="danger">{bookingOpError}</Alert>}

                    <Row>
                        <Col md={7} className="mb-3 mb-md-0">
                            <div style={{ height: '500px' }}>
                                {isLoadingAvailability ? (
                                    <div className="text-center h-100 d-flex align-items-center justify-content-center">
                                        <Spinner animation="border" variant="primary" /> <span className="ms-2">Loading calendar...</span>
                                    </div>
                                ) : (
                                    <Calendar
                                        localizer={localizer}
                                        events={calendarEvents}
                                        startAccessor="start"
                                        endAccessor="end"
                                        style={{ height: '100%' }}
                                        onSelectSlot={handleDateSelect} // For selecting a day/time slot in calendar view
                                        selectable // Allows clicking on empty slots
                                        defaultView="month"
                                        views={['month', 'week', 'day']}
                                        selected={selectedDate} // Highlight the selected date
                                        onNavigate={date => setSelectedDate(date)} // Update selectedDate on navigation
                                        eventPropGetter={eventStyleGetter}
                                    />
                                )}
                            </div>
                        </Col>
                        <Col md={5}>
                            <h5 className="mb-3">
                                <i className="fas fa-clock me-2"></i>
                                Available Slots for: {moment(selectedDate).format("MMMM DD, YYYY")}
                            </h5>
                            {isLoadingSlots ? (
                                <div className="text-center">
                                    <Spinner animation="border" size="sm" /> <span className="ms-2">Loading slots...</span>
                                </div>
                            ) : currentSlots.length > 0 ? (
                                <ListGroup>
                                    {currentSlots.map((slot, index) => (
                                        <ListGroup.Item 
                                            key={index} 
                                            action 
                                            onClick={() => handleSelectSlotForBooking(slot)}
                                            className="d-flex justify-content-between align-items-center slot-item"
                                        >
                                            {slot}
                                            <Button variant="outline-success" size="sm" disabled={isBooking}>
                                                <i className="fas fa-check-circle me-1"></i> Book
                                            </Button>
                                        </ListGroup.Item>
                                    ))}
                                </ListGroup>
                            ) : (
                                <Alert variant="info" className="text-center">
                                    <i className="fas fa-info-circle me-2"></i>
                                    No available slots for this date.
                                    {availability?.leaves?.some(leave => moment(selectedDate).isBetween(moment(leave.start_date), moment(leave.end_date), null, '[]')) && 
                                        " (Doctor is on leave)"}
                                </Alert>
                            )}
                             {currentUser?.role === 'medecin' && currentUser?.doctor?.id === doctorId && (
                                <div className="mt-4 d-grid gap-2">
                                    <Button variant="outline-secondary" size="sm" onClick={() => navigate('/schedule', { state: { doctorId } })}>
                                        <i className="fas fa-edit me-2"></i>Update My Schedule
                                    </Button>
                                    <Button variant="outline-warning" size="sm" onClick={() => navigate('/leaves', { state: { doctorId } })}>
                                        <i className="fas fa-calendar-times me-2"></i>Manage My Leaves
                                    </Button>
                                </div>
                            )}
                        </Col>
                    </Row>
                </Card.Body>
            </Card>

            <Modal show={showBookingModal} onHide={() => setShowBookingModal(false)} centered>
                <Modal.Header closeButton>
                    <Modal.Title>Confirm Appointment</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <p>Are you sure you want to book an appointment for:</p>
                    <p><strong>Date:</strong> {moment(selectedDate).format("MMMM DD, YYYY")}</p>
                    <p><strong>Time:</strong> {selectedSlot}</p>
                    {/* You might want to display doctor's name here if fetched */}
                </Modal.Body>
                <Modal.Footer>
                    <Button variant="secondary" onClick={() => setShowBookingModal(false)} disabled={isBooking}>
                        Cancel
                    </Button>
                    <Button variant="primary" onClick={confirmBooking} disabled={isBooking}>
                        {isBooking ? <><Spinner as="span" size="sm" className="me-2" />Booking...</> : "Confirm Booking"}
                    </Button>
                </Modal.Footer>
            </Modal>
        </Container>
    );
};

DoctorCalendar.propTypes = {
    doctorId: PropTypes.oneOfType([PropTypes.string, PropTypes.number]), // Can be passed as prop
};

export default DoctorCalendar;