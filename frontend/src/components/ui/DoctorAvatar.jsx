import { useState } from 'react';
import PropTypes from 'prop-types';
import api from '../../api/axios';

export default function DoctorAvatar({ name, photo }) {
    const [failed, setFailed] = useState(false);
    const initials = name.split(' ').filter(Boolean).slice(0, 2).map(part => part[0]).join('');
    const src = photo && (/^https?:\/\//i.test(photo) ? photo : `${api.defaults.baseURL}/storage/${photo.replace(/^\/?storage\//, '')}`);
    return src && !failed ? (
        <img src={src} alt="" onError={() => setFailed(true)} className="h-20 w-20 shrink-0 rounded-full object-cover" />
    ) : <span aria-hidden="true" className="h-20 w-20 shrink-0 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-2xl font-bold">{initials || 'Dr'}</span>;
}
DoctorAvatar.propTypes = { name: PropTypes.string.isRequired, photo: PropTypes.string };
