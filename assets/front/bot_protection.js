const originalFetch = window.fetch.bind(window);
let verificationPending = false;

window.fetch = async (...args) => {
    const response = await originalFetch(...args);
    const challenge = response.headers.get('X-Bot-Challenge');

    if (response.status === 429 && challenge && !verificationPending) {
        const source = new URL(response.url, window.location.origin);
        const target = new URL(challenge, window.location.origin);

        if (source.origin === window.location.origin && target.origin === window.location.origin) {
            verificationPending = true;
            target.searchParams.set('return_to', window.location.pathname + window.location.search);
            window.location.assign(target.href);
        }
    }

    return response;
};
