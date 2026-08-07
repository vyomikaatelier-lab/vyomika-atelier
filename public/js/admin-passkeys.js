(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
        ?? document.querySelector('input[name="_token"]')?.value
        ?? '';

    function base64UrlToBuffer(value) {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const buffer = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i += 1) {
            buffer[i] = raw.charCodeAt(i);
        }
        return buffer;
    }

    function bufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i += 1) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function prepareCreationOptions(options) {
        const publicKey = structuredClone(options);
        publicKey.challenge = base64UrlToBuffer(publicKey.challenge);
        publicKey.user = {
            ...publicKey.user,
            id: base64UrlToBuffer(publicKey.user.id),
        };
        if (Array.isArray(publicKey.excludeCredentials)) {
            publicKey.excludeCredentials = publicKey.excludeCredentials.map((cred) => ({
                ...cred,
                id: base64UrlToBuffer(cred.id),
            }));
        }
        return publicKey;
    }

    function prepareRequestOptions(options) {
        const publicKey = structuredClone(options);
        publicKey.challenge = base64UrlToBuffer(publicKey.challenge);
        if (Array.isArray(publicKey.allowCredentials) && publicKey.allowCredentials.length > 0) {
            publicKey.allowCredentials = publicKey.allowCredentials.map((cred) => ({
                ...cred,
                id: base64UrlToBuffer(cred.id),
            }));
        }
        return publicKey;
    }

    function serializeAttestation(credential) {
        const response = credential.response;
        return {
            id: credential.id,
            rawId: bufferToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                attestationObject: bufferToBase64Url(response.attestationObject),
                clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                transports: response.getTransports ? response.getTransports() : undefined,
            },
            clientExtensionResults: credential.getClientExtensionResults(),
        };
    }

    function serializeAssertion(credential) {
        const response = credential.response;
        return {
            id: credential.id,
            rawId: bufferToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                authenticatorData: bufferToBase64Url(response.authenticatorData),
                clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                signature: bufferToBase64Url(response.signature),
                userHandle: response.userHandle ? bufferToBase64Url(response.userHandle) : null,
            },
            clientExtensionResults: credential.getClientExtensionResults(),
        };
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = data.message
                ?? data.errors?.credential?.[0]
                ?? data.errors?.totp_code?.[0]
                ?? data.errors?.current_password?.[0]
                ?? 'Passkey request failed.';
            throw new Error(message);
        }

        return data;
    }

    async function getJson(url) {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message ?? 'Passkey request failed.');
        }

        return data;
    }

    async function login(optionsUrl, verifyUrl, remember = false) {
        if (!window.PublicKeyCredential) {
            throw new Error('Passkeys are not supported in this browser.');
        }

        const { options } = await getJson(optionsUrl);
        const credential = await navigator.credentials.get({
            publicKey: prepareRequestOptions(options),
        });

        if (!credential) {
            throw new Error('Passkey sign-in was cancelled.');
        }

        const result = await postJson(verifyUrl, {
            credential: serializeAssertion(credential),
            remember,
        });

        if (result.redirect) {
            window.location.href = result.redirect;
            return;
        }

        window.location.reload();
    }

    async function register(optionsUrl, storeUrl, payload) {
        if (!window.PublicKeyCredential) {
            throw new Error('Passkeys are not supported in this browser.');
        }

        const { options } = await postJson(optionsUrl, payload);
        const credential = await navigator.credentials.create({
            publicKey: prepareCreationOptions(options),
        });

        if (!credential) {
            throw new Error('Passkey registration was cancelled.');
        }

        return postJson(storeUrl, {
            ...payload,
            credential: serializeAttestation(credential),
        });
    }

    window.AdminPasskeys = {
        login,
        register,
        postJson,
    };
})();
