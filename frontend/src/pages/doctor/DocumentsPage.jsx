import { useCallback, useEffect, useRef, useState } from 'react';
import { deleteDoctorDocument, downloadDoctorDocument, getDoctorDocuments, uploadDoctorDocument } from '../../services/doctorService';
import { apiError } from '../../utils/apiErrors';

const types = { licence: 'Medical licence', cni: 'Identity document', diplome: 'Diploma', autre: 'Other' };
const statuses = { pending: 'Awaiting review', approved: 'Approved', rejected: 'Rejected' };
export default function DocumentsPage() {
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [type, setType] = useState('licence');
    const [removing, setRemoving] = useState(null);
    const fileInput = useRef(null);
    const load = useCallback(async (signal) => {
        const response = await getDoctorDocuments({ signal }); setDocuments(response.data.documents);
    }, []);
    useEffect(() => {
        const controller = new AbortController();
        load(controller.signal).catch(err => { if (!controller.signal.aborted) setError(apiError(err, 'Could not load documents.')); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [load]);
    async function refresh() {
        setLoading(true); setError('');
        try { await load(); } catch (err) { setError(apiError(err, 'Could not load documents.')); }
        finally { setLoading(false); }
    }
    async function mutate(action, success) {
        setBusy(true); setError(''); setMessage('');
        try {
            await action(); setMessage(success); setRemoving(null);
            try { await load(); } catch { setError('Changes saved, but refreshing failed. Reload to see the latest documents.'); }
            return true;
        } catch (err) { setError(apiError(err, 'Could not save document changes.')); return false; }
        finally { setBusy(false); }
    }
    async function upload(event) {
        event.preventDefault(); const file = fileInput.current.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) { setError('Choose a file smaller than 10 MB.'); return; }
        const data = new FormData(); data.append('file', file); data.append('type', type);
        if (await mutate(() => uploadDoctorDocument(data), 'Document uploaded and awaiting review.')) fileInput.current.value = '';
    }
    async function download(document) {
        setBusy(true); setError('');
        try {
            const response = await downloadDoctorDocument(document.id);
            const url = URL.createObjectURL(response.data);
            const anchor = window.document.createElement('a');
            anchor.href = url; anchor.download = document.original_name;
            window.document.body.appendChild(anchor); anchor.click(); anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (err) {
            let detail = 'Could not download the document. It may no longer be available.';
            if (err.response?.data instanceof Blob) {
                try { detail = JSON.parse(await err.response.data.text()).message || detail; } catch { /* Keep the readable fallback. */ }
            } else detail = apiError(err, detail);
            setError(detail);
        } finally { setBusy(false); }
    }
    return <main className="max-w-4xl mx-auto p-6 space-y-6">
        <h1 className="text-3xl font-bold">Professional documents</h1>
        <p>Upload your credentials for review. Files are private and accessible to you and authorized administrators.</p>
        {error && <div role="alert" className="p-4 bg-red-50 text-red-800">{error} <button disabled={busy || loading} onClick={refresh}>Reload documents</button></div>}
        {message && <p role="status" className="p-4 bg-green-50 text-green-800">{message}</p>}
        <form onSubmit={upload} className="bg-white border rounded-xl p-6 space-y-4">
            <h2 className="text-xl font-semibold">Upload a document</h2>
            <fieldset disabled={busy || loading} className="space-y-4">
                <div><label htmlFor="document-type">Document type</label><select id="document-type" className="block border rounded p-2 w-full" value={type} onChange={event => setType(event.target.value)}>{Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                <div><label htmlFor="document-file">File</label><input id="document-file" className="block border rounded p-2 w-full" type="file" required ref={fileInput} accept=".pdf,.jpg,.jpeg,.png" aria-describedby="file-help" /><p id="file-help">PDF, JPEG or PNG, up to 10 MB.</p></div>
                <button className="bg-blue-700 text-white rounded px-4 py-2" type="submit">{busy ? 'Working…' : 'Upload document'}</button>
            </fieldset>
        </form>
        <section className="bg-white border rounded-xl p-6 space-y-4">
            <h2 className="text-xl font-semibold">Your documents</h2>
            {loading ? <p role="status">Loading documents…</p> : documents.length === 0 ? <p>No documents uploaded.</p> : documents.map(document => <article key={document.id} className="border-t pt-4 space-y-2">
                <h3 className="font-semibold break-all">{document.original_name}</h3>
                <p>{types[document.type] || document.type} · {statuses[document.status] || document.status}</p>
                {document.rejection_reason && <p className="text-red-800">Review feedback: {document.rejection_reason}</p>}
                {document.file_available === false && <p className="text-red-800">This file is unavailable. Upload a new copy for review. If this credential is approved, contact support.</p>}
                <div className="flex flex-wrap gap-4">
                    <button disabled={busy || document.file_available === false} onClick={() => download(document)}>Download {document.original_name}</button>
                    {document.status !== 'approved' && (removing === document.id ? <div><span>Delete this document? It will be removed from review and its private file queued for cleanup.</span> <button disabled={busy} onClick={() => mutate(() => deleteDoctorDocument(document.id), 'Document removed. Private file cleanup is queued.')}>Confirm deletion</button> <button disabled={busy} onClick={() => setRemoving(null)}>Keep document</button></div>
                        : <button disabled={busy} onClick={() => setRemoving(document.id)}>Delete {document.original_name}</button>)}
                    {document.status === 'approved' && <p>Approved credentials cannot be deleted.</p>}
                </div>
            </article>)}
        </section>
    </main>;
}
