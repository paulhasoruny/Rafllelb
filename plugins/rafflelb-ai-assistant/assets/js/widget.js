(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    ready(function () {
        var config = window.RaffleLBAssistant;
        var root = document.getElementById('rlb-ai');
        if (!config || !root) {
            return;
        }

        var panel = document.getElementById('rlb-ai-panel');
        var launcher = root.querySelector('[data-rlb-ai-launcher]');
        var closeButton = root.querySelector('[data-rlb-ai-close]');
        var conversation = root.querySelector('.rlb-ai__conversation');
        var messages = root.querySelector('[data-rlb-ai-messages]');
        var suggestionButtons = root.querySelectorAll('[data-rlb-ai-suggestion]');
        var dateLabel = root.querySelector('[data-rlb-ai-date]');
        var form = root.querySelector('[data-rlb-ai-form]');
        var input = root.querySelector('[data-rlb-ai-input]');
        var send = root.querySelector('[data-rlb-ai-send]');
        var history = [];
        var busy = false;
        var priorFocus = null;
        var closeTimer = 0;
        var tooltipTimer = 0;
        var quickQuestions = Array.isArray(config.quickQuestions) ? config.quickQuestions : [];
        var quickQuestionPrompts = {};
        quickQuestions.forEach(function (question) {
            if (!question || typeof question.prompt !== 'string') {
                return;
            }
            var index = Number(question.index);
            if (Number.isInteger(index) && index >= 0 && index < 3) {
                quickQuestionPrompts[index] = question.prompt;
            }
        });

        function sessionId() {
            var key = 'rafflelb_ai_session';
            var existing = window.sessionStorage ? window.sessionStorage.getItem(key) : '';
            if (existing && /^[A-Za-z0-9_-]{16,64}$/.test(existing)) {
                return existing;
            }
            var bytes = new Uint8Array(18);
            if (window.crypto && window.crypto.getRandomValues) {
                window.crypto.getRandomValues(bytes);
            } else {
                for (var i = 0; i < bytes.length; i += 1) {
                    bytes[i] = Math.floor(Math.random() * 256);
                }
            }
            var generated = Array.prototype.map.call(bytes, function (byte) {
                return byte.toString(16).padStart(2, '0');
            }).join('');
            if (window.sessionStorage) {
                window.sessionStorage.setItem(key, generated);
            }
            return generated;
        }

        var browserSession = sessionId();

        function prefersReducedMotion() {
            return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        function timeLabel(date) {
            try {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (error) {
                return '';
            }
        }

        function setOpen(open) {
            window.clearTimeout(closeTimer);
            window.clearTimeout(tooltipTimer);
            launcher.removeAttribute('data-tooltip');
            launcher.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                priorFocus = document.activeElement;
                root.setAttribute('data-open', 'true');
                panel.hidden = false;
                panel.setAttribute('data-state', 'opening');
                window.requestAnimationFrame(function () {
                    panel.setAttribute('data-state', 'open');
                });
                window.setTimeout(function () {
                    if (!panel.hidden) {
                        input.focus();
                    }
                }, prefersReducedMotion() ? 0 : 40);
                return;
            }

            if (panel.hidden) {
                root.setAttribute('data-open', 'false');
                return;
            }

            panel.setAttribute('data-state', 'closing');
            closeTimer = window.setTimeout(function () {
                panel.hidden = true;
                panel.setAttribute('data-state', 'closed');
                root.setAttribute('data-open', 'false');
                if (priorFocus && typeof priorFocus.focus === 'function') {
                    priorFocus.focus();
                } else {
                    launcher.focus();
                }
            }, prefersReducedMotion() ? 0 : 190);
        }

        function isAllowedSiteUrl(candidate) {
            try {
                var target = new URL(candidate);
                var site = new URL(config.siteOrigin);
                return (target.protocol === 'https:' || target.protocol === 'http:') && target.origin === site.origin;
            } catch (error) {
                return false;
            }
        }

        function appendSafeInline(container, text) {
            var tokenPattern = /\*\*([^*\n]+)\*\*|\[([^\]\n]{1,120})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s]+)/g;
            var position = 0;
            var match;

            while ((match = tokenPattern.exec(text)) !== null) {
                if (match.index > position) {
                    container.appendChild(document.createTextNode(text.slice(position, match.index)));
                }

                if (match[1] !== undefined) {
                    var strong = document.createElement('strong');
                    strong.textContent = match[1];
                    container.appendChild(strong);
                    position = tokenPattern.lastIndex;
                    continue;
                }

                if (match[2] !== undefined && match[3] !== undefined) {
                    if (isAllowedSiteUrl(match[3])) {
                        var markdownLink = document.createElement('a');
                        markdownLink.href = match[3];
                        markdownLink.textContent = match[2];
                        markdownLink.rel = 'noopener';
                        container.appendChild(markdownLink);
                    } else {
                        container.appendChild(document.createTextNode(match[2]));
                    }
                    position = tokenPattern.lastIndex;
                    continue;
                }

                var raw = match[4] || '';
                var trailing = raw.match(/[),.!?:;]+$/);
                var candidate = trailing ? raw.slice(0, -trailing[0].length) : raw;
                if (isAllowedSiteUrl(candidate)) {
                    var link = document.createElement('a');
                    link.href = candidate;
                    link.textContent = candidate;
                    link.rel = 'noopener';
                    container.appendChild(link);
                } else {
                    container.appendChild(document.createTextNode(candidate));
                }
                if (trailing) {
                    container.appendChild(document.createTextNode(trailing[0]));
                }
                position = tokenPattern.lastIndex;
            }

            if (position < text.length) {
                container.appendChild(document.createTextNode(text.slice(position)));
            }
        }

        function appendFormattedMessage(container, text) {
            var lines = String(text).replace(/\r\n?/g, '\n').split('\n');
            var previousWasBlank = false;
            var renderedAny = false;

            lines.forEach(function (rawLine) {
                var line = rawLine.trimEnd();
                if (line.trim() === '') {
                    previousWasBlank = renderedAny;
                    return;
                }

                var block = document.createElement('div');
                var trimmed = line.trimStart();
                var isBullet = /^[-•]\s+/.test(trimmed);
                block.className = 'rlb-ai__text-line';

                if (isBullet) {
                    block.classList.add('rlb-ai__text-line--item');
                    trimmed = trimmed.replace(/^[-•]\s+/, '');
                } else if (previousWasBlank) {
                    block.classList.add('rlb-ai__text-line--spaced');
                }

                appendSafeInline(block, trimmed);
                container.appendChild(block);
                renderedAny = true;
                previousWasBlank = false;
            });

            if (!renderedAny) {
                container.appendChild(document.createTextNode(String(text)));
            }
        }

        function addAssistantAvatar(row) {
            var avatar = document.createElement('span');
            var image = document.createElement('img');
            avatar.className = 'rlb-ai__message-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            image.src = String(config.iconUrl || '');
            image.alt = '';
            avatar.appendChild(image);
            row.appendChild(avatar);
        }

        function addTimestamp(content, date) {
            var timestamp = document.createElement('time');
            var label = timeLabel(date);
            timestamp.className = 'rlb-ai__timestamp';
            timestamp.dateTime = date.toISOString();
            timestamp.textContent = label;
            content.appendChild(timestamp);
        }

        function addMessage(role, text, tone) {
            var now = new Date();
            var row = document.createElement('div');
            var content = document.createElement('div');
            var bubble = document.createElement('div');
            row.className = 'rlb-ai__message rlb-ai__message--' + role;
            content.className = 'rlb-ai__message-content';
            bubble.className = 'rlb-ai__bubble';
            if (tone === 'system') {
                row.classList.add('rlb-ai__message--system');
            }
            if (role === 'assistant') {
                addAssistantAvatar(row);
            }
            appendFormattedMessage(bubble, String(text));
            content.appendChild(bubble);
            addTimestamp(content, now);
            row.appendChild(content);
            messages.appendChild(row);
            conversation.scrollTop = conversation.scrollHeight;
            return row;
        }

        function addTyping() {
            var row = document.createElement('div');
            var content = document.createElement('div');
            var bubble = document.createElement('span');
            row.className = 'rlb-ai__message rlb-ai__message--assistant';
            row.setAttribute('role', 'status');
            row.setAttribute('aria-label', 'RaffleLB Assistant is typing');
            content.className = 'rlb-ai__message-content';
            bubble.className = 'rlb-ai__typing';
            addAssistantAvatar(row);
            for (var i = 0; i < 3; i += 1) {
                bubble.appendChild(document.createElement('i'));
            }
            content.appendChild(bubble);
            row.appendChild(content);
            messages.appendChild(row);
            conversation.scrollTop = conversation.scrollHeight;
            return row;
        }

        function resizeInput() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
            input.style.overflowY = input.scrollHeight > 96 ? 'auto' : 'hidden';
        }

        function updateComposer() {
            resizeInput();
            send.disabled = busy || input.value.trim() === '';
        }

        function beginConversation() {
            root.setAttribute('data-conversation', 'active');
        }

        function submitCurrentInput() {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                send.click();
            }
        }

        function showInitialTooltip() {
            var key = 'rafflelb_ai_tooltip_seen';
            if (!window.sessionStorage || window.sessionStorage.getItem(key)) {
                return;
            }
            window.sessionStorage.setItem(key, '1');
            launcher.setAttribute('data-tooltip', 'visible');
            tooltipTimer = window.setTimeout(function () {
                launcher.removeAttribute('data-tooltip');
            }, 6000);
        }

        launcher.addEventListener('click', function () { setOpen(true); });
        closeButton.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
            }
        });
        input.addEventListener('input', updateComposer);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitCurrentInput();
            }
        });

        Array.prototype.forEach.call(suggestionButtons, function (button) {
            button.addEventListener('click', function () {
                var index = Number(button.getAttribute('data-rlb-ai-suggestion'));
                var prompt = quickQuestionPrompts[index];
                if (busy || typeof prompt !== 'string' || prompt.trim() === '') {
                    return;
                }
                input.value = prompt;
                updateComposer();
                submitCurrentInput();
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var text = input.value.trim();
            if (busy || !text || text.length > Number(config.maxLength || 1500)) {
                return;
            }

            var requestHistory = history.slice(-12);
            beginConversation();
            addMessage('user', text);
            input.value = '';
            busy = true;
            input.disabled = true;
            updateComposer();
            var typing = addTyping();

            var requestHeaders = {
                'Content-Type': 'application/json',
                'X-RaffleLB-AI-Session': browserSession
            };
            if (typeof config.restNonce === 'string' && config.restNonce !== '') {
                requestHeaders['X-WP-Nonce'] = config.restNonce;
            }

            fetch(config.restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: requestHeaders,
                body: JSON.stringify({ message: text, history: requestHistory })
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok || !data || typeof data.message !== 'string') {
                        var serverMessage = data && typeof data.message === 'string' ? data.message.trim() : '';
                        throw new Error(serverMessage || 'I’m sorry, the assistant could not respond right now. Please try again later.');
                    }
                    return data.message;
                });
            }).then(function (answer) {
                typing.remove();
                addMessage('assistant', answer);
                history.push({ role: 'user', content: text });
                history.push({ role: 'assistant', content: answer.slice(0, 1500) });
                history = history.slice(-12);
            }).catch(function (error) {
                typing.remove();
                var message = error && typeof error.message === 'string' && error.message.trim() !== ''
                    ? error.message.trim()
                    : 'I’m sorry, the assistant could not respond right now. Please try again later.';
                addMessage('assistant', message, 'system');
            }).finally(function () {
                busy = false;
                input.disabled = false;
                updateComposer();
                if (!panel.hidden && panel.getAttribute('data-state') !== 'closing') {
                    input.focus();
                }
            });
        });

        root.setAttribute('data-conversation', 'welcome');
        if (dateLabel) {
            dateLabel.textContent = 'Today';
        }
        updateComposer();
        window.setTimeout(showInitialTooltip, prefersReducedMotion() ? 0 : 650);
    });
}());
