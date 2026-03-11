const makeRequest = async function (endpoint, data) {
    const requestParams = {
        method: 'POST',
        headers: {
            "Content-Type": "application/json"
        }
    };

    if (Object.keys(data || {}).length) {
        requestParams.body = JSON.stringify(data);
    }

    const response = await fetch(endpoint, requestParams);
    return await response.json();
}
class DinteroSessionManager {
    initSession() {
        return makeRequest('/rest/V1/dintero/checkout/session-init');
    }

    updateSession() {
        return makeRequest('/rest/V1/dintero/checkout/session-update');
    }

    updateTotals(sessionId) {
        return makeRequest('/rest/V1/dintero/checkout/update-totals', {sessionId: sessionId});
    }

    validateSession(sessionId) {
        return makeRequest('/rest/V1/dintero/checkout/session-validate', {sessionId: sessionId});
    }
}
export default new DinteroSessionManager();
