const AL_API = '../backend/admin-activity-logs.php';


async function logActivity(action, module = 'Reports') {
    try {
        await fetch(AL_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, module }),
        });
    } catch (err) {
        console.warn('logActivity failed:', err);
    }
}