(function () {
    'use strict';

    function setCollapsed(section, collapsed) {
        var button = section.querySelector('.rlad-collapse-toggle');
        var icon = button ? button.querySelector('.dashicons') : null;

        section.classList.toggle('is-collapsed', collapsed);

        if (button) {
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            button.setAttribute('title', collapsed ? 'Expand section' : 'Collapse section');
        }

        if (icon) {
            icon.classList.toggle('dashicons-arrow-up-alt2', !collapsed);
            icon.classList.toggle('dashicons-arrow-down-alt2', collapsed);
        }
    }

    function storageKey(section) {
        return 'rafflelb_admin_dashboard_section_' + (section.getAttribute('data-rlad-section') || 'panel');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.rlad-collapsible').forEach(function (section) {
            var button = section.querySelector('.rlad-collapse-toggle');
            if (!button) return;

            try {
                setCollapsed(section, window.localStorage.getItem(storageKey(section)) === 'collapsed');
            } catch (e) {
                setCollapsed(section, false);
            }

            button.addEventListener('click', function () {
                var collapsed = !section.classList.contains('is-collapsed');
                setCollapsed(section, collapsed);
                try {
                    window.localStorage.setItem(storageKey(section), collapsed ? 'collapsed' : 'expanded');
                } catch (e) {}
            });
        });
    });
}());
