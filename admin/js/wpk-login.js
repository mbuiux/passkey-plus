/* global WPKLogin */
(function () {
    'use strict';

    // ── Base64url helpers ───────────────────────────────────────────────────

    function b64urlToBuffer(input) {
        var base64 = input.replace(/-/g, '+').replace(/_/g, '/');
        var pad = base64.length % 4;
        if (pad) base64 += '='.repeat(4 - pad);
        var binary = atob(base64);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes.buffer;
    }

    function bufferToB64url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // ── DOM helpers ─────────────────────────────────────────────────────────

    function setMessage(text) {
        var node = document.getElementById('wpk-passkey-login-message');
        if (!node) return;
        node.textContent = text;
        node.style.display = text ? '' : 'none';
    }

    function setButtonState(btn, busy) {
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('wpk-btn-busy', busy);
        if (busy) {
            btn.setAttribute('data-original-text', btn.textContent);
            btn.textContent = WPKLogin.messages.signingIn || 'Signing in…';
        } else {
            var orig = btn.getAttribute('data-original-text');
            if (orig) btn.textContent = orig;
        }
    }

    function hydrateGetOptions(options) {
        options.publicKey.challenge = b64urlToBuffer(options.publicKey.challenge);
        if (Array.isArray(options.publicKey.allowCredentials)) {
            options.publicKey.allowCredentials = options.publicKey.allowCredentials.map(function (item) {
                item.id = b64urlToBuffer(item.id);
                return item;
            });
        }
        return options;
    }

    function getLoginIdentifier() {
        var node = document.getElementById('user_login');
        return node ? (node.value || '').trim() : '';
    }

    // ── AJAX ────────────────────────────────────────────────────────────────

    async function postForm(data) {
        var resp = await fetch(WPKLogin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
        });
        return resp.json();
    }

    // ── Sign-in flow ─────────────────────────────────────────────────────────

    async function signInWithPasskey() {
        setMessage('');

        var beginData = new FormData();
        beginData.append('action', 'wpk_begin_login');
        beginData.append('nonce',  WPKLogin.nonce);

        var identifier = getLoginIdentifier();
        if (identifier) {
            beginData.append('login', identifier);
        }

        var beginResp = await postForm(beginData);
        if (!beginResp || !beginResp.success) {
            throw new Error((beginResp && beginResp.data && beginResp.data.message) || WPKLogin.messages.genericError);
        }

        var options    = hydrateGetOptions(beginResp.data.options);
        var credential = await navigator.credentials.get(options);

        var finishData = new FormData();
        finishData.append('action',            'wpk_finish_login');
        finishData.append('nonce',             WPKLogin.nonce);
        finishData.append('token',             beginResp.data.token);
        finishData.append('id',                bufferToB64url(credential.rawId));
        finishData.append('clientDataJSON',    bufferToB64url(credential.response.clientDataJSON));
        finishData.append('authenticatorData', bufferToB64url(credential.response.authenticatorData));
        finishData.append('signature',         bufferToB64url(credential.response.signature));

        if (credential.response.userHandle) {
            finishData.append('userHandle', bufferToB64url(credential.response.userHandle));
        }

        var finishResp = await postForm(finishData);
        if (!finishResp || !finishResp.success || !finishResp.data || !finishResp.data.redirect) {
            throw new Error((finishResp && finishResp.data && finishResp.data.message) || WPKLogin.messages.genericError);
        }

        window.location.href = finishResp.data.redirect;
    }

    // ── Init ────────────────────────────────────────────────────────────────

    function init() {
        var btn = document.getElementById('wpk-signin-passkey');
        if (!btn) return;

        // Graceful degradation for unsupported browsers
        if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.get) {
            btn.disabled = true;
            btn.title = WPKLogin.messages.notSupported;
            setMessage(WPKLogin.messages.notSupported);
            return;
        }

        // Optional: auto-trigger discoverable credential prompt on page load
        // (usernameless passkey sign-in — the credential chooser appears immediately).
        // This is disabled by default to keep the UX consistent with existing flows.
        // Uncomment to enable:
        //
        // if (window.PublicKeyCredential.isConditionalMediationAvailable) {
        //     window.PublicKeyCredential.isConditionalMediationAvailable().then(function (available) {
        //         if (available) signInWithPasskey().catch(function () {});
        //     });
        // }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            setButtonState(btn, true);
            signInWithPasskey()
                .catch(function (err) {
                    // Ignore user-cancelled gestures silently
                    if (err && err.name === 'NotAllowedError') {
                        setMessage('');
                    } else {
                        setMessage(err.message || WPKLogin.messages.genericError);
                    }
                })
                .finally(function () {
                    setButtonState(btn, false);
                });
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
