<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RaffleLB_Selection_Renderer {
    /**
     * Shared recorded-result treatment used by Homepage and Winners.
     * The caller supplies only the adapter's public result fields.
     */
    public static function recorded_result_visual($result, $variant = 'compact', $show_status = true) {
        if (!is_array($result) || empty($result['entry']) || empty($result['selection_url'])) {
            return '';
        }

        $variant = $variant === 'strong' ? 'strong' : 'compact';
        $entry = sanitize_text_field((string) $result['entry']);
        $url = esc_url((string) $result['selection_url']);

        ob_start(); ?>
        <div class="rlse-record rlse-record--<?php echo esc_attr($variant); ?>">
            <div class="rlse-record-head">
                <?php if ($show_status): ?><span class="rlse-record-state"><i aria-hidden="true">✓</i> <?php echo esc_html__('SELECTION RECORDED', 'rafflelb-selection-engine'); ?></span><?php endif; ?>
                <span class="rlse-record-entry"><?php echo esc_html__('WINNING ENTRY', 'rafflelb-selection-engine'); ?> <strong><?php echo esc_html($entry); ?></strong></span>
            </div>
            <div class="rlse-record-machine" aria-hidden="true">
                <span class="rlse-record-grid"></span>
                <span class="rlse-record-ring rlse-record-ring--outer"></span>
                <span class="rlse-record-ring rlse-record-ring--inner"></span>
                <span class="rlse-record-core"><small>RECORDED</small><strong><?php echo esc_html($entry); ?></strong></span>
                <i class="rlse-record-node rlse-record-node--one"></i>
                <i class="rlse-record-node rlse-record-node--two"></i>
                <i class="rlse-record-node rlse-record-node--three"></i>
            </div>
            <a class="rlse-record-cta" href="<?php echo $url; ?>"><?php echo esc_html__('VIEW SELECTION RESULT', 'rafflelb-selection-engine'); ?> <span aria-hidden="true">→</span></a>
        </div>
        <?php return ob_get_clean();
    }

    public static function recorded_status_row() {
        return '<div class="rlse-record-status"><i aria-hidden="true">✓</i> ' . esc_html__('SELECTION RECORDED', 'rafflelb-selection-engine') . '</div>';
    }

    public static function winners() {
        if (!RaffleLB_Selection_Adapter::available()) {
            return self::unavailable();
        }

        $results = RaffleLB_Selection_Adapter::public_results(0);
        $count = count($results);
        $categories = [];
        foreach ($results as $result) {
            $categories[$result['category_slug']] = $result['category_name'];
        }
        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        ob_start(); ?>
        <main class="rlse-winners has-<?php echo esc_attr($count === 1 ? 'one' : ($count === 2 ? 'two' : 'many')); ?>-result<?php echo $count === 1 ? '' : 's'; ?>" id="rafflelb-winners-directory" data-rlse-winners>
            <section class="rlse-winners-hero">
                <div class="rlse-winners-shell rlse-winners-hero-grid">
                    <div class="rlse-winners-intro">
                        <span class="rlse-winners-eyebrow">RECORDED RESULTS</span>
                        <h1>Every winner.<br><em>One permanent record.</em></h1>
                        <p>Review completed RaffleLB selections and open the permanent Selection Engine record for every result.</p>
                        <?php if ($count): ?><div class="rlse-winners-count"><strong><?php echo esc_html($count); ?></strong><span><?php echo esc_html(_n('COMPLETED SELECTION', 'COMPLETED SELECTIONS', $count, 'rafflelb-selection-engine')); ?></span></div><?php endif; ?>
                    </div>
                    <?php if ($count): $latest = $results[0]; ?>
                        <article class="rlse-winners-latest">
                            <a class="rlse-winners-latest-media" href="<?php echo esc_url($latest['product_url']); ?>" aria-label="View <?php echo esc_attr($latest['name']); ?>">
                                <?php if ($latest['image']): ?><img src="<?php echo esc_url($latest['image']); ?>" alt="<?php echo esc_attr($latest['name']); ?>" loading="eager"><?php else: ?><span class="rlse-winners-placeholder" aria-hidden="true">R<em>LB</em></span><?php endif; ?>
                            </a>
                            <div class="rlse-winners-latest-body">
                                <span class="rlse-winners-latest-label">LATEST RECORDED RESULT</span>
                                <div class="rlse-winners-latest-copy">
                                    <span><?php echo esc_html($latest['selected_display']); ?></span>
                                    <h2><a href="<?php echo esc_url($latest['product_url']); ?>"><?php echo esc_html($latest['name']); ?></a></h2>
                                    <p><?php echo esc_html($latest['winner']); ?></p>
                                </div>
                                <?php echo self::recorded_result_visual($latest, 'strong'); ?>
                            </div>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rlse-winners-trust" aria-labelledby="rlse-winners-trust-title">
                <div class="rlse-winners-shell">
                    <div class="rlse-winners-trust-panel">
                        <div class="rlse-winners-trust-head">
                            <div><span>PUBLIC RESULT RECORDS</span><h2 id="rlse-winners-trust-title">EVERY RESULT HAS A RECORD</h2></div>
                            <a href="<?php echo esc_url(home_url('/selection-engine/')); ?>">EXPLORE THE SELECTION ENGINE <span aria-hidden="true">→</span></a>
                        </div>
                        <ol class="rlse-winners-flow">
                            <li><span>1</span><strong>Eligible Entries Locked</strong></li>
                            <li><span>2</span><strong>Selection Completed</strong></li>
                            <li><span>3</span><strong>Winning Entry Recorded</strong></li>
                        </ol>
                    </div>
                </div>
            </section>

            <section class="rlse-winners-directory">
                <div class="rlse-winners-shell">
                    <div class="rlse-winners-directory-head">
                        <div><span>COMPLETED SELECTIONS</span><h2>Recorded results</h2><p>Prize, public winner, winning entry and recorded time — with direct access to the permanent result page.</p></div>
                        <?php if ($count): ?>
                            <div class="rlse-winners-tools">
                                <label><span class="screen-reader-text">Search results</span><input type="search" data-rlse-results-search placeholder="Search winners or prizes…" aria-label="Search winners or prizes"></label>
                                <label><span class="screen-reader-text">Sort results</span><select data-rlse-results-sort aria-label="Sort results"><option value="newest">Newest First</option><option value="oldest">Oldest First</option><option value="title">Prize A–Z</option></select></label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($count): ?>
                        <nav class="rlse-winners-filters" aria-label="Result categories">
                            <button class="is-active" type="button" data-rlse-results-filter="all">All Results <span><?php echo esc_html($count); ?></span></button>
                            <?php foreach ($categories as $slug => $name): ?><button type="button" data-rlse-results-filter="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></button><?php endforeach; ?>
                        </nav>
                        <div class="rlse-winners-grid is-<?php echo esc_attr($count === 1 ? 'single' : ($count === 2 ? 'pair' : ($count === 3 ? 'trio' : 'many'))); ?>" data-rlse-results-grid>
                            <?php foreach ($results as $result):
                                $search = strtolower(wp_strip_all_tags($result['name'] . ' ' . $result['winner']));
                                $parts = preg_split('/\s+/', trim($result['winner']));
                                $initials = '';
                                foreach (array_slice((array) $parts, 0, 2) as $part) {
                                    $clean = preg_replace('/[^\p{L}\p{N}]/u', '', (string) $part);
                                    if ($clean !== '') $initials .= function_exists('mb_substr') ? mb_substr($clean, 0, 1) : substr($clean, 0, 1);
                                }
                                $initials = strtoupper($initials ?: 'W');
                            ?>
                                <article class="rlse-winners-card" data-category="<?php echo esc_attr($result['category_slug']); ?>" data-search="<?php echo esc_attr($search); ?>" data-date="<?php echo esc_attr($result['timestamp']); ?>" data-title="<?php echo esc_attr(strtolower($result['name'])); ?>">
                                    <a class="rlse-winners-media" href="<?php echo esc_url($result['product_url']); ?>" aria-label="View <?php echo esc_attr($result['name']); ?>">
                                        <?php if ($result['image']): ?><img src="<?php echo esc_url($result['image']); ?>" alt="<?php echo esc_attr($result['name']); ?>" loading="lazy"><?php else: ?><span class="rlse-winners-placeholder" aria-hidden="true">R<em>LB</em></span><?php endif; ?>
                                    </a>
                                    <div class="rlse-winners-card-body">
                                        <?php echo self::recorded_status_row(); ?>
                                        <span class="rlse-winners-date"><?php echo esc_html($result['selected_display']); ?></span>
                                        <h3><a href="<?php echo esc_url($result['product_url']); ?>"><?php echo esc_html($result['name']); ?></a></h3>
                                        <div class="rlse-winners-person"><span aria-hidden="true"><?php echo esc_html($initials); ?></span><div><small>PUBLIC WINNER</small><strong><?php echo esc_html($result['winner']); ?></strong></div></div>
                                        <?php echo self::recorded_result_visual($result, 'strong', false); ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="rlse-winners-empty-filter" data-rlse-results-empty hidden><strong>No matching recorded results</strong><span>Try another search or category.</span></div>
                    <?php else: ?>
                        <div class="rlse-winners-empty"><span aria-hidden="true">✓</span><div><strong>No recorded results yet</strong><p>Completed selections will appear here automatically with their permanent public result record.</p></div></div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
        <?php return ob_get_clean();
    }

    public static function hub() {
        if (!RaffleLB_Selection_Adapter::available()) {
            return self::unavailable();
        }
        $results = RaffleLB_Selection_Adapter::previous_results(3);
        $raffles = RaffleLB_Selection_Adapter::raffle_cards(6);
        ob_start();
        ?>
        <main class="rlse" id="rafflelb-selection-engine">
            <?php echo self::hero(); ?>

            <section class="rlse-shell rlse-demo" data-rlse-demo aria-labelledby="rlse-demo-title">
                <div class="rlse-demo-head">
                    <div>
                        <span class="rlse-eyebrow">INTERACTIVE DEMONSTRATION</span>
                        <h2 id="rlse-demo-title">Try the Selection Engine</h2>
                        <p>Watch a simulated selection unfold in real time.</p>
                    </div>
                    <div class="rlse-demo-controls">
                        <button class="rlse-button rlse-button-primary" type="button" data-demo-start><span aria-hidden="true">▶</span> START DEMO SELECTION</button>
                        <button class="rlse-button rlse-button-secondary" type="button" data-demo-reset><span aria-hidden="true">↻</span> RESET</button>
                    </div>
                </div>
                <p class="rlse-demo-notice"><strong>Demonstration only</strong> — this simulation does not affect live raffles or official results.</p>
                <?php echo self::demo_timeline(); ?>

                <div class="rlse-engine-grid">
                    <article class="rlse-panel rlse-pool-panel">
                        <div class="rlse-panel-title"><span>1.</span><h3>Eligible Entries</h3></div>
                        <div class="rlse-count"><strong data-demo-total>100</strong><span>Total entries</span></div>
                        <div class="rlse-entry-grid" data-demo-pool aria-label="Demo entry pool">
                            <?php for ($i = 1; $i <= 30; $i++): ?>
                                <span>#<?php echo esc_html(str_pad((string) $i, 3, '0', STR_PAD_LEFT)); ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="rlse-lock-note" data-demo-lock><i aria-hidden="true">⌁</i><span><strong>Entry pool prepared</strong><small>Start the demonstration to lock the simulated pool.</small></span></div>
                    </article>

                    <article class="rlse-panel rlse-chamber-panel">
                        <div class="rlse-panel-title rlse-panel-title-center"><span>2.</span><h3>Selection Engine</h3></div>
                        <p class="rlse-panel-subtitle" data-demo-engine-label>Ready for a secure browser-only simulation.</p>
                        <?php echo self::chamber('—', true); ?>
                        <div class="rlse-progress" aria-hidden="true"><span data-demo-progress></span></div>
                        <div class="rlse-progress-label"><span data-demo-progress-label>Ready</span><strong data-demo-percent>0%</strong></div>
                    </article>

                    <article class="rlse-panel rlse-winner-panel" aria-live="polite">
                        <div class="rlse-panel-title"><span>3.</span><h3>Winning Entry</h3></div>
                        <div class="rlse-winner-burst">
                            <span>DEMO WINNING ENTRY</span>
                            <strong data-demo-winner>—</strong>
                        </div>
                        <div class="rlse-result-note" data-demo-result-note><i aria-hidden="true">✓</i><span><strong>Awaiting simulation</strong><small>No official result is affected.</small></span></div>
                    </article>
                </div>

                <div class="rlse-lower-grid">
                    <?php echo self::demo_log(); ?>
                    <?php echo self::results_panel($results, __('Previous Results', 'rafflelb-selection-engine')); ?>
                    <?php echo self::trust_panel(); ?>
                </div>
            </section>

            <section class="rlse-shell rlse-live-section" aria-labelledby="rlse-live-title">
                <div class="rlse-section-heading">
                    <div><span class="rlse-eyebrow">CURRENT RAFFLES</span><h2 id="rlse-live-title">Live status, clearly shown</h2></div>
                    <p>Open a raffle’s public status page to see its recorded progress and result.</p>
                </div>
                <?php echo self::raffle_cards($raffles); ?>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }

    public static function raffle($product) {
        if (!RaffleLB_Selection_Adapter::available()) {
            return self::unavailable();
        }
        if (!$product instanceof WC_Product) {
            status_header(404);
            return self::not_found();
        }
        $data = RaffleLB_Selection_Adapter::snapshot($product->get_id());
        $related = RaffleLB_Selection_Adapter::previous_results(3, $product->get_id());
        $entry_panel = '';
        if (!empty($data['accepting_entries'])
            && class_exists('RaffleLB_Shop')
            && method_exists('RaffleLB_Shop', 'selection_entry_form')
            && RaffleLB_Shop::ready()) {
            $entry_panel = RaffleLB_Shop::selection_entry_form($product, $data['selection_url']);
        }
        ob_start();
        ?>
        <main class="rlse rlse-raffle" data-rlse-live data-product-id="<?php echo esc_attr($data['product_id']); ?>" data-revision="<?php echo esc_attr($data['revision']); ?>" data-status="<?php echo esc_attr($data['status']); ?>">
            <section class="rlse-raffle-hero">
                <div class="rlse-raffle-hero-gridlines" aria-hidden="true"></div>
                <div class="rlse-shell rlse-raffle-hero-grid">
                    <div class="rlse-raffle-copy">
                        <a class="rlse-back" href="<?php echo esc_url(home_url('/selection-engine/')); ?>">← SELECTION ENGINE</a>
                        <span class="rlse-raffle-kicker">RAFFLELB PUBLIC RAFFLE</span>
                        <span class="rlse-status-chip is-<?php echo esc_attr($data['status']); ?>" data-bind="status-label"><i></i><?php echo esc_html($data['status_label']); ?></span>
                        <h1><?php echo esc_html($data['name']); ?></h1>
                        <p>Public raffle status and recorded result information, presented directly from RaffleLB’s live system.</p>
                        <div class="rlse-hero-metrics">
                            <div><strong data-bind="eligible-entries"><?php echo esc_html($data['eligible_entries']); ?></strong><span>Eligible Entries</span></div>
                            <div><strong data-bind="total-allocation"><?php echo esc_html($data['total_allocation']); ?></strong><span>Total Allocation</span></div>
                            <div><strong data-bind="percent-filled"><?php echo esc_html($data['percent_filled']); ?>%</strong><span>Filled</span></div>
                        </div>
                    </div>
                    <div class="rlse-prize-visual">
                        <div class="rlse-prize-frame">
                            <?php if ($data['image']): ?>
                                <img src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['name']); ?>">
                            <?php else: ?>
                                <span class="rlse-brand-placeholder rlse-brand-placeholder-hero" aria-hidden="true"><span class="rlse-brand-emblem"><b>RAFFLE</b><em>LB</em></span><small>OFFICIAL PRIZE VISUAL</small></span>
                            <?php endif; ?>
                        </div>
                        <span class="rlse-prize-label" aria-hidden="true"><i></i> OFFICIAL PRIZE</span>
                        <div class="rlse-orbit" aria-hidden="true"></div>
                    </div>
                </div>
            </section>

            <?php echo $entry_panel; ?>

            <section class="rlse-shell rlse-status-board" aria-label="Raffle selection status">
                <div class="rlse-status-board-head">
                    <div><span class="rlse-eyebrow">PUBLIC STATUS</span><h2 data-bind="status-heading"><?php echo esc_html($data['status_label']); ?></h2></div>
                    <div class="rlse-refresh"><span aria-hidden="true">↻</span> Status refreshes automatically</div>
                </div>
                <?php echo self::real_timeline($data['status']); ?>
                <div class="rlse-fact-strip">
                    <span><small>Current status</small><strong data-bind="fact-status"><?php echo esc_html($data['status_label']); ?></strong></span>
                    <span><small>Closing record</small><strong data-bind="fact-closed"><?php echo esc_html($data['closed_display'] ?: ($data['status'] === 'live' ? __('Raffle remains open', 'rafflelb-selection-engine') : __('Closure time unavailable', 'rafflelb-selection-engine'))); ?></strong></span>
                    <span><small>Selection record</small><strong data-bind="fact-selected"><?php echo esc_html($data['selected_display'] ?: __('No result recorded', 'rafflelb-selection-engine')); ?></strong></span>
                </div>

                <?php echo self::early_closure_notice($data); ?>

                <div class="rlse-engine-grid rlse-real-grid">
                    <?php echo self::real_pool($data); ?>
                    <article class="rlse-panel rlse-chamber-panel">
                        <div class="rlse-panel-title rlse-panel-title-center"><span>2.</span><h3>Selection Engine</h3></div>
                        <p class="rlse-panel-subtitle" data-bind="engine-message"><?php echo esc_html(self::engine_message($data['status'])); ?></p>
                        <?php echo self::chamber($data['result'] ? $data['result']['entry'] : '—', false, $data['entry_numbers'], $data['status']); ?>
                        <div class="rlse-progress"><span data-bind="progress-bar" style="width:<?php echo esc_attr($data['percent_filled']); ?>%"></span></div>
                        <div class="rlse-progress-label"><span data-bind="progress-copy"><?php echo esc_html(self::progress_copy($data['status'])); ?></span><strong data-bind="progress-percent"><?php echo esc_html($data['percent_filled']); ?>%</strong></div>
                    </article>
                    <?php echo self::real_winner($data); ?>
                </div>

                <div class="rlse-lower-grid">
                    <?php echo self::real_log($data['events']); ?>
                    <?php echo self::results_panel($related, __('Related Previous Results', 'rafflelb-selection-engine')); ?>
                    <?php echo self::trust_panel(); ?>
                </div>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }

    private static function hero() {
        ob_start(); ?>
        <section class="rlse-hero">
            <div class="rlse-shell rlse-hero-grid">
                <div class="rlse-hero-copy">
                    <span class="rlse-eyebrow">TRANSPARENT. SECURE. RECORDED.</span>
                    <h1>RaffleLB<br><em>Selection Engine</em></h1>
                    <p>See how a winner is selected, step by step. Real technology. Clear public status.</p>
                    <div class="rlse-trust-row">
                        <span><i aria-hidden="true">▣</i>Locked Eligible Entries</span>
                        <span><i aria-hidden="true">↝</i>Secure Selection Process</span>
                        <span><i aria-hidden="true">▤</i>Recorded Results</span>
                        <span><i aria-hidden="true">◈</i>Transparent Result Record</span>
                    </div>
                </div>
                <div class="rlse-hero-machine" aria-hidden="true">
                    <div class="rlse-cube"><span>RAFFLE<em>LB</em></span></div>
                    <div class="rlse-machine-grid"></div>
                    <span class="rlse-machine-note">SAME OPPORTUNITIES.<br>REAL PEOPLE.</span>
                </div>
            </div>
        </section>
        <?php return ob_get_clean();
    }

    private static function demo_timeline() {
        $steps = [
            ['Raffle Open', 'Entries are collected'],
            ['Raffle Closes', 'No more entries'],
            ['Entries Lock', 'Final pool is secured'],
            ['Selection in Progress', 'Selecting a winner'],
            ['Winner Selected', 'Result is revealed'],
        ];
        ob_start(); ?>
        <ol class="rlse-timeline" data-demo-timeline>
            <?php foreach ($steps as $index => $step): ?>
                <li class="<?php echo $index === 0 ? 'is-current' : ''; ?>" data-demo-step="<?php echo esc_attr($index + 1); ?>"><span><?php echo esc_html($index + 1); ?></span><strong><?php echo esc_html($step[0]); ?></strong><small><?php echo esc_html($step[1]); ?></small></li>
            <?php endforeach; ?>
        </ol>
        <?php return ob_get_clean();
    }

    private static function real_timeline($status) {
        $steps = [
            ['Raffle Open', 'Eligible entries collected'],
            ['Raffle Closes', $status === 'live' ? 'Pending' : 'Closed'],
            ['Entries Lock', $status === 'live' ? 'Not locked' : 'Locked Entry Pool'],
            ['Selection', $status === 'complete' ? 'Recorded process complete' : ($status === 'cancelled' ? 'Not proceeding' : 'Not started')],
            ['Winner Selected', $status === 'complete' ? 'Recorded Result' : ($status === 'cancelled' ? 'Not applicable' : 'Pending')],
        ];
        $current = $status === 'live' ? 1 : (($status === 'awaiting' || $status === 'cancelled') ? 3 : 5);
        ob_start(); ?>
        <ol class="rlse-timeline" data-bind="timeline" data-status="<?php echo esc_attr($status); ?>">
            <?php foreach ($steps as $index => $step): $number = $index + 1; ?>
                <li class="<?php echo $number < $current ? 'is-done' : ($number === $current ? 'is-current' : ''); ?>" data-step="<?php echo esc_attr($number); ?>"><span><?php echo esc_html($number); ?></span><strong><?php echo esc_html($step[0]); ?></strong><small><?php echo esc_html($step[1]); ?></small></li>
            <?php endforeach; ?>
        </ol>
        <?php return ob_get_clean();
    }

    private static function early_closure_notice($data) {
        $closure = !empty($data['early_closure']) && is_array($data['early_closure']) ? $data['early_closure'] : null;
        $note = $closure ? sanitize_textarea_field((string) ($closure['note'] ?? '')) : '';
        $mode = $closure && ($closure['mode'] ?? '') === 'cancel_refund' ? 'cancel_refund' : 'selection';
        $refund_status = $closure && ($closure['refund_status'] ?? '') === 'complete' ? 'complete' : 'processing';
        $title = $mode === 'cancel_refund' ? __('Raffle Cancelled Early', 'rafflelb-selection-engine') : __('Raffle Closed Early', 'rafflelb-selection-engine');
        if ($mode === 'cancel_refund') {
            $status_copy = $refund_status === 'complete'
                ? __('Eligible paid entry value has been credited back in Raffle Points where applicable. No winner will be selected.', 'rafflelb-selection-engine')
                : __('Raffle Points refunds for eligible paid entries are being processed. No winner will be selected.', 'rafflelb-selection-engine');
        } else {
            $status_copy = $data['status'] === 'complete'
                ? __('Entries were locked at early closure and this raffle proceeded to Selection. No Raffle Points refund was issued.', 'rafflelb-selection-engine')
                : __('Entries are locked and this raffle will proceed to Selection. No Raffle Points refund was issued.', 'rafflelb-selection-engine');
        }
        ob_start(); ?>
        <aside class="rlse-early-closure" data-bind="early-closure-notice"<?php echo $note === '' ? ' hidden' : ''; ?> role="note" aria-live="polite">
            <span class="rlse-early-closure-icon" aria-hidden="true">i</span>
            <div><h3 data-bind="early-closure-title"><?php echo esc_html($title); ?></h3><p data-bind="early-closure-copy"><?php echo esc_html($note); ?></p><p data-bind="early-closure-refund"><?php echo esc_html($status_copy); ?></p></div>
        </aside>
        <?php return ob_get_clean();
    }

    private static function chamber($entry, $demo, $entry_numbers = [], $status = '') {
        $demo_labels = ['#041', '#058', '#073', '#083', '#033', '#094'];
        $real_labels = [];
        if (!$demo) {
            foreach (array_slice((array) $entry_numbers, 0, 6) as $number) {
                $number = absint($number);
                if ($number > 0) {
                    $real_labels[] = '#' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                }
            }
        }
        $state_class = $demo ? ' is-demo' : ' is-real';
        if (!$demo && $status !== 'live') {
            $state_class .= ' is-locked';
        }
        if (!$demo && $status === 'complete') {
            $state_class .= ' is-complete';
        }
        ob_start(); ?>
        <div class="rlse-chamber<?php echo esc_attr($state_class); ?>" <?php echo $demo ? 'data-demo-chamber' : 'data-bind="chamber"'; ?>>
            <div class="rlse-chamber-grid" aria-hidden="true"></div>
            <div class="rlse-chamber-rail rlse-rail-top" aria-hidden="true"><i></i><i></i><i></i></div>
            <div class="rlse-cylinder">
                <div class="rlse-cylinder-glass" aria-hidden="true"></div>
                <div class="rlse-ring rlse-ring-one" aria-hidden="true"></div>
                <div class="rlse-ring rlse-ring-two" aria-hidden="true"></div>
                <div class="rlse-ring rlse-ring-three" aria-hidden="true"></div>
                <div class="rlse-core-lines" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            </div>
            <div class="rlse-chamber-overlay">
                <div class="rlse-chamber-number"><small><?php echo $demo ? 'DEMO ENTRY' : 'ENTRY'; ?></small><strong <?php echo $demo ? 'data-demo-chamber-number' : 'data-bind="chamber-number"'; ?>><?php echo esc_html($entry); ?></strong></div>
                <?php for ($index = 0; $index < 6; $index++):
                    $label = $demo ? $demo_labels[$index] : (isset($real_labels[$index]) ? $real_labels[$index] : '');
                    $neutral_class = (!$demo && $label === '') ? ' rlse-neutral' : '';
                ?>
                    <span class="rlse-float r<?php echo esc_attr($index + 1); ?><?php echo esc_attr($neutral_class); ?>"<?php echo $demo ? '' : ' data-bind="chamber-sample"'; ?>><?php echo esc_html($label); ?></span>
                <?php endfor; ?>
                <span class="rlse-tech-label rlse-tech-left" aria-hidden="true">POOL</span>
                <span class="rlse-tech-label rlse-tech-right" aria-hidden="true"<?php echo $demo ? '' : ' data-bind="chamber-mode"'; ?>><?php echo (!$demo && $status === 'complete') ? 'RECORDED' : (($demo || $status !== 'live') ? 'LOCKED' : 'IDLE'); ?></span>
            </div>
            <div class="rlse-chamber-rail rlse-rail-bottom" aria-hidden="true"><i></i><i></i><i></i></div>
        </div>
        <?php return ob_get_clean();
    }

    private static function demo_log() {
        ob_start(); ?>
        <article class="rlse-panel rlse-log-panel">
            <div class="rlse-mini-head"><h3>Demo Process Log</h3><span class="rlse-live-dot"><i></i>Live</span></div>
            <ul data-demo-log><li class="rlse-log-ready"><time>—</time><span>Ready to begin demonstration</span></li></ul>
        </article>
        <?php return ob_get_clean();
    }

    private static function real_pool($data) {
        $is_live = $data['status'] === 'live';
        $pool = $is_live
            ? (!empty($data['live_pool']) && is_array($data['live_pool']) ? $data['live_pool'] : null)
            : (!empty($data['locked_pool']) && is_array($data['locked_pool']) ? $data['locked_pool'] : null);
        $pool_rows = $pool ? (array) $pool['entries'] : [];
        $pool_total = $pool ? absint($is_live ? $pool['total_entries'] : $pool['total_locked']) : 0;
        ob_start(); ?>
        <article class="rlse-panel rlse-pool-panel" data-bind="pool-panel">
            <div class="rlse-panel-title rlse-pool-heading"><span>1.</span><h3 data-bind="pool-title"><?php esc_html_e('Eligible Entries', 'rafflelb-selection-engine'); ?></h3><b data-bind="pool-lock-chip" class="<?php echo $is_live ? 'is-live' : 'is-locked'; ?>"><?php echo $is_live ? 'LIVE' : esc_html($data['eligible_entries']) . ' LOCKED'; ?></b></div>
            <div class="rlse-count"><strong data-bind="pool-count"><?php echo esc_html($data['eligible_entries']); ?></strong><span>Eligible Entries</span></div>
            <div data-bind="entry-grid">
                <?php if (!$pool && !$is_live): ?>
                    <div class="rlse-pool-open">This historical raffle has no authoritative locked-pool record. Its entry list is not published.</div>
                <?php elseif (!$pool): ?>
                    <div class="rlse-pool-open">Live entries are temporarily unavailable.</div>
                <?php else: ?>
                    <div class="rlse-pool-verifier" data-entry-pool data-mode="<?php echo $is_live ? 'live' : 'locked'; ?>" data-page="1" data-total="<?php echo esc_attr($pool_total); ?>" data-loaded="<?php echo esc_attr(count($pool_rows)); ?>"<?php echo $is_live ? '' : ' data-revision="' . esc_attr($pool['revision']) . '"'; ?>>
                        <label class="rlse-entry-search"><span>Search entry number</span><input type="search" inputmode="numeric" autocomplete="off" placeholder="#001" data-entry-search aria-label="Search entry number"></label>
                        <div class="rlse-entry-register">
                            <div class="rlse-entry-table-head" aria-hidden="true"><span>TICKET #</span><span>PARTICIPANT</span></div>
                            <div class="rlse-entry-list" data-entry-list role="list" aria-label="<?php echo $is_live ? 'Current active entries' : 'Final eligible entries'; ?>">
                                <?php foreach ($pool_rows as $row): ?>
                                    <div class="rlse-entry-row<?php echo !empty($row['winning']) ? ' is-winning' : ''; ?>" data-entry="<?php echo esc_attr(absint(ltrim((string)$row['entry'], '#'))); ?>" role="listitem">
                                        <strong><?php echo esc_html($row['entry']); ?></strong>
                                        <?php if (!empty($row['winning'])): ?><span class="rlse-winning-entry">WINNING ENTRY</span><?php endif; ?>
                                        <span><?php echo esc_html($row['participant']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="rlse-entry-empty" data-entry-empty<?php echo ($pool_rows || !$is_live) ? ' hidden' : ''; ?>><?php echo $is_live ? 'No active entries yet.' : 'No matching eligible entry.'; ?></div>
                        <?php if (count($pool_rows) < $pool_total): ?><button class="rlse-load-entries" type="button" data-load-entries>Load more entries</button><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rlse-lock-note"><i aria-hidden="true">⌁</i><span><strong data-bind="lock-title"><?php echo esc_html($is_live ? __('Live Entry Pool', 'rafflelb-selection-engine') : __('Locked Entry Pool', 'rafflelb-selection-engine')); ?></strong><small data-bind="lock-copy"><?php echo esc_html($is_live ? __('Current active entries are shown and may change while the raffle remains open.', 'rafflelb-selection-engine') : __('No new entries can be added.', 'rafflelb-selection-engine')); ?></small></span></div>
        </article>
        <?php return ob_get_clean();
    }

    private static function real_winner($data) {
        $has_result = !empty($data['result']);
        $pool_state = $data['status'] === 'live' ? __('LIVE', 'rafflelb-selection-engine') : __('LOCKED', 'rafflelb-selection-engine');
        $selection_state = $has_result
            ? __('COMPLETE', 'rafflelb-selection-engine')
            : ($data['status'] === 'awaiting'
                ? __('AWAITING SELECTION', 'rafflelb-selection-engine')
                : ($data['status'] === 'cancelled'
                    ? __('NOT PROCEEDING', 'rafflelb-selection-engine')
                    : __('WAITING FOR CLOSE', 'rafflelb-selection-engine')));
        $record_state = $has_result
            ? ($data['result']['selected_display'] ?: __('RECORDED', 'rafflelb-selection-engine'))
            : ($data['status'] === 'cancelled' ? __('NO RESULT', 'rafflelb-selection-engine') : __('PENDING', 'rafflelb-selection-engine'));
        ob_start(); ?>
        <article class="rlse-panel rlse-winner-panel<?php echo $has_result ? ' has-result' : ''; ?>" data-bind="winner-panel" aria-live="polite">
            <div class="rlse-panel-title"><span>3.</span><h3 data-bind="winner-panel-title"><?php echo esc_html($data['status'] === 'cancelled' ? __('Selection Result', 'rafflelb-selection-engine') : __('Winning Entry', 'rafflelb-selection-engine')); ?></h3></div>
            <div class="rlse-winner-burst">
                <span data-bind="winner-kicker"><?php echo $has_result ? esc_html__('WINNING ENTRY', 'rafflelb-selection-engine') : esc_html($data['status'] === 'cancelled' ? __('NO SELECTION', 'rafflelb-selection-engine') : __('AWAITING SELECTION', 'rafflelb-selection-engine')); ?></span>
                <strong data-bind="winner-entry"><?php echo esc_html($has_result ? $data['result']['entry'] : '—'); ?></strong>
            </div>
            <div class="rlse-result-note"><i aria-hidden="true" data-bind="result-icon"><?php echo $has_result ? '✓' : '○'; ?></i><span><strong data-bind="result-title"><?php echo esc_html($has_result ? __('Recorded Result', 'rafflelb-selection-engine') : ($data['status'] === 'cancelled' ? __('Raffle cancelled', 'rafflelb-selection-engine') : __('No result recorded yet', 'rafflelb-selection-engine'))); ?></strong><small data-bind="result-copy"><?php echo esc_html($has_result ? $data['result']['winner'] . ' · ' . $data['result']['selected_display'] : self::winner_waiting_copy($data['status'])); ?></small></span></div>
            <div class="rlse-winner-status" aria-label="Selection status summary">
                <span class="rlse-winner-status-kicker">SELECTION STATUS</span>
                <div><i aria-hidden="true">1</i><span><small>ENTRY POOL</small><strong data-bind="winner-pool-state"><?php echo esc_html($pool_state); ?></strong></span></div>
                <div><i aria-hidden="true">2</i><span><small>SELECTION PROCESS</small><strong data-bind="winner-selection-state"><?php echo esc_html($selection_state); ?></strong></span></div>
                <div><i aria-hidden="true">3</i><span><small>PUBLIC RECORD</small><strong data-bind="winner-record-state"><?php echo esc_html($record_state); ?></strong></span></div>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    private static function real_log($events) {
        ob_start(); ?>
        <article class="rlse-panel rlse-log-panel">
            <div class="rlse-mini-head"><h3>Recorded Process Log</h3><span class="rlse-recorded-chip">PUBLIC DATA</span></div>
            <ul data-bind="event-log">
                <?php if (!$events): ?><li class="rlse-empty-row"><span>No timestamped events are available yet.</span></li><?php endif; ?>
                <?php foreach ($events as $event): ?><li><time><?php echo esc_html($event['time']); ?></time><span><?php echo esc_html($event['label']); ?></span></li><?php endforeach; ?>
            </ul>
        </article>
        <?php return ob_get_clean();
    }

    private static function results_panel($results, $title) {
        ob_start(); ?>
        <article class="rlse-panel rlse-results-panel">
            <div class="rlse-mini-head"><h3><?php echo esc_html($title); ?></h3><a href="<?php echo esc_url(home_url('/winners/')); ?>">View all →</a></div>
            <div class="rlse-result-list">
                <?php if (!$results): ?><div class="rlse-empty-result"><strong>No recorded results yet</strong><span>Completed results will appear here automatically.</span></div><?php endif; ?>
                <?php foreach ($results as $result): ?>
                    <a href="<?php echo esc_url($result['url']); ?>">
                        <span class="rlse-result-thumb"><?php if ($result['image']): ?><img src="<?php echo esc_url($result['image']); ?>" alt=""><?php else: ?><span class="rlse-brand-placeholder rlse-brand-placeholder-compact" aria-hidden="true"><span class="rlse-brand-emblem"><b>R</b><em>LB</em></span></span><?php endif; ?></span>
                        <span><strong><?php echo esc_html($result['name']); ?></strong><small><?php echo esc_html($result['date']); ?></small></span>
                        <span><small>Winning Entry</small><strong class="rlse-lime"><?php echo esc_html($result['entry']); ?></strong></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>
        <?php return ob_get_clean();
    }

    private static function trust_panel() {
        ob_start(); ?>
        <article class="rlse-panel rlse-trust-panel">
            <div class="rlse-mini-head"><h3>Why You Can Trust It</h3></div>
            <ul>
                <li><i aria-hidden="true">▣</i><span><strong>Locked Entry Pool</strong><small>No new entries join after closing.</small></span></li>
                <li><i aria-hidden="true">↝</i><span><strong>Secure Selection Process</strong><small>The official selection process operates on the eligible locked entry pool.</small></span></li>
                <li><i aria-hidden="true">▤</i><span><strong>Recorded Results</strong><small>The official result is recorded after selection.</small></span></li>
                <li><i aria-hidden="true">◉</i><span><strong>Transparent Process</strong><small>Public status and result facts remain visible.</small></span></li>
            </ul>
        </article>
        <?php return ob_get_clean();
    }

    private static function raffle_cards($raffles) {
        ob_start(); ?>
        <div class="rlse-raffle-cards">
            <?php if (!$raffles): ?><div class="rlse-empty-state"><strong>No active raffles right now.</strong><span>New open raffles will appear here automatically.</span><a href="<?php echo esc_url(home_url('/shop/')); ?>">BROWSE SHOP →</a></div><?php endif; ?>
            <?php foreach ($raffles as $raffle): ?>
                <a class="rlse-raffle-card" href="<?php echo esc_url($raffle['selection_url']); ?>">
                    <span class="rlse-card-media"><?php if ($raffle['image']): ?><img src="<?php echo esc_url($raffle['image']); ?>" alt=""><?php else: ?><span class="rlse-brand-placeholder rlse-brand-placeholder-card" aria-hidden="true"><span class="rlse-brand-emblem"><b>RAFFLE</b><em>LB</em></span><small>PRIZE VISUAL</small></span><?php endif; ?><b class="is-<?php echo esc_attr($raffle['status']); ?>"><?php echo esc_html($raffle['status_label']); ?></b></span>
                    <span class="rlse-card-body"><strong><?php echo esc_html($raffle['name']); ?></strong><span><em><?php echo esc_html($raffle['eligible_entries']); ?> / <?php echo esc_html($raffle['total_allocation']); ?></em><i><?php echo esc_html($raffle['percent_filled']); ?>%</i></span><span class="rlse-card-bar"><i style="width:<?php echo esc_attr($raffle['percent_filled']); ?>%"></i></span><b>VIEW SELECTION STATUS →</b></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private static function engine_message($status) {
        if ($status === 'complete') return __('Selection complete. The official result is recorded.', 'rafflelb-selection-engine');
        if ($status === 'awaiting') return __('Entries are locked. Awaiting official selection.', 'rafflelb-selection-engine');
        if ($status === 'cancelled') return __('This raffle was cancelled early. The locked entry pool remains available as a public record.', 'rafflelb-selection-engine');
        return __('The raffle is open. The entry pool is not locked.', 'rafflelb-selection-engine');
    }

    private static function progress_copy($status) {
        if ($status === 'complete') return __('Recorded result', 'rafflelb-selection-engine');
        if ($status === 'awaiting') return __('Eligible entries locked', 'rafflelb-selection-engine');
        if ($status === 'cancelled') return __('Cancelled — entries locked', 'rafflelb-selection-engine');
        return __('Allocation progress', 'rafflelb-selection-engine');
    }

    private static function winner_waiting_copy($status) {
        if ($status === 'live') return __('The raffle is still open.', 'rafflelb-selection-engine');
        if ($status === 'cancelled') return __('This raffle was cancelled early. No winner will be selected.', 'rafflelb-selection-engine');
        return __('Entries are locked and awaiting the official result.', 'rafflelb-selection-engine');
    }

    private static function unavailable() {
        ob_start(); ?>
        <main class="rlse"><section class="rlse-message-state"><span>SELECTION ENGINE</span><h1>Selection status is temporarily unavailable</h1><p>The live raffle data service is not available right now. Please try again shortly.</p><a href="<?php echo esc_url(home_url('/')); ?>">RETURN HOME</a></section></main>
        <?php return ob_get_clean();
    }

    private static function not_found() {
        ob_start(); ?>
        <main class="rlse"><section class="rlse-message-state"><span>SELECTION ENGINE</span><h1>Raffle not found</h1><p>This public selection page is not available.</p><a href="<?php echo esc_url(home_url('/selection-engine/')); ?>">VIEW SELECTION ENGINE</a></section></main>
        <?php return ob_get_clean();
    }
}
