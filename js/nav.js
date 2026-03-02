document.addEventListener('DOMContentLoaded', () => {
    // Theme Management
    let tema = localStorage.getItem('tema') || 'claro';
    document.documentElement.setAttribute("data-tema", tema);
    updateThemeIcon(tema);

    // #region agent log
    try {
        const modalIds = ['timelineModal', 'scheduleModal', 'reservaModal'];
        const modalInfo = modalIds.map((id) => {
            const el = document.getElementById(id);
            if (!el) {
                return { id, present: false };
            }
            let computedDisplay = '';
            try {
                computedDisplay = window.getComputedStyle(el).display;
            } catch (_) { }
            return {
                id,
                present: true,
                className: el.className,
                hasActiveClass: el.classList.contains('active'),
                inlineDisplay: el.style.display || null,
                computedDisplay
            };
        });

        fetch('http://127.0.0.1:7570/ingest/dcc64b35-cdb2-4f1e-aca8-88a2d7de6e84', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': '5458ef'
            },
            body: JSON.stringify({
                sessionId: '5458ef',
                runId: 'modals-pre-fix',
                hypothesisId: 'H1',
                location: 'js/nav.js:DOMContentLoaded',
                message: 'Modal visibility snapshot on load',
                data: {
                    path: window.location.pathname,
                    modalInfo
                },
                timestamp: Date.now()
            })
        }).catch(() => { });
    } catch (e) {
        fetch('http://127.0.0.1:7570/ingest/dcc64b35-cdb2-4f1e-aca8-88a2d7de6e84', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': '5458ef'
            },
            body: JSON.stringify({
                sessionId: '5458ef',
                runId: 'modals-pre-fix',
                hypothesisId: 'H1',
                location: 'js/nav.js:DOMContentLoaded',
                message: 'Error capturing modal visibility snapshot',
                data: {
                    error: String(e && e.message ? e.message : e)
                },
                timestamp: Date.now()
            })
        }).catch(() => { });
    }
    // #endregion

    // Sidebar Toggle
    const arrow = document.getElementById("fechar-nav");
    const sidebar = document.querySelector(".sidebar");
    const main = document.querySelector(".main-content");

    if (arrow) {
        const btn = arrow.querySelector('button');
        if (btn) {
            const icon = btn.querySelector('i');

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isClosed = document.body.classList.contains('sidebar-closed');

                if (!isClosed) {
                    // Close it
                    document.body.classList.add('sidebar-closed');
                    document.cookie = "sidebar=closed; path=/";
                    if (icon) icon.className = 'bi bi-chevron-right';
                } else {
                    // Open it
                    document.body.classList.remove('sidebar-closed');
                    document.cookie = "sidebar=open; path=/";
                    if (icon) icon.className = 'bi bi-chevron-left';
                }
            });

            // Initial icon state
            if (icon) {
                if (document.body.classList.contains('sidebar-closed')) {
                    icon.className = 'bi bi-chevron-right';
                } else {
                    icon.className = 'bi bi-chevron-left';
                }
            }
        }
    }

    // Dropdown menus toggle (Planejamento / Exportar-Importar)
    document.querySelectorAll('.menu-manutencao .manutencao-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const menu = btn.closest('.menu-manutencao');
            const submenu = menu ? menu.querySelector('.submenu') : null;
            if (!menu || !submenu) return;
            submenu.classList.toggle('aberto');
            menu.classList.toggle('aberto');
        });
    });
});

function changeTheme() {
    let current = document.documentElement.getAttribute("data-tema");
    let next = current === 'escuro' ? 'claro' : 'escuro';

    document.documentElement.setAttribute("data-tema", next);
    localStorage.setItem('tema', next);
    updateThemeIcon(next);
}

function updateThemeIcon(tema) {
    const btn = document.getElementById("tema");
    if (btn) {
        btn.innerHTML = tema === 'escuro'
            ? '<i class="bi bi-moon-stars-fill"></i>'
            : '<i class="bi bi-brightness-high-fill"></i>';
    }
}

function changeSairBtn(state) {
    const btn = document.querySelector(".sair");
    if (btn) {
        btn.innerHTML = state === 'open'
            ? 'Sair <i class="bi bi-door-open-fill"></i>'
            : 'Sair <i class="bi bi-door-closed-fill"></i>';
    }
}

// Global Modal Helpers
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// Global click-outside listener
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});
