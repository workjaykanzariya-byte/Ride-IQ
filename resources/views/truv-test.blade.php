<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truv Driver Test</title>
    <script src="https://cdn.truv.com/bridge.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f9;
            margin: 0;
            padding: 24px;
            color: #1f2937;
        }

        .card {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        input[type="text"] {
            width: 100%;
            max-width: 420px;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        button {
            background: #2563eb;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        #logs {
            background: #0b1020;
            color: #d1fae5;
            border-radius: 8px;
            padding: 14px;
            min-height: 260px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-size: 12px;
            line-height: 1.5;
            margin-top: 12px;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Truv Bridge Driver Sandbox Test</h2>
    <p>Use a valid driver API token, then click the button to run the full create-token → bridge → exchange-token → status/report flow.</p>

    <div class="row">
        <label for="driverToken"><strong>Driver Bearer Token</strong></label>
    </div>
    <div class="row">
        <input id="driverToken" type="text" value="TEST_DRIVER_TOKEN" />
        <button id="connectBtn" type="button">Connect Driver Account</button>
    </div>

    <div id="logs"></div>
</div>

<script>
    const connectBtn = document.getElementById('connectBtn');
    const driverTokenInput = document.getElementById('driverToken');
    const logsElement = document.getElementById('logs');

    const log = (title, payload = null) => {
        const time = new Date().toISOString();
        const message = payload === null
            ? `[${time}] ${title}`
            : `[${time}] ${title}\n${JSON.stringify(payload, null, 2)}`;

        logsElement.textContent += `${message}\n\n`;
        logsElement.scrollTop = logsElement.scrollHeight;
    };

    const request = async (url, options = {}) => {
        const token = driverTokenInput.value.trim();

        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            ...(options.headers || {})
        };

        const response = await fetch(url, {
            ...options,
            headers
        });

        let data;
        try {
            data = await response.json();
        } catch (error) {
            data = { message: 'Non-JSON response', raw: await response.text() };
        }

        if (!response.ok) {
            throw new Error(JSON.stringify({ status: response.status, data }, null, 2));
        }

        return data;
    };

    const fetchStatusAndReport = async () => {
        const statusResponse = await request('/api/driver/truv/status', { method: 'GET' });
        log('GET /api/driver/truv/status response', statusResponse);

        const reportResponse = await request('/api/driver/truv/report', { method: 'GET' });
        log('GET /api/driver/truv/report response', reportResponse);
    };

    const launchBridge = async (bridgeToken) => {
        if (!window.TruvBridge || typeof window.TruvBridge.init !== 'function') {
            throw new Error('TruvBridge SDK is not loaded.');
        }

        const bridge = window.TruvBridge.init({
            bridgeToken,
            onLoad() {
                log('Truv Bridge callback: onLoad');
            },
            onEvent(eventType, payload) {
                log('Truv Bridge callback: onEvent', { eventType, payload });
            },
            async onSuccess(publicToken, metadata) {
                log('Truv Bridge callback: onSuccess', { public_token: publicToken, metadata });

                try {
                    const exchangeResponse = await request('/api/driver/truv/exchange-token', {
                        method: 'POST',
                        body: JSON.stringify({ public_token: publicToken })
                    });

                    log('POST /api/driver/truv/exchange-token response', exchangeResponse);
                    await fetchStatusAndReport();
                } catch (error) {
                    log('Exchange/status/report flow failed', { error: error.message });
                }
            },
            onClose() {
                log('Truv Bridge callback: onClose');
            },
            onError(error) {
                log('Truv Bridge callback: onError', error);
            }
        });

        bridge.open();
    };

    connectBtn.addEventListener('click', async () => {
        connectBtn.disabled = true;
        logsElement.textContent = '';

        try {
            log('POST /api/driver/truv/create-token started');
            const createTokenResponse = await request('/api/driver/truv/create-token', {
                method: 'POST'
            });

            log('POST /api/driver/truv/create-token response', createTokenResponse);

            const bridgeToken = createTokenResponse?.data?.bridge_token;
            if (!bridgeToken) {
                throw new Error('bridge_token was not returned by backend.');
            }

            await launchBridge(bridgeToken);
        } catch (error) {
            log('Connect flow failed', { error: error.message });
        } finally {
            connectBtn.disabled = false;
        }
    });
</script>
</body>
</html>
