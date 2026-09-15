(function () {
    'use strict';

    function one(root, selector) {
        return root.querySelector(selector);
    }

    function all(root, selector) {
        return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    function secureNumber(max) {
        if (window.crypto && window.crypto.getRandomValues) {
            var values = new Uint32Array(1);
            window.crypto.getRandomValues(values);
            return (values[0] % max) + 1;
        }
        return Math.floor(Math.random() * max) + 1;
    }

    function entryLabel(number) {
        return '#' + String(number).padStart(3, '0');
    }

    function initDemo(root) {
        var demoEntryCount = 100;
        var start = one(root, '[data-demo-start]');
        var reset = one(root, '[data-demo-reset]');
        var pool = one(root, '[data-demo-pool]');
        var total = one(root, '[data-demo-total]');
        var chamber = one(root, '[data-demo-chamber]');
        var chamberNumber = one(root, '[data-demo-chamber-number]');
        var winner = one(root, '[data-demo-winner]');
        var resultNote = one(root, '[data-demo-result-note]');
        var engineLabel = one(root, '[data-demo-engine-label]');
        var progress = one(root, '[data-demo-progress]');
        var progressLabel = one(root, '[data-demo-progress-label]');
        var percent = one(root, '[data-demo-percent]');
        var log = one(root, '[data-demo-log]');
        var timers = [];
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function later(fn, delay) {
            var timer = window.setTimeout(fn, delay);
            timers.push(timer);
        }

        function clearTimers() {
            timers.forEach(window.clearTimeout);
            timers = [];
        }

        function paintPool(count) {
            pool.textContent = '';
            var fragment = document.createDocumentFragment();
            var visible = Math.min(30, count);
            for (var i = 1; i <= visible; i += 1) {
                var tile = document.createElement('span');
                tile.textContent = entryLabel(i);
                fragment.appendChild(tile);
            }
            if (count > visible) {
                var more = document.createElement('span');
                more.className = 'rlse-more';
                more.textContent = '+' + (count - visible) + ' more';
                fragment.appendChild(more);
            }
            pool.appendChild(fragment);
        }

        function setStep(step) {
            var timeline = one(root, '[data-demo-timeline]');
            if (timeline) timeline.style.setProperty('--rlse-step-progress', ((step - 1) * 21) + '%');
            all(root, '[data-demo-step]').forEach(function (item) {
                var value = Number(item.getAttribute('data-demo-step'));
                item.classList.toggle('is-done', value < step);
                item.classList.toggle('is-current', value === step);
            });
        }

        function addLog(message) {
            if (log.children.length === 1 && log.children[0].textContent.indexOf('Ready to begin') !== -1) {
                log.textContent = '';
            }
            var item = document.createElement('li');
            var time = document.createElement('time');
            var text = document.createElement('span');
            time.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            text.textContent = message;
            item.appendChild(time);
            item.appendChild(text);
            log.appendChild(item);
        }

        function setProgress(value, label) {
            progress.style.width = value + '%';
            percent.textContent = value + '%';
            progressLabel.textContent = label;
        }

        function doReset() {
            clearTimers();
            var count = demoEntryCount;
            paintPool(count);
            total.textContent = count;
            start.disabled = false;
            chamber.classList.remove('is-running', 'is-complete');
            chamberNumber.textContent = '—';
            winner.textContent = '—';
            engineLabel.textContent = 'Ready for a secure browser-only simulation.';
            resultNote.querySelector('strong').textContent = 'Awaiting simulation';
            resultNote.querySelector('small').textContent = 'No official result is affected.';
            one(root, '[data-demo-lock] strong').textContent = 'Entry pool prepared';
            one(root, '[data-demo-lock] small').textContent = 'Start the demonstration to lock the simulated pool.';
            log.innerHTML = '<li class="rlse-log-ready"><time>—</time><span>Ready to begin demonstration</span></li>';
            setProgress(0, 'Ready');
            setStep(1);
        }

        function doStart() {
            doReset();
            var count = demoEntryCount;
            var selected = secureNumber(count);
            start.disabled = true;
            addLog('Raffle opened');
            later(function () {
                setStep(2);
                addLog('Demo entry collection closed');
            }, 900);
            later(function () {
                setStep(3);
                one(root, '[data-demo-lock] strong').textContent = 'Demo pool locked';
                one(root, '[data-demo-lock] small').textContent = 'No more simulated entries can be added.';
                addLog('Demo entries locked (' + count + ')');
            }, 1900);
            later(function () {
                setStep(4);
                chamber.classList.add('is-running');
                engineLabel.textContent = 'Running secure browser-only simulation…';
                addLog('Secure demo selection running');
                var value = 0;
                if (reduced) {
                    chamberNumber.textContent = '…';
                    setProgress(80, 'Simulation running…');
                    return;
                }
                function tick() {
                    value = Math.min(96, value + secureNumber(4) + 1);
                    chamberNumber.textContent = entryLabel(secureNumber(count));
                    setProgress(value, 'Simulation running…');
                    if (value < 96) later(tick, 180);
                }
                tick();
            }, 3000);
            later(function () {
                setStep(5);
                chamber.classList.remove('is-running');
                chamber.classList.add('is-complete');
                chamberNumber.textContent = entryLabel(selected);
                winner.textContent = entryLabel(selected);
                engineLabel.textContent = 'Demo selection complete.';
                resultNote.querySelector('strong').textContent = 'Demonstration complete';
                resultNote.querySelector('small').textContent = 'This simulated result is not official.';
                setProgress(100, 'Demo complete');
                addLog('Demo winning entry: ' + entryLabel(selected));
                addLog('Simulation finished — no live data changed');
                start.disabled = false;
            }, 8500);
        }

        start.addEventListener('click', doStart);
        reset.addEventListener('click', doReset);
        doReset();
    }

    function setText(root, selector, value) {
        var element = one(root, selector);
        if (element) element.textContent = value;
    }

    function renderTimeline(root, status) {
        var timeline = one(root, '[data-bind="timeline"]');
        if (!timeline) return;
        timeline.setAttribute('data-status', status);
        var current = status === 'live' ? 1 : ((status === 'awaiting' || status === 'cancelled') ? 3 : 5);
        var subtitles = status === 'live'
            ? ['Eligible entries collected', 'Pending', 'Not locked', 'Not started', 'Pending']
            : status === 'awaiting'
                ? ['Eligible entries collected', 'Closed', 'Locked Entry Pool', 'Not started', 'Pending']
                : status === 'cancelled'
                    ? ['Eligible entries collected', 'Closed early', 'Locked Entry Pool', 'Not proceeding', 'Not applicable']
                    : ['Eligible entries collected', 'Closed', 'Locked Entry Pool', 'Recorded process complete', 'Recorded Result'];
        all(timeline, '[data-step]').forEach(function (item, index) {
            var step = index + 1;
            item.classList.toggle('is-done', step < current);
            item.classList.toggle('is-current', step === current);
            var small = item.querySelector('small');
            if (small) small.textContent = subtitles[index];
        });
    }

    function normalizeEntry(value) {
        var digits = String(value || '').replace(/[^0-9]/g, '').replace(/^0+/, '');
        return digits === '' ? (/[0]/.test(String(value || '')) ? '0' : '') : digits;
    }

    function entryRow(row) {
        var item = document.createElement('div');
        var number = document.createElement('strong');
        var participant = document.createElement('span');
        item.className = 'rlse-entry-row' + (row.winning ? ' is-winning' : '');
        item.setAttribute('role', 'listitem');
        item.setAttribute('data-entry', normalizeEntry(row.entry));
        number.className = 'rlse-entry-ticket';
        participant.className = 'rlse-entry-participant';
        number.textContent = String(row.entry || '');
        participant.textContent = String(row.participant || 'Participant ***');
        item.appendChild(number);
        if (row.winning) {
            var winning = document.createElement('span');
            winning.className = 'rlse-winning-entry';
            winning.textContent = 'WINNING ENTRY';
            item.appendChild(winning);
        }
        item.appendChild(participant);
        return item;
    }

    function renderEntryPool(root, data) {
        var host = one(root, '[data-bind="entry-grid"]');
        if (!host) return;
        host.textContent = '';
        var live = data.status === 'live';
        var available = live ? data.live_pool_available : data.locked_pool_available;
        var pool = live ? data.live_pool : data.locked_pool;
        if (!available || !pool) {
            var unavailable = document.createElement('div');
            unavailable.className = 'rlse-pool-open';
            unavailable.textContent = live
                ? 'Live entries are temporarily unavailable.'
                : 'This historical raffle has no authoritative locked-pool record. Its entry list is not published.';
            host.appendChild(unavailable);
            return;
        }

        var total = Number(live ? pool.total_entries : pool.total_locked) || 0;
        var verifier = document.createElement('div');
        var label = document.createElement('label');
        var labelText = document.createElement('span');
        var input = document.createElement('input');
        var register = document.createElement('div');
        var tableHead = document.createElement('div');
        var ticketHead = document.createElement('span');
        var participantHead = document.createElement('span');
        var list = document.createElement('div');
        var empty = document.createElement('div');
        verifier.className = 'rlse-pool-verifier';
        verifier.setAttribute('data-entry-pool', '');
        verifier.setAttribute('data-mode', live ? 'live' : 'locked');
        verifier.setAttribute('data-page', '1');
        verifier.setAttribute('data-total', total);
        verifier.setAttribute('data-loaded', (pool.entries || []).length);
        if (!live) verifier.setAttribute('data-revision', String(pool.revision || ''));
        label.className = 'rlse-entry-search';
        labelText.textContent = 'Search entry number';
        input.type = 'search';
        input.inputMode = 'numeric';
        input.autocomplete = 'off';
        input.placeholder = '#001';
        input.setAttribute('data-entry-search', '');
        input.setAttribute('aria-label', 'Search entry number');
        register.className = 'rlse-entry-register';
        tableHead.className = 'rlse-entry-table-head';
        tableHead.setAttribute('aria-hidden', 'true');
        ticketHead.textContent = 'TICKET #';
        participantHead.textContent = 'PARTICIPANT';
        tableHead.appendChild(ticketHead);
        tableHead.appendChild(participantHead);
        list.className = 'rlse-entry-list';
        list.setAttribute('data-entry-list', '');
        list.setAttribute('role', 'list');
        list.setAttribute('aria-label', live ? 'Current active entries' : 'Final eligible entries');
        (pool.entries || []).forEach(function (row) { list.appendChild(entryRow(row)); });
        empty.className = 'rlse-entry-empty';
        empty.setAttribute('data-entry-empty', '');
        empty.hidden = !live || (pool.entries || []).length > 0;
        empty.textContent = live && total === 0 ? 'No active entries yet.' : 'No matching eligible entry.';
        label.appendChild(labelText);
        label.appendChild(input);
        verifier.appendChild(label);
        register.appendChild(tableHead);
        register.appendChild(list);
        verifier.appendChild(register);
        verifier.appendChild(empty);
        if ((pool.entries || []).length < total) {
            var load = document.createElement('button');
            load.className = 'rlse-load-entries';
            load.type = 'button';
            load.setAttribute('data-load-entries', '');
            load.textContent = 'Load more entries';
            verifier.appendChild(load);
        }
        host.appendChild(verifier);
    }

    function bindEntryPool(root, productId, config) {
        var verifier = one(root, '[data-entry-pool]');
        if (!verifier || verifier.getAttribute('data-bound') === 'yes') return;
        verifier.setAttribute('data-bound', 'yes');
        var input = one(verifier, '[data-entry-search]');
        var list = one(verifier, '[data-entry-list]');
        var empty = one(verifier, '[data-entry-empty]');
        var load = one(verifier, '[data-load-entries]');
        var searchTimer = 0;
        var searchSequence = 0;
        var mode = verifier.getAttribute('data-mode') === 'live' ? 'live' : 'locked';

        function filterRows(query) {
            var matches = 0;
            all(list, '[data-entry]').forEach(function (row) {
                var match = !query || row.getAttribute('data-entry') === query;
                row.hidden = !match;
                if (match) matches += 1;
            });
            empty.textContent = query ? 'No matching eligible entry.' : 'No active entries yet.';
            empty.hidden = query
                ? matches !== 0
                : (mode === 'locked' || Number(verifier.getAttribute('data-total')) > 0);
            return matches;
        }

        function fetchPool(params) {
            var base = mode === 'live' ? config.livePoolBase : config.lockedPoolBase;
            if (!base) return Promise.reject(new Error('Pool unavailable'));
            var requestUrl = base + productId + '?' + params;
            if (mode === 'live') requestUrl += '&_rlse=' + Date.now();
            return window.fetch(requestUrl, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: mode === 'live' ? 'no-store' : 'default'
            }).then(function (response) {
                if (!response.ok) throw new Error('Pool unavailable');
                return response.json();
            }).then(function (pool) {
                if (!pool || (mode === 'locked' && String(pool.revision || '') !== verifier.getAttribute('data-revision'))) {
                    throw new Error('Pool revision changed');
                }
                if (mode === 'live' && typeof pool.total_entries !== 'undefined') {
                    verifier.setAttribute('data-total', Number(pool.total_entries || 0));
                }
                return pool;
            });
        }

        if (input) input.addEventListener('input', function () {
            var query = normalizeEntry(input.value);
            window.clearTimeout(searchTimer);
            searchSequence += 1;
            var sequence = searchSequence;
            all(list, '[data-search-result]').forEach(function (row) { row.remove(); });
            if (!query || filterRows(query)) return;
            searchTimer = window.setTimeout(function () {
                fetchPool('entry=' + encodeURIComponent(query)).then(function (pool) {
                    if (sequence !== searchSequence) return;
                    if (pool.entries && pool.entries.length) {
                        var row = entryRow(pool.entries[0]);
                        row.setAttribute('data-search-result', '');
                        list.appendChild(row);
                    }
                    filterRows(query);
                }).catch(function () {
                    if (sequence === searchSequence) filterRows(query);
                });
            }, 180);
        });

        if (load) load.addEventListener('click', function () {
            var nextPage = Number(verifier.getAttribute('data-page') || 1) + 1;
            load.disabled = true;
            load.textContent = 'Loading…';
            fetchPool('page=' + nextPage + '&per_page=60').then(function (pool) {
                (pool.entries || []).forEach(function (row) {
                    var key = normalizeEntry(row.entry);
                    if (!one(list, '[data-entry="' + key + '"]')) list.appendChild(entryRow(row));
                });
                verifier.setAttribute('data-page', nextPage);
                verifier.setAttribute('data-loaded', all(list, '[data-entry]:not([data-search-result])').length);
                if (Number(verifier.getAttribute('data-loaded')) >= Number(verifier.getAttribute('data-total'))) {
                    load.remove();
                } else {
                    load.disabled = false;
                    load.textContent = 'Load more entries';
                }
            }).catch(function () {
                load.disabled = false;
                load.textContent = 'Try loading again';
            });
        });
    }

    function poolForStatus(data) {
        return data && data.status === 'live' ? data.live_pool : (data ? data.locked_pool : null);
    }

    function poolTotal(data, pool) {
        return Number(data.status === 'live' ? pool.total_entries : pool.total_locked) || 0;
    }

    function entryPoolMatches(root, data) {
        var verifier = one(root, '[data-entry-pool]');
        var pool = poolForStatus(data);
        var available = data.status === 'live' ? data.live_pool_available : data.locked_pool_available;
        if (!verifier || !available || !pool || !Array.isArray(pool.entries)) return false;

        var mode = data.status === 'live' ? 'live' : 'locked';
        if (verifier.getAttribute('data-mode') !== mode) return false;
        if (Number(verifier.getAttribute('data-total')) !== poolTotal(data, pool)) return false;
        if (mode === 'locked' && String(verifier.getAttribute('data-revision') || '') !== String(pool.revision || '')) return false;

        var rendered = all(verifier, '[data-entry]:not([data-search-result])');
        if (rendered.length < pool.entries.length) return false;
        return pool.entries.every(function (row, index) {
            var current = rendered[index];
            var participant = one(current, '.rlse-entry-participant') || one(current, 'span:last-child');
            return current.getAttribute('data-entry') === normalizeEntry(row.entry)
                && String(participant ? participant.textContent : '') === String(row.participant || 'Participant ***')
                && current.classList.contains('is-winning') === !!row.winning;
        });
    }

    function validNumber(value) {
        return value !== null && value !== '' && isFinite(Number(value)) && Number(value) >= 0;
    }

    function validStatusCore(data) {
        return !!data
            && ['live', 'awaiting', 'cancelled', 'complete'].indexOf(data.status) !== -1
            && typeof data.revision === 'string'
            && data.revision !== ''
            && validNumber(data.eligible_entries)
            && validNumber(data.total_allocation)
            && validNumber(data.percent_filled);
    }

    function validLivePool(pool) {
        if (!pool || pool.mode !== 'live' || !validNumber(pool.total_entries) || !Array.isArray(pool.entries)) return false;
        var total = Number(pool.total_entries);
        var rendered = pool.entries.length;
        return rendered <= total && (total === 0 ? rendered === 0 : rendered > 0);
    }

    function addCacheBuster(url) {
        return url + (url.indexOf('?') === -1 ? '?' : '&') + '_rlse=' + Date.now();
    }

    function fetchFreshLivePool(productId, config) {
        if (!config.livePoolBase) return Promise.reject(new Error('Live pool unavailable'));
        var url = addCacheBuster(config.livePoolBase + productId + '?page=1&per_page=60');
        return window.fetch(url, {
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            if (!response.ok) throw new Error('Live pool unavailable');
            return response.json();
        }).then(function (pool) {
            if (!validLivePool(pool)) throw new Error('Incomplete live pool');
            return pool;
        });
    }

    function reconcileLiveStatus(data, lastKnownLiveCount, productId, config) {
        if (!validStatusCore(data)) return Promise.reject(new Error('Incomplete status'));
        if (data.status !== 'live') return Promise.resolve(data);

        var statusCount = Number(data.eligible_entries);
        var embeddedPoolValid = data.live_pool_available === true
            && validLivePool(data.live_pool)
            && Number(data.live_pool.total_entries) === statusCount;
        var unexpectedZero = lastKnownLiveCount > 0 && statusCount === 0;
        if (embeddedPoolValid && !unexpectedZero) return Promise.resolve(data);

        return fetchFreshLivePool(productId, config).then(function (pool) {
            var freshCount = Number(pool.total_entries);
            var reconciled = {};
            Object.keys(data).forEach(function (key) { reconciled[key] = data[key]; });
            reconciled.eligible_entries = freshCount;
            reconciled.live_pool_available = true;
            reconciled.live_pool = pool;
            reconciled.percent_filled = Number(data.total_allocation) > 0
                ? Math.min(100, Math.round((freshCount / Number(data.total_allocation)) * 100))
                : 0;
            reconciled.revision = String(data.revision) + '-verified-' + freshCount + '-' + (pool.entries || []).map(function (row) {
                return normalizeEntry(row.entry);
            }).join('.');
            return reconciled;
        });
    }

    function renderChamberSamples(root, data) {
        var entries = data.status === 'live' ? [] : (data.entry_numbers || []).slice(0, 6);
        all(root, '[data-bind="chamber-sample"]').forEach(function (element, index) {
            var number = Number(entries[index] || 0);
            element.textContent = number > 0 ? entryLabel(number) : '';
            element.classList.toggle('rlse-neutral', number < 1);
        });
    }

    function renderEvents(root, events) {
        var log = one(root, '[data-bind="event-log"]');
        if (!log) return;
        log.textContent = '';
        if (!events || !events.length) {
            var empty = document.createElement('li');
            empty.className = 'rlse-empty-row';
            empty.textContent = 'No timestamped events are available yet.';
            log.appendChild(empty);
            return;
        }
        events.forEach(function (event) {
            var item = document.createElement('li');
            var time = document.createElement('time');
            var label = document.createElement('span');
            time.textContent = event.time;
            label.textContent = event.label;
            item.appendChild(time);
            item.appendChild(label);
            log.appendChild(item);
        });
    }

    function renderEarlyClosure(root, closure) {
        var notice = one(root, '[data-bind="early-closure-notice"]');
        if (!notice) return;
        var note = closure && closure.closed_early ? String(closure.note || '').trim() : '';
        var mode = closure && closure.mode === 'cancel_refund' ? 'cancel_refund' : 'selection';
        var refundStatus = closure && closure.refund_status === 'complete' ? 'complete' : 'processing';
        var title = mode === 'cancel_refund' ? 'Raffle Cancelled Early' : 'Raffle Closed Early';
        var pageStatus = root.getAttribute('data-status') || '';
        var statusCopy = mode === 'cancel_refund'
            ? (refundStatus === 'complete'
                ? 'Eligible paid entry value has been credited back in Raffle Points where applicable. No winner will be selected.'
                : 'Raffle Points refunds for eligible paid entries are being processed. No winner will be selected.')
            : (pageStatus === 'complete'
                ? 'Entries were locked at early closure and this raffle proceeded to Selection. No Raffle Points refund was issued.'
                : 'Entries are locked and this raffle will proceed to Selection. No Raffle Points refund was issued.');
        notice.hidden = !note;
        setText(root, '[data-bind="early-closure-title"]', title);
        setText(root, '[data-bind="early-closure-copy"]', note);
        setText(root, '[data-bind="early-closure-refund"]', statusCopy);
    }

    function renderLive(root, data) {
        var poolMatches = entryPoolMatches(root, data);
        root.setAttribute('data-revision', data.revision);
        root.setAttribute('data-status', data.status);
        var chip = one(root, '[data-bind="status-label"]');
        if (chip) {
            chip.className = 'rlse-status-chip is-' + data.status;
            chip.innerHTML = '<i></i>' + data.status_label;
        }
        setText(root, '[data-bind="status-heading"]', data.status_label);
        setText(root, '[data-bind="fact-status"]', data.status_label);
        setText(root, '[data-bind="fact-closed"]', data.closed_display || (data.status === 'live' ? 'Raffle remains open' : 'Closure time unavailable'));
        setText(root, '[data-bind="fact-selected"]', data.selected_display || 'No result recorded');
        setText(root, '[data-bind="eligible-entries"]', data.eligible_entries);
        setText(root, '[data-bind="total-allocation"]', data.total_allocation);
        setText(root, '[data-bind="percent-filled"]', data.percent_filled + '%');
        setText(root, '[data-bind="pool-count"]', data.eligible_entries);
        setText(root, '[data-bind="pool-title"]', 'Eligible Entries');
        var poolChip = one(root, '[data-bind="pool-lock-chip"]');
        if (poolChip) {
            poolChip.hidden = false;
            poolChip.classList.toggle('is-live', data.status === 'live');
            poolChip.classList.toggle('is-locked', data.status !== 'live');
            poolChip.textContent = data.status === 'live' ? 'LIVE' : data.eligible_entries + ' LOCKED';
        }
        setText(root, '[data-bind="lock-title"]', data.status === 'live' ? 'Live Entry Pool' : 'Locked Entry Pool');
        setText(root, '[data-bind="lock-copy"]', data.status === 'live' ? 'Current active entries are shown and may change while the raffle remains open.' : 'No new entries can be added.');
        setText(root, '[data-bind="engine-message"]', data.status === 'complete' ? 'Selection complete. The official result is recorded.' : (data.status === 'awaiting' ? 'Entries are locked. Awaiting official selection.' : (data.status === 'cancelled' ? 'This raffle was cancelled early. The locked entry pool remains available as a public record.' : 'The raffle is open. The entry pool is not locked.')));
        setText(root, '[data-bind="progress-copy"]', data.status === 'complete' ? 'Recorded result' : (data.status === 'awaiting' ? 'Eligible entries locked' : (data.status === 'cancelled' ? 'Cancelled — entries locked' : 'Allocation progress')));
        setText(root, '[data-bind="progress-percent"]', data.percent_filled + '%');
        var bar = one(root, '[data-bind="progress-bar"]');
        if (bar) bar.style.width = data.percent_filled + '%';
        var chamberNumber = one(root, '[data-bind="chamber-number"]');
        if (chamberNumber) chamberNumber.textContent = data.result ? data.result.entry : '—';
        var chamber = one(root, '[data-bind="chamber"]');
        if (chamber) {
            chamber.classList.toggle('is-locked', data.status !== 'live');
            chamber.classList.toggle('is-complete', !!data.result);
        }
        setText(root, '[data-bind="chamber-mode"]', data.status === 'complete' ? 'RECORDED' : (data.status === 'awaiting' ? 'LOCKED' : (data.status === 'cancelled' ? 'CANCELLED' : 'IDLE')));
        setText(root, '[data-bind="winner-panel-title"]', data.status === 'cancelled' ? 'Selection Result' : 'Winning Entry');
        setText(root, '[data-bind="winner-kicker"]', data.result ? 'WINNING ENTRY' : (data.status === 'cancelled' ? 'NO SELECTION' : 'AWAITING SELECTION'));
        setText(root, '[data-bind="winner-entry"]', data.result ? data.result.entry : '—');
        setText(root, '[data-bind="result-title"]', data.result ? 'Recorded Result' : (data.status === 'cancelled' ? 'Raffle cancelled' : 'No result recorded yet'));
        setText(root, '[data-bind="result-copy"]', data.result ? (data.result.winner + ' · ' + data.result.selected_display) : (data.status === 'live' ? 'The raffle is still open.' : (data.status === 'cancelled' ? 'This raffle was cancelled early. No winner will be selected.' : 'Entries are locked and awaiting the official result.')));
        setText(root, '[data-bind="result-icon"]', data.result ? '✓' : '○');
        setText(root, '[data-bind="winner-pool-state"]', data.status === 'live' ? 'LIVE' : 'LOCKED');
        setText(root, '[data-bind="winner-selection-state"]', data.result ? 'COMPLETE' : (data.status === 'awaiting' ? 'AWAITING SELECTION' : (data.status === 'cancelled' ? 'NOT PROCEEDING' : 'WAITING FOR CLOSE')));
        setText(root, '[data-bind="winner-record-state"]', data.result ? (data.result.selected_display || 'RECORDED') : (data.status === 'cancelled' ? 'NO RESULT' : 'PENDING'));
        var winnerPanel = one(root, '[data-bind="winner-panel"]');
        if (winnerPanel) winnerPanel.classList.toggle('has-result', !!data.result);
        var entryPanel = one(root, '[data-rlse-entry-panel]');
        if (entryPanel && !data.accepting_entries) entryPanel.remove();
        renderEarlyClosure(root, data.early_closure);
        renderTimeline(root, data.status);
        if (!poolMatches) {
            renderEntryPool(root, data);
            bindEntryPool(root, Number(root.getAttribute('data-product-id')), window.RaffleLBSelectionEngine || {});
        }
        renderChamberSamples(root, data);
        renderEvents(root, data.events);
    }

    function initLive(root) {
        var productId = Number(root.getAttribute('data-product-id'));
        var config = window.RaffleLBSelectionEngine || {};
        if (!productId || !config.restBase) return;
        var polling = false;
        var initialCount = one(root, '[data-bind="eligible-entries"]');
        var lastKnownLiveCount = Math.max(0, Number(initialCount ? initialCount.textContent : 0) || 0);
        bindEntryPool(root, productId, config);

        function refresh() {
            if (polling || document.hidden) return;
            polling = true;
            window.fetch(addCacheBuster(config.restBase + productId), {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Status unavailable');
                    return response.json();
                })
                .then(function (data) {
                    return reconcileLiveStatus(data, lastKnownLiveCount, productId, config);
                })
                .then(function (data) {
                    var displayedCount = one(root, '[data-bind="eligible-entries"]');
                    var countChanged = Number(displayedCount ? displayedCount.textContent : 0) !== Number(data.eligible_entries);
                    var statusChanged = String(root.getAttribute('data-status') || '') !== data.status;
                    var needsRender = data.revision !== root.getAttribute('data-revision')
                        || statusChanged
                        || countChanged
                        || !entryPoolMatches(root, data);
                    if (needsRender) renderLive(root, data);
                    if (data.status === 'live') lastKnownLiveCount = Number(data.eligible_entries);
                })
                .catch(function () {})
                .then(function () { polling = false; });
        }

        window.setInterval(refresh, Number(config.pollMs) || 25000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refresh();
        });
    }

    function initResultsDirectory(root) {
        var grid = one(root, '[data-rlse-results-grid]');
        if (!grid) return;
        var cards = all(root, '.rlse-winners-card');
        var filters = all(root, '[data-rlse-results-filter]');
        var search = one(root, '[data-rlse-results-search]');
        var sort = one(root, '[data-rlse-results-sort]');
        var empty = one(root, '[data-rlse-results-empty]');
        var active = 'all';

        function render() {
            var query = search ? search.value.trim().toLowerCase() : '';
            var shown = 0;
            cards.forEach(function (card) {
                var categoryMatches = active === 'all' || card.getAttribute('data-category') === active;
                var searchMatches = !query || (card.getAttribute('data-search') || '').indexOf(query) !== -1;
                card.hidden = !(categoryMatches && searchMatches);
                if (!card.hidden) shown += 1;
            });
            if (empty) empty.hidden = shown !== 0;
        }

        filters.forEach(function (button) {
            button.addEventListener('click', function () {
                active = button.getAttribute('data-rlse-results-filter') || 'all';
                filters.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                render();
            });
        });
        if (search) search.addEventListener('input', render);
        if (sort) {
            sort.addEventListener('change', function () {
                cards.sort(function (a, b) {
                    if (sort.value === 'oldest') return Number(a.dataset.date) - Number(b.dataset.date);
                    if (sort.value === 'title') return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                    return Number(b.dataset.date) - Number(a.dataset.date);
                });
                cards.forEach(function (card) { grid.appendChild(card); });
                render();
            });
        }
        render();
    }

    function boot() {
        all(document, '[data-rlse-demo]').forEach(initDemo);
        all(document, '[data-rlse-live]').forEach(initLive);
        all(document, '[data-rlse-winners]').forEach(initResultsDirectory);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
