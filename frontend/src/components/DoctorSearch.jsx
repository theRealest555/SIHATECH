import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import { 
  fetchDoctorSpecialities, 
  fetchDoctorLocations, 
  searchDoctors,
  selectDoctorSpecialities, // Returns array of {id, nom, ...}
  selectDoctorLocations,    // Returns array of strings
  selectDoctorSearchResults,
  selectDoctorStatus,
  selectDoctorError
} from "../redux/slices/doctorSlice";
import { Container, Row, Col, Form, Button, Card, ListGroup, Spinner, Alert, Badge } from 'react-bootstrap';
import moment from "moment";

const DoctorSearch = () => {
    const navigate = useNavigate();
    const dispatch = useDispatch();

    // Redux state
    const specialities = useSelector(selectDoctorSpecialities); // Array of {id, nom, description}
    const locations = useSelector(selectDoctorLocations); // Array of strings
    const doctors = useSelector(selectDoctorSearchResults); // Array of doctor objects from search
    const status = useSelector(selectDoctorStatus);
    const error = useSelector(selectDoctorError);

    // Local state for filters
    const [filters, setFilters] = useState({
        speciality_id: "", // Store ID for speciality
        location: "",
        name: "", // For doctor's name search
        date: moment().format("YYYY-MM-DD"), // Default to today
    });

    // Fetch initial filter options (specialities, locations)
    useEffect(() => {
        dispatch(fetchDoctorSpecialities());
        dispatch(fetchDoctorLocations());
    }, [dispatch]);

    // Trigger search when filters change (debounced or on explicit search button click)
    // For simplicity, searching on every filter change here.
    useEffect(() => {
        // Prepare filters for the API: remove empty values
        const activeFilters = {};
        if (filters.speciality_id) activeFilters.speciality_id = filters.speciality_id;
        if (filters.location) activeFilters.location = filters.location;
        if (filters.name) activeFilters.name = filters.name;
        if (filters.date) activeFilters.date = filters.date; // Backend uses this to check availability for the day

        dispatch(searchDoctors(activeFilters));
    }, [dispatch, filters]);

    const handleFilterChange = (e) => {
        const { name, value } = e.target;
        setFilters((prev) => ({ ...prev, [name]: value }));
    };

    const handleViewCalendar = (doctorId) => {
        navigate(`/doctor-calendar/${doctorId}`, { state: { date: filters.date, doctorId } });
    };
    
    // This function might not be directly used if appointments are viewed on a separate page or doctor's profile
    // const handleViewAppointments = (doctorId) => {
    //     navigate(`/doctor/${doctorId}/appointments`, { state: { doctorId } });
    // };

    const isLoading = status === 'loading';
    const isLoadingFilters = isLoading && (specialities.length === 0 || locations.length === 0);

    return (
        <Container className="py-4">
            <Card className="shadow-sm mb-4">
                <Card.Header as="h4" className="bg-primary text-white">
                    <i className="fas fa-search me-2"></i>Find a Doctor
                </Card.Header>
                <Card.Body>
                    {error && <Alert variant="danger">{typeof error === 'string' ? error : "An error occurred while fetching data."}</Alert>}
                    <Row className="g-3 mb-3 align-items-end">
                        <Col md={3}>
                            <Form.Group>
                                <Form.Label htmlFor="name">Doctor Name</Form.Label>
                                <Form.Control
                                    type="text"
                                    id="name"
                                    name="name"
                                    placeholder="e.g., Dr. Smith"
                                    value={filters.name}
                                    onChange={handleFilterChange}
                                    disabled={isLoading}
                                />
                            </Form.Group>
                        </Col>
                        <Col md={3}>
                            <Form.Group>
                                <Form.Label htmlFor="speciality_id">Speciality</Form.Label>
                                <Form.Select
                                    id="speciality_id"
                                    name="speciality_id"
                                    value={filters.speciality_id}
                                    onChange={handleFilterChange}
                                    disabled={isLoading || isLoadingFilters}
                                >
                                    <option value="">{isLoadingFilters ? "Loading..." : "All Specialities"}</option>
                                    {Array.isArray(specialities) && specialities.map((spec) => (
                                        <option key={spec.id} value={spec.id}>
                                            {spec.nom}
                                        </option>
                                    ))}
                                </Form.Select>
                            </Form.Group>
                        </Col>
                        <Col md={3}>
                            <Form.Group>
                                <Form.Label htmlFor="location">Location</Form.Label>
                                <Form.Select
                                    id="location"
                                    name="location"
                                    value={filters.location}
                                    onChange={handleFilterChange}
                                    disabled={isLoading || isLoadingFilters}
                                >
                                    <option value="">{isLoadingFilters ? "Loading..." : "All Locations"}</option>
                                    {Array.isArray(locations) && locations.map((loc, index) => (
                                        <option key={index} value={loc}>
                                            {loc}
                                        </option>
                                    ))}
                                </Form.Select>
                            </Form.Group>
                        </Col>
                        <Col md={3}>
                             <Form.Group>
                                <Form.Label htmlFor="date">Preferred Date</Form.Label>
                                <Form.Control
                                    type="date"
                                    id="date"
                                    name="date"
                                    value={filters.date}
                                    onChange={handleFilterChange}
                                    min={moment().format("YYYY-MM-DD")}
                                    disabled={isLoading}
                                />
                            </Form.Group>
                        </Col>
                        {/* <Col md={2} className="d-flex align-items-end">
                            <Button variant="primary" onClick={() => dispatch(searchDoctors(filters))} className="w-100" disabled={isLoading}>
                                {isLoading ? <Spinner as="span" size="sm" /> : <><i className="fas fa-search me-1"></i> Search</>}
                            </Button>
                        </Col> */}
                    </Row>
                </Card.Body>
            </Card>
            
            <h5>Search Results</h5>
            {isLoading && !isLoadingFilters ? (
                <div className="text-center my-4">
                    <Spinner animation="border" variant="primary" />
                    <p className="mt-2">Searching for doctors...</p>
                </div>
            ) : doctors.length > 0 ? (
                <ListGroup variant="flush">
                    {doctors.map((doctor) => (
                        <ListGroup.Item key={doctor.id} className="mb-3 p-3 border rounded shadow-sm doctor-card">
                            <Row className="align-items-center">
                                <Col xs={12} md={2} className="text-center mb-2 mb-md-0">
                                     <img 
                                        src={doctor.photo ? `http://localhost:8000/storage/${doctor.photo}` : 'https://via.placeholder.com/80'} 
                                        alt={doctor.name} 
                                        className="rounded-circle" 
                                        style={{ width: '80px', height: '80px', objectFit: 'cover' }}
                                    />
                                </Col>
                                <Col xs={12} md={7}>
                                    <h5 className="mb-1">Dr. {doctor.name || 'N/A'}</h5>
                                    <p className="text-muted mb-1">
                                        <i className="fas fa-stethoscope me-2 text-primary"></i>{doctor.speciality || 'N/A'}
                                        <span className="mx-2">|</span>
                                        <i className="fas fa-map-marker-alt me-2 text-primary"></i>{doctor.location || 'N/A'}
                                    </p>
                                     <p className="mb-1 small">
                                        <i className="fas fa-star text-warning me-1"></i> 
                                        {doctor.rating?.formatted || 'N/A'} ({doctor.rating?.total_reviews || 0} reviews)
                                    </p>
                                    {doctor.languages && doctor.languages.length > 0 && (
                                        <p className="text-muted small mb-0">
                                            <i className="fas fa-language me-2"></i>
                                            Speaks: {doctor.languages.join(', ')}
                                        </p>
                                    )}
                                </Col>
                                <Col xs={12} md={3} className="text-md-end mt-2 mt-md-0">
                                    {doctor.available_slots && doctor.available_slots.length > 0 ? (
                                        <Badge bg="success" pill className="mb-2 d-block">
                                            {doctor.available_slots.length} slots available on {moment(filters.date).format("MMM DD")}
                                        </Badge>
                                    ) : (
                                         <Badge bg="secondary" pill className="mb-2 d-block">
                                            Check availability
                                        </Badge>
                                    )}
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={() => handleViewCalendar(doctor.id)}
                                        className="w-100"
                                    >
                                        <i className="fas fa-calendar-alt me-2"></i>
                                        View Calendar & Book
                                    </Button>
                                </Col>
                            </Row>
                        </ListGroup.Item>
                    ))}
                </ListGroup>
            ) : (
                <Alert variant="info" className="text-center">
                    <i className="fas fa-info-circle fa-2x mb-2"></i>
                    <p className="mb-0">No doctors found matching your criteria. Try adjusting your filters.</p>
                </Alert>
            )}
        </Container>
    );
};

export default DoctorSearch;