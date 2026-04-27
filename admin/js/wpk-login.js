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

    function getMessageNode(btn) {
        var root = btn && btn.closest ? btn.closest('.wpk-login-passkey-wrap, .wpk-shortcode-login-wrap') : null;
        if (!root) {
            return document.getElementById('wpk-passkey-login-message');
        }
        return root.querySelector('.wpk-login-message') || document.getElementById('wpk-passkey-login-message');
    }

    function setMessage(btn, text) {
        var node = getMessageNode(btn);
        if (!node) return;
        node.textContent = text;
        node.style.display = text ? '' : 'none';
    }

    function setButtonState(btn, busy) {
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('wpk-btn-busy', busy);
        if (busy) {
            btn.setAttribute('data-original-html', btn.innerHTML);
            btn.textContent = WPKLogin.messages.signingIn || 'Signing in…';
        } else {
            var orig = btn.getAttribute('data-original-html');
            if (orig) btn.innerHTML = orig;
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
        if (!resp.ok) {
            throw new Error(WPKLogin.messages.genericError);
        }
        return resp.json();
    }

    // ── Sign-in flow ─────────────────────────────────────────────────────────

    async function signInWithPasskey(btn) {
        setMessage(btn, '');

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

        var redirectUrl = finishResp.data.redirect;
        try {
            var parsed = new URL(redirectUrl, window.location.origin);
            if (parsed.origin !== window.location.origin) {
                throw new Error('Unexpected redirect origin');
            }
            window.location.href = parsed.href;
        } catch (e) {
            window.location.href = window.location.origin;
        }
    }

    // ── Init ────────────────────────────────────────────────────────────────

    function init() {
        var buttons = Array.prototype.slice.call(document.querySelectorAll('#wpk-signin-passkey, [data-wpk-passkey-login-btn="1"]'));
        if (!buttons.length) return;

        // Graceful degradation for unsupported browsers
        if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.get) {
            buttons.forEach(function (btn) {
                btn.disabled = true;
                btn.classList.add('wpk-btn-disabled');
                btn.setAttribute('aria-disabled', 'true');
                btn.title = WPKLogin.messages.notSupported;
                setMessage(btn, WPKLogin.messages.notSupported);
            });
            return;
        }

        buttons.forEach(function (btn) {
            btn.classList.remove('wpk-btn-disabled');
            btn.removeAttribute('aria-disabled');
        });

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

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                setButtonState(btn, true);
                signInWithPasskey(btn)
                    .catch(function (err) {
                        // Ignore user-cancelled gestures silently
                        if (err && err.name === 'NotAllowedError') {
                            setMessage(btn, '');
                        } else {
                            setMessage(btn, (err && err.message) || WPKLogin.messages.genericError);
                        }
                    })
                    .finally(function () {
                        setButtonState(btn, false);
                    });
                });
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
