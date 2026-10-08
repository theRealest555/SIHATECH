import PropTypes from 'prop-types';
// src/pages/HomePage.jsx
import { Link } from 'react-router-dom';
import { FaUserMd, FaCalendarCheck, FaSearch, FaShieldAlt, FaBlog, FaQuestionCircle, FaFacebookF, FaTwitter, FaLinkedinIn } from 'react-icons/fa'; // Example icons

const FeatureCard = ({ icon, title, description, linkTo, linkText }) => (
    <div className="bg-white shadow-2xl rounded-2xl p-8 hover:scale-105 hover:shadow-indigo-200 transition-all duration-300 flex flex-col border border-gray-100">
        <div className="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-tr from-indigo-100 to-blue-100 text-indigo-600 mb-6 shadow">
            {icon}
        </div>
        <h3 className="text-2xl font-bold text-gray-800 mb-3 text-center">{title}</h3>
        <p className="text-gray-500 text-base mb-6 text-center flex-grow">{description}</p>
        <Link 
            to={linkTo} 
            className="mt-auto block w-full text-center bg-gradient-to-r from-indigo-600 to-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:from-indigo-700 hover:to-blue-600 shadow transition-all duration-300"
        >
            {linkText}
        </Link>
    </div>
);

FeatureCard.propTypes = { icon: PropTypes.node.isRequired, title: PropTypes.string.isRequired, description: PropTypes.string.isRequired, linkTo: PropTypes.string.isRequired, linkText: PropTypes.string.isRequired };

const HomePage = () => {
    return (
        <div className="bg-gray-50 min-h-screen flex flex-col">
            {/* Hero Section */}
            <section className="relative bg-gradient-to-br from-blue-700 via-indigo-800 to-indigo-900 text-white py-24 md:py-40 overflow-hidden">
                <div className="absolute inset-0 pointer-events-none">
                    <svg className="w-full h-full" viewBox="0 0 1440 320" fill="none">
                        <path fill="#f1f5f9" fillOpacity="0.12" d="M0,160L60,170.7C120,181,240,203,360,197.3C480,192,600,160,720,154.7C840,149,960,171,1080,181.3C1200,192,1320,192,1380,192L1440,192L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z"></path>
                    </svg>
                </div>
                <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-5xl sm:text-6xl md:text-7xl font-extrabold tracking-tight drop-shadow-lg">
                        Welcome to <span className="block text-blue-300 xl:inline">SihaTech</span>
                    </h1>
                    <p className="mt-8 max-w-2xl mx-auto text-2xl sm:text-2xl text-indigo-100 md:mt-10 md:max-w-3xl font-light">
                        Your trusted platform for seamless healthcare connections. Find expert doctors, book appointments, and manage your health journey with ease.
                    </p>
                    <div className="mt-12 flex flex-col sm:flex-row justify-center gap-6">
                        <Link
                            to="/doctors"
                            className="flex items-center justify-center px-8 py-4 border border-transparent text-lg font-semibold rounded-lg text-indigo-700 bg-white hover:bg-indigo-50 shadow-lg transition-colors duration-300"
                        >
                            Find a Doctor
                        </Link>
                        <Link
                            to="/register"
                            className="flex items-center justify-center px-8 py-4 border border-transparent text-lg font-semibold rounded-lg text-white bg-gradient-to-r from-indigo-600 to-blue-500 hover:from-indigo-700 hover:to-blue-600 shadow-lg transition-colors duration-300"
                        >
                            Get Started
                        </Link>
                    </div>
                </div>
            </section>

            {/* SVG Wave Divider */}
            <div className="w-full -mt-1">
                <svg viewBox="0 0 1440 60" fill="none" className="w-full h-12">
                    <path fill="#f9fafb" d="M0,32L60,37.3C120,43,240,53,360,53.3C480,53,600,43,720,37.3C840,32,960,32,1080,37.3C1200,43,1320,53,1380,58.7L1440,64L1440,0L1380,0C1320,0,1200,0,1080,0C960,0,840,0,720,0C600,0,480,0,360,0C240,0,120,0,60,0L0,0Z"></path>
                </svg>
            </div>

            {/* Features Section */}
            <section className="py-20 md:py-28 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-20">
                        <h2 className="text-4xl font-extrabold text-gray-900 sm:text-5xl">
                            Everything You Need for Better Health
                        </h2>
                        <p className="mt-6 text-xl text-gray-500 max-w-2xl mx-auto">
                            Discover a range of services designed to make your healthcare experience simpler and more effective.
                        </p>
                    </div>
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-12">
                        <FeatureCard 
                            icon={<FaSearch className="h-8 w-8" />}
                            title="Find Doctors Easily"
                            description="Search our extensive network of qualified doctors by specialty, location, and more. Read patient reviews to make informed choices."
                            linkTo="/doctors"
                            linkText="Search Doctors"
                        />
                        <FeatureCard 
                            icon={<FaCalendarCheck className="h-8 w-8" />}
                            title="Effortless Booking"
                            description="View doctor availability and book appointments online in just a few clicks. Get reminders and manage your schedule on the go."
                            linkTo="/doctors"
                            linkText="Book Now"
                        />
                        <FeatureCard 
                            icon={<FaUserMd className="h-8 w-8" />}
                            title="For Healthcare Professionals"
                            description="Join our platform to expand your reach, manage your schedule efficiently, and connect with patients seeking your expertise."
                            linkTo="/register"
                            linkText="Join as a Doctor"
                        />
                        <FeatureCard 
                            icon={<FaShieldAlt className="h-8 w-8" />}
                            title="Secure & Confidential"
                            description="Your privacy is our priority. We use robust security measures to protect your personal and health information."
                            linkTo="/privacy-policy"
                            linkText="Learn More"
                        />
                        <FeatureCard 
                            icon={<FaBlog className="h-8 w-8" />}
                            title="Health & Wellness Blog"
                            description="Stay informed with our articles on various health topics, wellness tips, and updates in the medical field."
                            linkTo="/blog"
                            linkText="Read Articles"
                        />
                        <FeatureCard 
                            icon={<FaQuestionCircle className="h-8 w-8" />}
                            title="Support & FAQ"
                            description="Have questions? Our support team is here to help. Find answers to common queries in our FAQ section."
                            linkTo="/faq"
                            linkText="Get Help"
                        />
                    </div>
                </div>
            </section>

            {/* Call to Action Section */}
            <section className="relative bg-gradient-to-r from-indigo-50 to-blue-100 py-20 md:py-28">
                <div className="absolute inset-0 pointer-events-none">
                    <svg className="w-full h-full" viewBox="0 0 1440 320" fill="none">
                        <path fill="#6366f1" fillOpacity="0.08" d="M0,160L60,170.7C120,181,240,203,360,197.3C480,192,600,160,720,154.7C840,149,960,171,1080,181.3C1200,192,1320,192,1380,192L1440,192L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z"></path>
                    </svg>
                </div>
                <div className="relative z-10 max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                    <h2 className="text-4xl font-extrabold text-gray-900 sm:text-5xl">
                        Ready to Take Control of Your Health?
                    </h2>
                    <p className="mt-6 text-xl text-gray-600">
                        Sign up today and experience a new standard in healthcare access and management.
                    </p>
                    <div className="mt-10">
                        <Link
                            to="/register"
                            className="inline-flex items-center justify-center px-10 py-5 border border-transparent text-2xl font-bold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-blue-500 hover:from-indigo-700 hover:to-blue-600 shadow-xl hover:shadow-2xl transition-all duration-300"
                        >
                            Create Your Free Account
                        </Link>
                    </div>
                </div>
            </section>
            
            {/* Footer */}
            <footer className="bg-gray-900 text-white py-12 mt-auto">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div className="text-center md:text-left">
                        <p className="font-semibold text-lg">&copy; {new Date().getFullYear()} SihaTech. All rights reserved.</p>
                        <p className="mt-2 text-gray-400">
                            <Link to="/terms" className="hover:text-indigo-300 transition-colors">Terms of Service</Link>
                            {" | "}
                            <Link to="/privacy" className="hover:text-indigo-300 transition-colors">Privacy Policy</Link>
                        </p>
                    </div>
                    <div className="flex space-x-6 justify-center md:justify-end">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" className="hover:text-indigo-400 transition-colors">
                            <FaFacebookF size={22} />
                        </a>
                        <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" className="hover:text-indigo-400 transition-colors">
                            <FaTwitter size={22} />
                        </a>
                        <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" className="hover:text-indigo-400 transition-colors">
                            <FaLinkedinIn size={22} />
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default HomePage;
