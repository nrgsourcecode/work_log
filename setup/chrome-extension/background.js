let endpoint = null;
let port = null;

async function getConfig() {
    const url = chrome.runtime.getURL('config.json');
    const { server } = await fetch(url).then((response) => response.json());
    endpoint = server.endpoint;
    port = server.port;
}

let lastUrl = '__INIT__';

async function publish(url) {
    if (url === lastUrl) {
        return;
    }

    lastUrl = url;
    if (!endpoint || !port) {
        await getConfig();
    }

    const postUrl = `http://127.0.0.1:${port}${endpoint}`;

    try {
        await fetch(postUrl, {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify({
                url: url
            })
        });
    } catch (e) {
        // PHP daemon not running
    }
}

async function updateCurrentUrl() {
    chrome.windows.getLastFocused({ populate: true }, async (window) => {
        if (chrome.runtime.lastError || !window) {
            publish(null);
            return;
        }

        /*
         * IMPORTANT
         *
         * If the focused window is Incognito,
         * never expose any URL.
         */

        if (window.incognito) {
            publish(null);
            return;
        }

        const activeTab = window.tabs.find((tab) => tab.active);

        if (!activeTab || !activeTab.url) {
            publish(null);
            return;
        }

        publish(activeTab.url);
    });
}

chrome.tabs.onActivated.addListener(updateCurrentUrl);

chrome.tabs.onUpdated.addListener((tabId, changeInfo) => {
    if (changeInfo.status === 'complete') updateCurrentUrl();
});

chrome.windows.onFocusChanged.addListener(updateCurrentUrl);

chrome.webNavigation.onHistoryStateUpdated.addListener(updateCurrentUrl);

updateCurrentUrl();
