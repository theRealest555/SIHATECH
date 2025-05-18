import { Routes, Route, Link, Navigate } from "react-router-dom";
import DoctorCalendar from "./components/DoctorCalendar";
import DoctorSearch from "./components/DoctorSearch";
import ScheduleForm from "./components/ScheduleForm";
import LeaveForm from "./components/LeaveForm";
import PatientAppointments from "./components/PatientAppointments";
import { getDoctors } from "./services/api";
import { useState, useEffect } from "react";


const App = () => {
    const [doctors, setDoctors] = useState([]);
    const [selectedDoctorId, setSelectedDoctorId] = useState(null);

    useEffect(() => {
        const fetchDoctors = async () => {
            try {
                const response = await getDoctors();
                setDoctors(response.data.data);
                if (response.data.data.length > 0) {
                    setSelectedDoctorId(response.data.data[0].id); // Set default doctor ID
                }
            } catch (error) {
                console.error("Error fetching doctors:", error);
            }
        };
        fetchDoctors();
    }, []);

    const handleDoctorChange = (e) => {
        const doctorId = Number(e.target.value);
        setSelectedDoctorId(doctorId);
    };

    return (
        <div>
            <nav className="navbar navbar-expand-lg navbar-light bg-light mb-4">
                <div className="container">
                    <Link className="navbar-brand" to="/">Medical Application System</Link>
                    <div className="navbar-nav">
                        <Link className="nav-link" to="/doctor">Doctor Calendar</Link>
                        {/* <Link className="nav-link" to="/schedule">Update Schedule</Link>
                        <Link className="nav-link" to="/leaves">Manage Leaves</Link> */}
                        <Link className="nav-link" to="/patient">Find a Doctor</Link>
                        <Link className="nav-link" to="/my-appointments">My Appointments</Link>
                    </div>
                </div>
            </nav>
            <div className="container">
                <Routes>
    <Route path="/" element={<Navigate to="/patient" />} />
    <Route
        path="/doctor"
        element={
            <>
                <div className="mb-3">
                    <label htmlFor="doctorSelect" className="form-label">Select Doctor:</label>
                    <select
                        id="doctorSelect"
                        className="form-select"
                        value={selectedDoctorId || ""}
                        onChange={handleDoctorChange}
                    >
                        <option value="">-- Select a Doctor --</option>
                        {doctors.map((doctor) => (
                            <option key={doctor.id} value={doctor.id}>
                                {doctor.name || 'N/A'} ({doctor.speciality || 'N/A'})
                            </option>
                        ))}
                    </select>
                </div>
                {selectedDoctorId && <DoctorCalendar doctorId={selectedDoctorId} mode="patient" />}
            </>
        }
    />
    <Route path="/patient" element={<DoctorSearch />} />
    <Route path="/doctor-calendar/:doctorId" element={<DoctorCalendar mode="patient" />} />
    <Route path="/doctor/:doctorId/appointments" element={<PatientAppointments />} />
    <Route path="/schedule" element={<ScheduleForm />} />
    <Route path="/leaves" element={<LeaveForm />} />
</Routes>
            </div>
        </div>
    );
};

export default App;