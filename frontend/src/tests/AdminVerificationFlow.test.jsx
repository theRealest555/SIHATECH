// @vitest-environment jsdom
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import VerificationPage from '../pages/admin/DoctorVerificationPage';
import DashboardPage from '../pages/admin/DashboardPage';
import * as service from '../services/adminService';
vi.mock('../services/adminService', () => ({ getVerificationDoctors: vi.fn(), getVerificationDoctor: vi.fn(), approveDoctorDocument: vi.fn(), rejectDoctorDocument: vi.fn(), verifyDoctor: vi.fn(), revokeDoctorVerification: vi.fn(), downloadAdminDocument: vi.fn(), getAdminDashboard: vi.fn() }));
const document = { id: 91, type: 'licence', status: 'pending', original_name: 'medical-licence.pdf', file_available: true };
const doctor = { id: 7, speciality_id: 2, is_verified: false, user: { prenom: 'Sara', nom: 'Ahmed', email: 'sara@example.test', email_verified_at: '2100-01-01', status: 'actif' }, speciality: { nom: 'Cardiology' }, pending_documents_count: 1, documents: [document] };
const list = { data: { data: [doctor], meta: { current_page: 1, last_page: 2, total: 16 } } };
const detail = (data = doctor, missing = ['licence']) => ({ data: { data, meta: { required_document_types: ['licence'], missing_document_types: missing } } });
beforeEach(() => { vi.resetAllMocks(); service.getVerificationDoctors.mockResolvedValue(list); service.getVerificationDoctor.mockResolvedValue(detail()); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
function show() { render(<VerificationPage />); }
async function select(user) { await user.click(await screen.findByRole('button', { name: 'Review Dr. Sara Ahmed' })); await screen.findByText('medical-licence.pdf'); }
it('uses submitted filters for search and pagination', async () => {
    const user = userEvent.setup(); show(); await screen.findByRole('button', { name: 'Review Dr. Sara Ahmed' });
    await user.type(screen.getByLabelText('Name or email'), 'Sara'); await user.click(screen.getByRole('button', { name: 'Search', exact: true }));
    await waitFor(() => expect(service.getVerificationDoctors.mock.lastCall[0]).toEqual({ status: 'pending', search: 'Sara', page: 1 }));
    await user.click(screen.getByRole('button', { name: 'Next', exact: true }));
    await waitFor(() => expect(service.getVerificationDoctors.mock.lastCall[0].page).toBe(2));
});
it('requires confirmation and sends document IDs for approval', async () => {
    const user = userEvent.setup(); show(); await select(user);
    expect(screen.getByRole('button', { name: 'Verify doctor' }).disabled).toBe(true);
    await user.click(screen.getByRole('button', { name: 'Approve medical-licence.pdf' })); expect(service.approveDoctorDocument).not.toHaveBeenCalled();
    service.approveDoctorDocument.mockResolvedValue({});
    service.getVerificationDoctor.mockResolvedValue(detail({ ...doctor, documents: [{ ...document, status: 'approved' }] }, []));
    await user.click(screen.getByRole('button', { name: 'Confirm decision' })); await screen.findByText('Decision saved.');
    expect(service.approveDoctorDocument).toHaveBeenCalledWith(91, 'pending');
    expect(screen.getByRole('button', { name: 'Verify doctor' }).disabled).toBe(false);
});
it('rejects the credential ID with the entered reason rather than the doctor ID', async () => {
    const user = userEvent.setup(); show(); await select(user); await user.click(screen.getByRole('button', { name: 'Reject medical-licence.pdf' }));
    expect(screen.getByRole('button', { name: 'Confirm decision' }).disabled).toBe(true);
    await user.type(screen.getByLabelText('Reason'), 'Unreadable licence.'); service.rejectDoctorDocument.mockResolvedValue({});
    await user.click(screen.getByRole('button', { name: 'Confirm decision' })); await screen.findByText('Decision saved.');
    expect(service.rejectDoctorDocument).toHaveBeenCalledWith(91, 'Unreadable licence.', 'pending');
});
it('refreshes a stale review conflict without claiming success', async () => {
    const user = userEvent.setup(); show(); await select(user); await user.click(screen.getByRole('button', { name: 'Approve medical-licence.pdf' }));
    service.approveDoctorDocument.mockRejectedValue({ response: { status: 409, data: { message: 'Another admin reviewed this.' } } });
    await user.click(screen.getByRole('button', { name: 'Confirm decision' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Another admin reviewed this.');
    expect(screen.queryByText('Decision saved.')).toBeNull(); expect(service.getVerificationDoctor).toHaveBeenCalledTimes(2);
});
it('verifies a doctor only after approved credentials and explicit confirmation', async () => {
    service.getVerificationDoctor.mockResolvedValue(detail({ ...doctor, documents: [{ ...document, status: 'approved' }] }, []));
    const user = userEvent.setup(); show(); await select(user); await user.click(screen.getByRole('button', { name: 'Verify doctor' }));
    expect(service.verifyDoctor).not.toHaveBeenCalled(); service.verifyDoctor.mockResolvedValue({});
    service.getVerificationDoctor.mockResolvedValue(detail({ ...doctor, is_verified: true }, []));
    await user.click(screen.getByRole('button', { name: 'Confirm decision' })); await screen.findByRole('button', { name: 'Revoke verification' });
    expect(service.verifyDoctor).toHaveBeenCalledWith(7);
});
it('requires a revocation reason and preserves verification on failure', async () => {
    service.getVerificationDoctor.mockResolvedValue(detail({ ...doctor, is_verified: true }, []));
    const user = userEvent.setup(); show(); await select(user); await user.click(screen.getByRole('button', { name: 'Revoke verification' }));
    await user.type(screen.getByLabelText('Reason'), 'Credential expired.');
    service.revokeDoctorVerification.mockRejectedValue({ response: { data: { message: 'Could not revoke.' } } });
    await user.click(screen.getByRole('button', { name: 'Confirm decision' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Could not revoke.');
    expect(service.revokeDoctorVerification).toHaveBeenCalledWith(7, 'Credential expired.');
    expect(screen.queryByText('Decision saved.')).toBeNull();
});
it('uses the private download endpoint and blocks missing files', async () => {
    const user = userEvent.setup(); show(); await select(user);
    URL.createObjectURL = vi.fn(() => 'blob:admin-private'); URL.revokeObjectURL = vi.fn();
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    service.downloadAdminDocument.mockResolvedValue({ data: new Blob(['test']) });
    await user.click(screen.getByRole('button', { name: 'Download medical-licence.pdf' })); await waitFor(() => expect(click).toHaveBeenCalled());
    expect(service.downloadAdminDocument).toHaveBeenCalledWith(91);
    service.getVerificationDoctor.mockResolvedValue(detail({ ...doctor, documents: [{ ...document, file_available: false }] }));
    await user.click(screen.getByRole('button', { name: 'Reload applications' }));
    await screen.findByText('File missing. Ask the doctor to upload it again.'); expect(screen.getByRole('button', { name: 'Approve medical-licence.pdf' }).disabled).toBe(true);
});
it('shows failed list loads with a working reload action', async () => {
    service.getVerificationDoctors.mockRejectedValueOnce({ response: { data: { message: 'Applications unavailable.' } } });
    const user = userEvent.setup(); show(); expect((await screen.findByRole('alert')).textContent).toContain('Applications unavailable.');
    await user.click(screen.getByRole('button', { name: 'Reload applications' })); await screen.findByRole('button', { name: 'Review Dr. Sara Ahmed' });
});
it('renders real dashboard counts and only supported action routes', async () => {
    service.getAdminDashboard.mockResolvedValue({ data: { data: { total_users: 23, total_doctors: 9, total_patients: 12, total_appointments: 41, pending_doctor_verifications: 2, pending_reviews: 3, recent_registrations: [] } } });
    render(<MemoryRouter><DashboardPage /></MemoryRouter>); await screen.findByText('23'); expect(screen.getByText('41')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Review doctor credentials' }).getAttribute('href')).toBe('/admin/doctors-verification');
    expect(screen.queryByText('Operational')).toBeNull(); expect(screen.queryByText('1250')).toBeNull();
});
