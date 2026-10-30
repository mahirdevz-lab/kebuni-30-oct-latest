/**
 * Tema terang/gelap (disimpan di localStorage) & notifikasi topbar.
 */
$(function () {
    const root = document.documentElement;
    const isDark = () => root.getAttribute('data-bs-theme') === 'dark';
    const syncIcon = () => $('#themeToggle i').attr('class', isDark() ? 'fa-solid fa-sun' : 'fa-solid fa-moon');
    syncIcon();

    // Sinkronisasi warna Chart.js dengan tema (legend, tooltip, grid, tick)
    function syncChartTheme() {
        if (typeof Chart === 'undefined') return;
        const dark = isDark();
        const text = dark ? '#b6c2d4' : '#64748b';
        const grid = dark ? 'rgba(139, 152, 173, .12)' : 'rgba(100, 116, 139, .12)';
        Chart.defaults.color = text;
        Chart.defaults.borderColor = grid;
        // Update chart yang sudah ada
        if (Chart.instances) {
            Object.values(Chart.instances).forEach(ch => {
                if (!ch) return;
                if (ch.options.plugins?.legend?.labels) ch.options.plugins.legend.labels.color = text;
                if (ch.options.plugins?.tooltip) {
                    ch.options.plugins.tooltip.backgroundColor = dark ? '#1a2332' : 'rgba(15, 23, 42, .9)';
                    ch.options.plugins.tooltip.titleColor = dark ? '#e8edf5' : '#fff';
                    ch.options.plugins.tooltip.bodyColor = dark ? '#b6c2d4' : '#e2e8f0';
                    ch.options.plugins.tooltip.borderColor = dark ? '#243044' : 'transparent';
                    ch.options.plugins.tooltip.borderWidth = dark ? 1 : 0;
                }
                if (ch.options.scales) {
                    Object.values(ch.options.scales).forEach(sc => {
                        if (sc.ticks) sc.ticks.color = text;
                        if (sc.grid) sc.grid.color = grid;
                    });
                }
                ch.update('none');
            });
        }
    }
    syncChartTheme();
    $(document).on('themechange', syncChartTheme);

    $('#themeToggle').on('click', function () {
        const next = isDark() ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('bo-theme', next); } catch (e) {}
        syncIcon();
        $(document).trigger('themechange', next);
    });

    if (! $('#notifList').length) return;

    const tones = { danger: ['#fee2e2', '#dc2626'], warning: ['#fef3c7', '#d97706'], info: ['#e0e7ff', '#4f46e5'] };

    function loadNotifications() {
        $.get(BASE_URL + 'admin/notifications', function (res) {
            if (! res || ! res.success) return;
            const items = res.items || [];
            const $count = $('#notifCount');
            items.length ? $count.text(res.total > 99 ? '99+' : res.total).removeClass('d-none') : $count.addClass('d-none');

            if (! items.length) {
                $('#notifList').html('<div class="p-4 text-center text-muted small"><i class="fa-regular fa-circle-check fa-2x mb-2 d-block"></i>Tidak ada notifikasi.</div>');
                return;
            }
            $('#notifList').html(items.map(i => {
                const t = tones[i.tone] || tones.info;
                return '<a class="notif-item" href="' + i.url + '">' +
                    '<span class="n-icon" style="background:' + t[0] + ';color:' + t[1] + '"><i class="fa-solid ' + i.icon + '"></i></span>' +
                    '<span><div class="fw-semibold small">' + i.title + '</div><div class="small text-muted">' + i.text + '</div></span></a>';
            }).join(''));
        });
    }

    loadNotifications();
    setInterval(loadNotifications, 120000);
});
