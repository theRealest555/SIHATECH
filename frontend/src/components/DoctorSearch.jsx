import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
  fetchDoctorSpecialities, 
  fetchDoctorLocations, 
  searchDoctors,
  selectDoctorSpecialities,
  selectDoctorLocations,
  selectDoctorSearchResults,
  selectDoctorStatus,
  selectDoctorError
} from "../redux/slices/doctorSlice";
import moment from "moment";

const DoctorSearch = () => {
    const navigate = useNavigate();
    const dispatch = useDispatch();

    // Get data from Redux store
    const specialities = useSelector(selectDoctorSpecialities);
    const locations = useSelector(selectDoctorLocations);
    const doctors = useSelector(selectDoctorSearchResults);
    const status = useSelector(selectDoctorStatus);
    const error = useSelector(selectDoctorError);

    const [filters, setFilters] = useState({
        speciality: "",
        location: "",
        date: moment().format("YYYY-MM-DD"),
    });

    useEffect(() => {
        // Fetch filter options
        dispatch(fetchDoctorSpecialities());
        dispatch(fetchDoctorLocations());
    }, [dispatch]);

    useEffect(() => {
        // Search doctors when filters change
        dispatch(searchDoctors(filters));
    }, [dispatch, filters]);

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

    const isLoading = status === 'loading';

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
                        disabled={isLoading}
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
                        disabled={isLoading}
                    >
                        <option value="">All Locations</option>
                        {locations.map((loc, index) => (
                            <option key={index} value={loc}>{loc}</option>
                        ))}
                    </select>
                </div>
                <div className="col-md-4">
                    <label htmlFor="date" className="form-label">Date</label>
                    <input
                        type="date"
                        id="date"
                        name="date"
                        className="form-control"
                        value={filters.date}
                        onChange={handleFilterChange}
                        min={moment().format("YYYY-MM-DD")}
                        disabled={isLoading}
                    />
                </div>
            </div>
            
            <h3>Matching Doctors</h3>
            {isLoading ? (
                <div className="d-flex justify-content-center my-4">
                    <div className="spinner-border text-primary" role="status">
                        <span className="visually-hidden">Loading...</span>
                    </div>
                </div>
            ) : doctors.length ? (
                <ul className="list-group">
                    {doctors.map((doctor) => (
                        <li key={doctor.id} className="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{doctor.name || 'N/A'}</strong> - {doctor.speciality || 'N/A'} ({doctor.location || 'N/A'})
                                {doctor.available_slots && doctor.available_slots.length > 0 && (
                                    <span className="badge bg-success ms-2">
                                        {doctor.available_slots.length} slots available
                                    </span>
                                )}
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