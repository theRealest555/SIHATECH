import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import ApiService from "../services/api";
import moment from "moment";

const DoctorSearch = () => {
    const navigate = useNavigate();

    const [specialities, setSpecialities] = useState([]);
    const [locations, setLocations] = useState([]);
    const [doctors, setDoctors] = useState([]);
    const [filters, setFilters] = useState({
        speciality: "",
        location: "",
        date: moment().format("YYYY-MM-DD"),
    });
    const [error, setError] = useState(null);

    useEffect(() => {
        const fetchFilters = async () => {
            try {
                const [specialitiesRes, locationsRes] = await Promise.all([
                    ApiService.getSpecialities(),
                    ApiService.getLocations(),
                ]);
                setSpecialities(specialitiesRes.data.data);
                setLocations(locationsRes.data.data);
            } catch (error) {
                console.error("Error fetching filters:", error);
                setError("Failed to load filters. Please try again later.");
            }
        };
        fetchFilters();
    }, []);

    useEffect(() => {
        const fetchDoctors = async () => {
            try {
                const response = await ApiService.searchDoctors(filters);
                setDoctors(response.data.data);
            } catch (error) {
                console.error("Error searching doctors:", error);
                setError("Failed to search doctors. Please try again later.");
            }
        };
        fetchDoctors();
    }, [filters]);

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilters((prev) => ({ ...prev, [name]: value }));
    };

    const handleViewCalendar = (doctorId) => {
    navigate(`/doctor-calendar/${doctorId}`, { state: { date: filters.date, doctorId } });
    };

    const handleViewAppointments = (doctorId) => {
        navigate(`/doctor/${doctorId}/appointments`, { state: { doctorId } });
    };

    return (
        <div>
            <h2>Find a Doctor</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            <div className="row mb-3">
                <div className="col-md-4">
                    <label htmlFor="speciality" className="form-label">Speciality</label>
                    <select
                        id="speciality"
                        name="speciality"
                        className="form-select"
                        value={filters.speciality}
                        onChange={handleFilterChange}
                    >
                        <option value="">All Specialities</option>
                        {specialities.map((spec, index) => (
                            <option key={index} value={spec}>{spec}</option>
                        ))}
                    </select>
                </div>
                <div className="col-md-4">
                    <label htmlFor="location" className="form-label">Location</label>
                    <select
                        id="location"
                        name="location"
                        className="form-select"
                        value={filters.location}
                        onChange={handleFilterChange}
                    >
                        <option value="">All Locations</option>
                        {locations.map((loc, index) => (
                            <option key={index} value={loc}>{loc}</option>
                        ))}
                    </select>
                </div>
                {/* <div className="col-md-4">
                    <label htmlFor="date" className="form-label">Date</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        className="form-control"
                        value={filters.date}
                        onChange={handleFilterChange}
                        min={moment().format("YYYY-MM-DD")}
                    />
                </div> */}
            </div>
            <h3>Matching Doctors</h3>
            {doctors.length ? (
                <ul className="list-group">
                    {doctors.map((doctor) => (
                        <li key={doctor.id} className="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{doctor.name || 'N/A'}</strong> - {doctor.speciality || 'N/A'} ({doctor.location || 'N/A'})
                            </div>
                            <div>
                                <button
                                    className="btn btn-primary me-2"
                                    onClick={() => handleViewCalendar(doctor.id)}
                                >
                                    View Calendar
                                </button>
                                <button
                                    className="btn btn-secondary"
                                    onClick={() => handleViewAppointments(doctor.id)}
                                >
                                    View Appointments
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No doctors found matching your criteria.</p>
            )}
        </div>
    );
};

export default DoctorSearch;