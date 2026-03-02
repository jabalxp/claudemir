document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('professor-calendar');

    const apiBase = '../controllers/agenda_api.php';
    let currentDocente = null;
    let docenteAgendas = [];
    let mesesOcupados = [];
    let currentDate = new Date();
    let showingMonthPicker = false;

    // Period state from URL
    const urlParams = new URLSearchParams(window.location.search);
    let currentPeriod = urlParams.get('periodo') || null;
    const periodConfig = {
        'Manhã': { inicio: '07:30', fim: '13:30', min: '07:30', max: '13:30' },
        'Tarde': { inicio: '13:30', fim: '17:30', min: '13:30', max: '17:30' },
        'Integral': { inicio: '07:30', fim: '17:30', min: '07:30', max: '17:30' },
        'Noite': { inicio: '19:30', fim: '23:30', min: '19:30', max: '23:30' }
    };

    // Reservation mode state
    let reservationMode = false;
    let reservedSlots = []; // array of dateISO strings
    let reservationsConfirmed = false; // after clicking confirm, dates turn yellow

    // Monthly stats display
    const monthlyStatsEl = document.createElement('div');
    monthlyStatsEl.id = 'calendar-monthly-stats';
    monthlyStatsEl.style.cssText = 'margin: 15px 0; font-size: 0.85rem; color: var(--text-muted); text-align: right; background: var(--bg-color); padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);';
    if (calendarEl) {
        calendarEl.parentNode.insertBefore(monthlyStatsEl, calendarEl.nextSibling);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    function escapeAttr(s) { return s.replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const diasSemanaFull = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

    const docenteSelect = document.getElementById('calendar-docente-select');

    // ============================================================
    // PROFESSOR SELECTION MODAL
    // ============================================================
    const profModal = document.getElementById('modal-selecionar-professor');
    const profSearchInput = document.getElementById('prof-search-input');
    const profAreaFilter = document.getElementById('prof-area-filter');
    const profSearchResults = document.getElementById('prof-search-results');
    const btnSelecionarProf = document.getElementById('btn-selecionar-professor');
    const btnProfLabel = document.getElementById('btn-prof-label');
    const docentes = window.__docentesData || [];

    if (btnSelecionarProf) {
        btnSelecionarProf.addEventListener('click', () => {
            profModal.classList.add('active');
            profSearchInput.value = '';
            profAreaFilter.value = '';
            renderProfessorResults();
            setTimeout(() => profSearchInput.focus(), 100);
        });
    }

    document.getElementById('modal-prof-close')?.addEventListener('click', () => profModal.classList.remove('active'));
    profModal?.addEventListener('click', e => { if (e.target === profModal) profModal.classList.remove('active'); });

    profSearchInput?.addEventListener('input', renderProfessorResults);
    profAreaFilter?.addEventListener('change', renderProfessorResults);

    function renderProfessorResults() {
        const query = (profSearchInput?.value || '').toLowerCase().trim();
        const areaFilter = profAreaFilter?.value || '';

        let filtered = docentes.filter(d => {
            const nameMatch = !query || d.nome.toLowerCase().includes(query);
            const areaMatch = !areaFilter || (d.area_conhecimento || 'Outros') === areaFilter;
            return nameMatch && areaMatch;
        });

        if (filtered.length === 0) {
            profSearchResults.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>Nenhum professor encontrado.</div>';
            return;
        }

        let html = '';
        filtered.forEach(d => {
            const area = d.area_conhecimento || 'Outros';
            html += `<div class="prof-result-item" data-id="${d.id}" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; margin-bottom: 6px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-color); cursor: pointer; transition: all 0.2s;">
                <div>
                    <strong style="font-size: 0.95rem;">${escapeHtml(d.nome)}</strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">
                        <i class="fas fa-tag" style="margin-right: 5px;"></i>${escapeHtml(area)}
                    </div>
                </div>
                <button type="button" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.8rem; white-space: nowrap;">
                    <i class="fas fa-check" style="margin-right: 5px;"></i>Selecionar
                </button>
            </div>`;
        });
        profSearchResults.innerHTML = html;

        // Add click listeners
        profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
            item.addEventListener('click', function () {
                const id = this.dataset.id;
                const prof = docentes.find(d => String(d.id) === String(id));
                if (!prof) return;

                // Update the hidden select
                if (docenteSelect) docenteSelect.value = id;
                if (btnProfLabel) btnProfLabel.textContent = prof.nome;
                if (btnSelecionarProf) {
                    btnSelecionarProf.style.background = '#2e7d32';
                    btnSelecionarProf.style.borderColor = '#1b5e20';
                }

                // If we're not in the main calendar/SPA view, we should reload with the search filter
                const calendarElement = document.getElementById('professor-calendar');
                if (!calendarElement) {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('search', prof.nome);
                    currentUrl.searchParams.set('docente_id', prof.id);
                    currentUrl.searchParams.delete('page'); // Reset to first page
                    window.location.href = currentUrl.toString();
                    return;
                }

                // Close modal and load agenda (SPA mode)
                profModal.classList.remove('active');
                if (typeof loadDocenteAgenda === 'function') loadDocenteAgenda(id);
            });
        });

        // Hover styles
        profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.borderColor = 'var(--primary-red)';
                item.style.background = 'rgba(229, 57, 53, 0.05)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.borderColor = 'var(--border-color)';
                item.style.background = 'var(--bg-color)';
            });
        });
    }



    // ============================================================
    // LOAD DOCENTE AGENDA
    // ============================================================
    if (docenteSelect) {
        docenteSelect.addEventListener('change', function () {
            const id = this.value;
            if (id) loadDocenteAgenda(id);
            else {
                currentDocente = null;
                docenteAgendas = [];
                mesesOcupados = [];
                renderCalendar();
            }
        });
    }

    window.loadDocenteAgenda = function (docenteId, focusDate = null) {
        return fetch(`${apiBase}?action=get_docente_agenda&docente_id=${docenteId}`)
            .then(r => r.json())
            .then(data => {
                currentDocente = data.docente;
                docenteAgendas = data.agendas || [];
                mesesOcupados = data.meses_ocupados || [];

                if (focusDate) {
                    const parts = focusDate.split('-');
                    if (parts.length === 3) {
                        currentDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                    }
                } else {
                    const now = new Date();
                    currentDate = new Date(now.getFullYear(), now.getMonth(), 1);
                }
                showingMonthPicker = false;
                renderCalendar();
                updateAvailabilityBar();

                // Show availability section
                const availSec = document.getElementById('availability-section');
                if (availSec) availSec.style.display = '';
                const btnAgendar = document.getElementById('btn-agendar-bar');
                if (btnAgendar) btnAgendar.style.display = '';

                // Pre-fill docente_id1 in the scheduling modal
                const d1 = document.querySelector('#form-agendar-calendar select[name="docente_id1"]');
                if (d1) d1.value = docenteId;
            })
            .catch(err => console.error(err));
    }

    // ============================================================
    // CALENDAR RENDERING
    // ============================================================
    window.renderCalendar = function () {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDayOfWeek = firstDay.getDay();
        const totalDays = lastDay.getDate();

        // Detect blocking overlap rules
        const blockingOverlaps = {
            'Integral': ['Manhã', 'Tarde'],
            'Manhã': ['Integral'],
            'Tarde': ['Integral']
        };

        // Info text - moved to a subtle place
        let infoHtml = '';
        // (We can remove the global month-wide otherPeriods check as we'll do it per-day if needed, 
        // or just keep the period badge in the header)


        // Period header badge — ensure high contrast
        const periodBadge = currentPeriod
            ? `<span style="font-size: 0.8rem; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.4); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; margin-left: 10px;">${currentPeriod}</span>`
            : '';

        let html = `<div class="cal-header" style="position: relative;">
            <button class="cal-nav-btn" id="cal-prev"><i class="fas fa-chevron-left"></i></button>
            <div style="text-align: center;">
                <h3 class="cal-month-title" id="cal-month-title" style="margin-bottom: 0;">${mesesNomes[month]} ${year}${periodBadge}</h3>
                ${infoHtml}
            </div>
            <button class="cal-nav-btn" id="cal-next"><i class="fas fa-chevron-right"></i></button>
        </div>`;

        if (showingMonthPicker) html += renderMonthPicker(year);
        else {
            html += `<div class="cal-grid-wrapper" style="position: relative;">
                <div class="cal-grid">`;
            diasSemana.forEach(d => html += `<div class="cal-day-header">${d}</div>`);
            for (let i = 0; i < startDayOfWeek; i++) html += `<div class="cal-day empty"></div>`;
            for (let day = 1; day <= totalDays; day++) {
                const dateObj = new Date(year, month, day);
                const dayOfWeek = dateObj.getDay();
                const dayName = diasSemanaFull[dayOfWeek];
                const isToday = isSameDay(dateObj, new Date());
                const dateISO = formatISO(dateObj);
                const aulasNoDia = getAulasNoDia(dateObj, dayName);
                const hasAula = aulasNoDia.length > 0;
                const isSunday = dayOfWeek === 0;
                const isReservedDate = reservedSlots.includes(dateISO);

                let statusClass = '';
                if (hasAula) {
                    const hasReserved = aulasNoDia.some(a => a.status === 'RESERVADO');
                    const hasConfirmed = aulasNoDia.some(a => a.status !== 'RESERVADO');
                    if (hasReserved && !hasConfirmed) statusClass = ' reservado';
                    else statusClass = ' has-aula';
                }
                if (isReservedDate && !hasAula) statusClass = ' reservado';

                let classes = 'cal-day' + (isToday ? ' today' : '') + statusClass + (isSunday ? ' domingo' : '');
                let tooltipContent = hasAula && currentDocente
                    ? `<strong>${escapeHtml(currentDocente.nome)}</strong><br><i class="far fa-calendar-alt"></i> ${fmtDate(dateObj)}<br>` + aulasNoDia.map(a => {
                        const statusLabel = a.status === 'RESERVADO' ? ' [RESERVADO]' : '';
                        const timeStr = (a.horario_inicio && a.horario_fim)
                            ? `${a.horario_inicio.substring(0, 5)} - ${a.horario_fim.substring(0, 5)}`
                            : 'Reservado';
                        return `<i class="far fa-clock"></i> ${a.curso_nome} | ${timeStr}${statusLabel}`;
                    }).join('<br>')
                    : '';

                let classTimesHtml = '';
                if (hasAula && currentDocente) {
                    classTimesHtml = aulasNoDia.map(a => {
                        const isRes = a.status === 'RESERVADO';
                        const color = isRes ? '#ffb300' : 'var(--primary-red)';
                        let timeRange = 'Reservado';
                        if (a.horario_inicio && a.horario_fim) {
                            timeRange = `${a.horario_inicio.substring(0, 5)}-${a.horario_fim.substring(0, 5)}`;
                        } else if (a.periodo) {
                            const profShort = currentDocente ? (currentDocente.nome.split(' ')[0]) : '';
                            timeRange = `Reservado por ${profShort} | ${a.periodo}`;
                        }
                        return `<span class="cal-class-time" style="color:${color};">${timeRange}</span>`;
                    }).join('');
                }

                // Check for classes in OTHER periods (for visual indicator)
                let otherPeriodDot = '';
                let conflictLabel = '';
                if (currentPeriod && currentDocente && !isSunday) {
                    const allAulasDay = getAllAulasNoDia(dateObj, dayName);

                    // Identify specific conflict for THIS day
                    const dayOtherPeriods = new Set();
                    allAulasDay.forEach(a => { if (a.periodo !== currentPeriod) dayOtherPeriods.add(a.periodo); });

                    const dayConflicts = blockingOverlaps[currentPeriod]
                        ? Array.from(dayOtherPeriods).filter(p => blockingOverlaps[currentPeriod].includes(p))
                        : [];

                    if (dayConflicts.length > 0) {
                        conflictLabel = `<div style="background: rgba(229,57,53,0.15); color: #e53935; padding: 2px 4px; border-radius: 4px; font-size: 0.65rem; text-transform: lowercase; border: 1px solid rgba(229,57,53,0.2); backdrop-filter: blur(2px); margin-top: 2px; line-height: 1.1;">
                            período inválido
                        </div>`;
                    } else if (dayOtherPeriods.size > 0 && !hasAula) {
                        otherPeriodDot = `<span style="display:block; width:6px; height:6px; border-radius:50%; background:#ffb300; margin: 2px auto 0; opacity:0.8;" title="Aula em outro período"></span>`;
                        classes += ' has-other-period';
                    }
                }

                html += `<div class="${classes}" ${hasAula ? `data-tooltip="${escapeAttr(tooltipContent)}"` : ''} data-date="${dateISO}">
                    <span class="cal-day-num">${day}</span>
                    ${hasAula && currentDocente ? `<span class="cal-prof-name">${escapeHtml(currentDocente.nome.split(' ')[0])}</span>` : ''}
                    ${classTimesHtml}
                    ${otherPeriodDot}
                    ${conflictLabel}
                    ${isSunday ? '<span class="cal-sunday-label">Indisponível</span>' : ''}
                </div>`;
            }
            html += `</div></div>`;
        }
        if (calendarEl) { calendarEl.innerHTML = html; }

        // Navigation
        document.getElementById('cal-prev')?.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); });
        document.getElementById('cal-next')?.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); });
        document.getElementById('cal-month-title')?.addEventListener('click', () => { showingMonthPicker = !showingMonthPicker; renderCalendar(); });
        document.querySelectorAll('.cal-month-item').forEach(item => item.addEventListener('click', function () { currentDate.setMonth(parseInt(this.dataset.month)); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); }));

        // Day click handlers
        document.querySelectorAll('.cal-day:not(.empty):not(.domingo)').forEach(day => {
            day.addEventListener('click', function () {
                const dateISO = this.dataset.date;

                if (reservationMode && currentDocente) {
                    // Check if this day is already a confirmed reservation (yellow in DB)
                    const isAlreadyReserved = docenteAgendas.some(a => a.status === 'RESERVADO' && a.data_inicio === dateISO);

                    // Toggle selection for this day
                    const idx = reservedSlots.indexOf(dateISO);
                    if (idx >= 0) {
                        reservedSlots.splice(idx, 1);
                        this.classList.remove('reservado', 'reservado-remove');
                        // Restore original yellow if it was already reserved in DB
                        if (isAlreadyReserved) this.classList.add('reservado');
                    } else {
                        reservedSlots.push(dateISO);
                        if (isAlreadyReserved) {
                            // Mark for removal: red visual
                            this.classList.add('reservado-remove');
                        } else {
                            // New reservation selection: yellow visual
                            this.classList.add('reservado');
                        }
                    }
                    updateReservationCount();
                } else if (reservationsConfirmed && reservedSlots.includes(dateISO)) {
                    // Clicking a confirmed (yellow) reservation opens the scheduling modal
                    window.openCalendarScheduleModal(dateISO, dateISO);
                } else if (dateISO && typeof window.openCalendarScheduleModal === 'function') {
                    window.openCalendarScheduleModal(dateISO, dateISO);
                }
            });
        });

        if (typeof setupTooltips === 'function') setupTooltips();
        updateMonthlyStats();
    }

    // ============================================================
    // RESERVATION MODE
    // ============================================================
    window.toggleReservationMode = function () {
        if (!currentDocente) {
            showNotification('Selecione um professor primeiro.', 'error');
            return;
        }
        reservationMode = true;
        reservationsConfirmed = false;
        reservedSlots = [];
        const btnModo = document.getElementById('btn-modo-reserva-unificado') || document.getElementById('btn-modo-reserva');
        if (btnModo) btnModo.style.display = 'none';
        document.getElementById('btn-confirmar-reserva').style.display = 'inline-flex';
        const btnRemoverBatch = document.getElementById('btn-remover-selecionados');
        if (btnRemoverBatch) btnRemoverBatch.style.display = 'inline-flex';
        document.getElementById('btn-cancelar-reserva').style.display = 'inline-flex';
        showNotification('Modo Reserva ativo. Clique nos dias disponíveis para reservar.', 'info');
        renderCalendar();
    };

    window.cancelReservationMode = function () {
        reservationMode = false;
        reservationsConfirmed = false;
        reservedSlots = [];
        const btnModo = document.getElementById('btn-modo-reserva-unificado') || document.getElementById('btn-modo-reserva');
        if (btnModo) btnModo.style.display = 'inline-flex';
        document.getElementById('btn-confirmar-reserva').style.display = 'none';
        const btnRemoverBatch = document.getElementById('btn-remover-selecionados');
        if (btnRemoverBatch) btnRemoverBatch.style.display = 'none';
        document.getElementById('btn-cancelar-reserva').style.display = 'none';
        renderCalendar();
    };

    window.confirmReservations = function () {
        let isAgendaMode = typeof reservaModeActive !== 'undefined' && reservaModeActive;
        let selected = isAgendaMode ? reservaSelectedDates.map(d => d.date) : reservedSlots;
        let pId = isAgendaMode && reservaCurrentProfId ? reservaCurrentProfId : docenteSelect.value;

        if (!selected || selected.length === 0) {
            showNotification('Nenhum dia selecionado para reservar.', 'error');
            return;
        }

        let conflictFound = false;
        if (isAgendaMode) {
            conflictFound = reservaSelectedDates.some(d => d.isAlreadyReserved);
        } else if (typeof docenteAgendas !== 'undefined') {
            conflictFound = selected.some(d => docenteAgendas.some(a => a.status === 'RESERVADO' && a.data_inicio === d));
        }

        if (conflictFound) {
            showNotification('Atenção: Você selecionou datas que já estão reservadas! Para alterá-las, desmarque ou utilize "Remover".', 'error');
            return;
        }

        // Save reservations to the database
        const fd = new FormData();
        fd.append('action', 'save_reservations');
        fd.append('docente_id', pId);
        if (currentPeriod) fd.append('periodo', currentPeriod);
        selected.forEach(d => fd.append('dates[]', d));

        fetch(apiBase, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (isAgendaMode && typeof cancelReservaSelection === 'function') {
                        cancelReservaSelection();
                        location.reload();
                    } else {
                        reservationMode = false;
                        reservationsConfirmed = true;
                        const btnModo = document.getElementById('btn-modo-reserva-unificado') || document.getElementById('btn-modo-reserva');
                        if (btnModo) btnModo.style.display = 'inline-flex';
                        document.getElementById('btn-confirmar-reserva').style.display = 'none';
                        document.getElementById('btn-cancelar-reserva').style.display = 'none';
                        showNotification(`${selected.length} dia(s) reservado(s) e salvos. Clique em um dia amarelo para cadastrar o horário.`, 'success');
                        loadDocenteAgenda(pId);
                    }
                } else {
                    showNotification(data.message || 'Erro ao salvar reservas.', 'error');
                }
            })
            .catch(() => showNotification('Erro de conexão ao salvar reservas.', 'error'));
    };

    window.batchRemoveReservations = function () {
        let isAgendaMode = typeof reservaModeActive !== 'undefined' && reservaModeActive;
        let selected = isAgendaMode ? reservaSelectedDates.map(d => d.date) : reservedSlots;
        let pId = isAgendaMode && reservaCurrentProfId ? reservaCurrentProfId : docenteSelect.value;

        if (!selected || selected.length === 0) {
            showNotification('Selecione os dias reservados que deseja remover.', 'error');
            return;
        }

        if (!confirm(`Deseja realmente remover ${selected.length} reserva(s) selecionada(s)?`)) return;

        const fd = new FormData();
        fd.append('action', 'remove_reservations_batch');
        fd.append('docente_id', pId);
        if (currentPeriod) fd.append('periodo', currentPeriod);
        selected.forEach(d => fd.append('dates[]', d));

        fetch(apiBase, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (isAgendaMode && typeof cancelReservaSelection === 'function') {
                        cancelReservaSelection();
                        location.reload();
                    } else {
                        reservationMode = false;
                        const btnModo = document.getElementById('btn-modo-reserva-unificado') || document.getElementById('btn-modo-reserva');
                        if (btnModo) btnModo.style.display = 'inline-flex';
                        document.getElementById('btn-confirmar-reserva').style.display = 'none';
                        const btnRemoverBatch = document.getElementById('btn-remover-selecionados');
                        if (btnRemoverBatch) btnRemoverBatch.style.display = 'none';
                        document.getElementById('btn-cancelar-reserva').style.display = 'none';

                        showNotification(data.message, 'success');
                        loadDocenteAgenda(pId);
                    }
                } else {
                    showNotification(data.message || 'Erro ao remover reservas.', 'error');
                }
            })
            .catch(() => showNotification('Erro de conexão ao remover reservas.', 'error'));
    };

    function updateReservationCount() {
        const btn = document.getElementById('btn-confirmar-reserva');
        if (btn) {
            btn.innerHTML = `<i class="fas fa-check"></i> Confirmar Reserva (${reservedSlots.length})`;
        }
        const btnRem = document.getElementById('btn-remover-selecionados');
        if (btnRem) {
            btnRem.innerHTML = `<i class="fas fa-trash-alt"></i> Remover Selecionados (${reservedSlots.length})`;
        }
    }

    function updateTeacherDropdowns() {
        const selects = document.querySelectorAll('.docente-turma-select');
        const selectedValues = Array.from(selects)
            .map(s => s.value)
            .filter(v => v !== "");

        selects.forEach(select => {
            const currentValue = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value === "") return;
                // If it's selected in ANOTHER dropdown, hide it
                const isSelectedElsewhere = selectedValues.includes(option.value) && option.value !== currentValue;
                option.hidden = isSelectedElsewhere;
                option.style.display = isSelectedElsewhere ? 'none' : '';
            });
        });
    }

    document.querySelectorAll('.docente-turma-select').forEach(select => {
        select.addEventListener('change', updateTeacherDropdowns);
    });

    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    function updateMonthlyStats() {
        if (!docenteAgendas || !currentDate) return;
        const viewMonth = currentDate.getMonth();
        const viewYear = currentDate.getFullYear();
        const lastDay = new Date(viewYear, viewMonth + 1, 0).getDate();

        let occupiedDaysCount = 0;
        for (let day = 1; day <= lastDay; day++) {
            const dateObj = new Date(viewYear, viewMonth, day);
            const dayOfWeek = dateObj.getDay();
            if (dayOfWeek === 0) continue; // Skip Sundays

            const dayName = diasSemanaFull[dayOfWeek];
            if (getAulasNoDia(dateObj, dayName).length > 0) {
                occupiedDaysCount++;
            }
        }

        const mStats = document.getElementById('calendar-monthly-stats');
        if (mStats) {
            const periodText = currentPeriod ? ` (Período: ${currentPeriod})` : '';
            mStats.innerHTML = `<i class="fas fa-info-circle" style="color:var(--primary-color);"></i> <strong>${mesesNomes[viewMonth]} ${viewYear}${periodText}</strong>: ${occupiedDaysCount} dia(s) ocupado(s).`;
        }
    }

    function formatISO(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function renderMonthPicker(year) {
        let html = `<div class="cal-month-picker">`;
        mesesNomes.forEach((m, i) => {
            const isActive = i === currentDate.getMonth();
            const mesChave = year + '-' + String(i + 1).padStart(2, '0');
            const temAula = mesesOcupados.includes(mesChave);

            // Check for classes in specific periods for this month
            let periodInfo = '';
            if (currentDocente && currentPeriod) {
                const monthStart = new Date(year, i, 1);
                const monthEnd = new Date(year, i + 1, 0);
                let hasCurrentPeriod = false;
                let monthOtherPeriods = new Set();

                for (let d = 1; d <= monthEnd.getDate(); d++) {
                    const dd = new Date(year, i, d);
                    if (dd.getDay() === 0) continue;
                    const dn = diasSemanaFull[dd.getDay()];
                    const allAulas = getAllAulasNoDia(dd, dn);
                    allAulas.forEach(a => {
                        if (a.periodo === currentPeriod) hasCurrentPeriod = true;
                        else monthOtherPeriods.add(a.periodo);
                    });
                }

                if (hasCurrentPeriod) {
                    periodInfo = `<span style="display:block; font-size:0.6rem; color:#4caf50; margin-top:2px;"><i class="fas fa-check-circle"></i> ${currentPeriod}</span>`;
                }
                if (monthOtherPeriods.size > 0) {
                    const otherText = Array.from(monthOtherPeriods).join(', ');
                    periodInfo += `<span style="display:block; font-size:0.55rem; color:#ffb300; margin-top:1px;" title="Possui aulas em: ${otherText}"><i class="fas fa-exclamation-circle"></i> Outros períodos</span>`;
                }
            }

            const dotClass = temAula ? 'month-dot-busy' : 'month-dot-free';
            html += `<div class="cal-month-item ${isActive ? 'active' : ''}" data-month="${i}">${m}${currentDocente ? `<span class="month-dot ${dotClass}"></span>` : ''}${periodInfo}</div>`;
        });
        return html + `</div>`;
    }

    function getAulasNoDia(dateObj, dayName) {
        if (!docenteAgendas.length) return [];
        return docenteAgendas.filter(a => {
            const agDateStart = new Date(a.data_inicio + 'T00:00:00');
            const agDateEnd = new Date(a.data_fim + 'T00:00:00');

            const dateMatches = (dateObj >= agDateStart && dateObj <= agDateEnd && a.dia_semana === dayName);
            if (!dateMatches) return false;

            // If period filter is active, only show matching classes
            if (currentPeriod) {
                return a.periodo === currentPeriod;
            }
            return true;
        });
    }

    // Unfiltered version — returns ALL classes regardless of period
    function getAllAulasNoDia(dateObj, dayName) {
        if (!docenteAgendas.length) return [];
        return docenteAgendas.filter(a => {
            const agDateStart = new Date(a.data_inicio + 'T00:00:00');
            const agDateEnd = new Date(a.data_fim + 'T00:00:00');
            return dateObj >= agDateStart && dateObj <= agDateEnd && a.dia_semana === dayName;
        });
    }

    function setupTooltips() {
        const t = document.getElementById('cal-tooltip') || createTooltip();
        document.querySelectorAll('.cal-day[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', e => { t.innerHTML = el.dataset.tooltip; t.style.display = 'block'; posTooltip(t, e); });
            el.addEventListener('mousemove', e => posTooltip(t, e));
            el.addEventListener('mouseleave', () => t.style.display = 'none');
        });
    }

    function createTooltip() { const t = document.createElement('div'); t.id = 'cal-tooltip'; t.className = 'cal-tooltip'; document.body.appendChild(t); return t; }
    function posTooltip(t, e) { t.style.left = (e.clientX + 12) + 'px'; t.style.top = (e.clientY - 10) + 'px'; }

    function updateAvailabilityBar() {
        const barEl = document.getElementById('availability-bar');
        if (!barEl) return;
        const year = currentDate.getFullYear(), month = currentDate.getMonth();
        const lastDay = new Date(year, month + 1, 0).getDate();
        let totalUteis = 0, diasOcup = 0;
        for (let day = 1; day <= lastDay; day++) {
            const d = new Date(year, month, day);
            if (d.getDay() === 0) continue;
            totalUteis++;
            if (getAulasNoDia(d, diasSemanaFull[d.getDay()]).length > 0) diasOcup++;
        }
        const libres = totalUteis - diasOcup, pctOcup = totalUteis > 0 ? Math.round((diasOcup / totalUteis) * 100) : 0, pctLivre = 100 - pctOcup;
        barEl.innerHTML = `<div class="avail-bar-track">
            <div class="avail-bar-free" style="width: ${pctLivre}%">${pctLivre > 15 ? `${libres} livres (${pctLivre}%)` : ''}</div>
            <div class="avail-bar-busy" style="width: ${pctOcup}%">${pctOcup > 15 ? `${diasOcup} ocupados (${pctOcup}%)` : ''}</div>
        </div>`;
    }

    function fmtDate(d) { return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear(); }
    function isSameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    // ============================================================
    // OPEN SCHEDULE MODAL
    // ============================================================
    window.openCalendarScheduleModal = function (startISO = null, endISO = null) {
        const m = document.getElementById('modal-agendar-calendar');
        if (!m || !currentDocente) return;

        document.getElementById('modal-cal-docente-nome').textContent = currentDocente.nome;
        document.getElementById('modal-cal-docente-id').value = docenteSelect.value;
        if (startISO) document.querySelector('#form-agendar-calendar input[name="data_inicio"]').value = startISO;
        if (endISO) document.querySelector('#form-agendar-calendar input[name="data_fim"]').value = endISO;

        // Auto-check the clicked day(s)
        if (startISO) {
            let daysToSelect = new Set();
            const isReservedClick = docenteAgendas.some(a => a.status === 'RESERVADO' && a.data_inicio === startISO);

            if (isReservedClick) {
                // Find all reserved dates in the same week
                const clickedDate = new Date(startISO + 'T00:00:00');
                const dayOfWeek = clickedDate.getDay();
                const diffToMon = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
                const weekStart = new Date(clickedDate);
                weekStart.setDate(clickedDate.getDate() - diffToMon);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);

                let minDateIso = startISO;
                let maxDateIso = startISO;

                docenteAgendas.filter(a => {
                    if (a.status !== 'RESERVADO') return false;
                    const aDate = new Date(a.data_inicio + 'T00:00:00');
                    return aDate >= weekStart && aDate <= weekEnd;
                }).forEach(a => {
                    daysToSelect.add(a.dia_semana);
                    if (a.data_inicio > maxDateIso) maxDateIso = a.data_inicio;
                    if (a.data_inicio < minDateIso) minDateIso = a.data_inicio;
                });

                document.querySelector('#form-agendar-calendar input[name="data_inicio"]').value = minDateIso;
                document.querySelector('#form-agendar-calendar input[name="data_fim"]').value = maxDateIso;
            } else {
                const dateObj = new Date(startISO + 'T00:00:00');
                const dayName = diasSemanaFull[dateObj.getDay()];
                daysToSelect.add(dayName);
            }

            document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]').forEach(cb => {
                cb.checked = daysToSelect.has(cb.value);
            });
        }

        // Set period from global period selector OR reservation
        const periodSelect = document.getElementById('modal-cal-periodo');
        if (periodSelect) {
            let periodToSet = currentPeriod;

            // If no global period filter, try to get from the clicked reservation
            if (!periodToSet && startISO) {
                const resFound = docenteAgendas.find(a => a.status === 'RESERVADO' && a.data_inicio === startISO);
                if (resFound) periodToSet = resFound.periodo;
            }

            if (periodToSet) {
                periodSelect.value = periodToSet;
                applyPeriodToForm(periodToSet);
            }
        }

        // Conflict Prevention
        disableOccupiedDays(startISO, endISO);

        // Room Restriction
        filterRoomsByCourse();

        // Initial teacher dropdown update
        updateTeacherDropdowns();

        // Admin actions
        const adminSection = document.getElementById('admin-reservation-actions');
        if (adminSection) {
            const isReserved = docenteAgendas.some(a => a.status === 'RESERVADO' && a.data_inicio === startISO && (currentPeriod ? a.periodo === currentPeriod : true));
            adminSection.style.display = (window.__isAdmin && isReserved) ? 'block' : 'none';

            // Re-setup the click listener to avoid duplicates
            const btnRemover = document.getElementById('btn-remover-reserva');
            if (btnRemover) {
                btnRemover.onclick = () => {
                    if (confirm('Deseja realmente remover a reserva deste dia?')) {
                        const data = new FormData();
                        data.append('action', 'remove_reservation');
                        data.append('docente_id', currentDocente.id);
                        data.append('data', startISO);

                        // Use effectively selected period in modal
                        const modalPeriod = document.getElementById('modal-cal-periodo')?.value;
                        data.append('periodo', modalPeriod || currentPeriod || '');

                        fetch(apiBase, { method: 'POST', body: data })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    m.classList.remove('active');
                                    loadDocenteAgenda(currentDocente.id);
                                } else {
                                    alert('Erro ao remover reserva: ' + (res.message || 'Erro desconhecido'));
                                }
                            })
                            .catch(err => console.error(err));
                    }
                };
            }
        }

        enforceSaturdayNightConstraint();
        m.classList.add('active');
    };

    function enforceSaturdayNightConstraint() {
        const periodSelect = document.getElementById('modal-cal-periodo');
        const checkboxes = document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]');
        const sabCheckbox = Array.from(checkboxes).find(cb => cb.value === 'Sábado');
        if (!periodSelect || !sabCheckbox) return;

        const isNoite = periodSelect.value === 'Noite';
        const isSabChecked = sabCheckbox.checked;

        // Constraint 1: If Noite selected, Sab cannot be checked
        if (isNoite) {
            sabCheckbox.disabled = true;
            if (sabCheckbox.checked) {
                sabCheckbox.checked = false;
            }
            const label = sabCheckbox.closest('label') || sabCheckbox.parentElement;
            if (label) {
                label.style.opacity = '0.4';
                label.title = 'Não é permitido aulas no período Noite aos Sábados.';
                label.classList.add('info-tooltip'); // Assuming you have or will add styling for this
            }
        } else {
            // Re-enable Sab if it's not blocked by other logic (disableOccupiedDays)
            if (!sabCheckbox.dataset.occupied) {
                sabCheckbox.disabled = false;
                const label = sabCheckbox.closest('label') || sabCheckbox.parentElement;
                if (label) {
                    label.style.opacity = '1';
                    label.title = '';
                }
            }
        }

        // Constraint 2: If Sab checked, Noite option is disabled
        const noiteOption = Array.from(periodSelect.options).find(opt => opt.value === 'Noite');
        if (noiteOption) {
            if (isSabChecked) {
                noiteOption.disabled = true;
                if (periodSelect.value === 'Noite') {
                    periodSelect.value = ''; // Reset if it was Noite
                    alert('Aviso: Período "Noite" não é permitido aos Sábados. Por favor, selecione outro período.');
                }
            } else {
                noiteOption.disabled = false;
            }
        }
    }

    function disableOccupiedDays(startISO, endISO) {
        if (!docenteAgendas.length || !startISO) return;
        const startDate = new Date(startISO + 'T00:00:00');
        const endDate = endISO ? new Date(endISO + 'T00:00:00') : startDate;
        const occupiedDays = new Set();

        docenteAgendas.forEach(a => {
            if (a.status === 'RESERVADO') return; // Reservations don't block the day here
            const agStart = new Date(a.data_inicio + 'T00:00:00');
            const agEnd = new Date(a.data_fim + 'T00:00:00');
            if (agStart <= endDate && agEnd >= startDate) {
                occupiedDays.add(a.dia_semana);
            }
        });

        document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]').forEach(cb => {
            const label = cb.closest('label') || cb.parentElement;
            if (occupiedDays.has(cb.value)) {
                cb.disabled = true;
                cb.checked = false;
                cb.dataset.occupied = 'true';
                if (label) { label.style.opacity = '0.4'; label.title = `${cb.value}: Professor já tem aula neste dia`; }
            } else {
                cb.disabled = false;
                cb.dataset.occupied = '';
                if (label) { label.style.opacity = '1'; label.title = ''; }
            }
        });
    }

    // ============================================================
    // PERIOD -> TIME CONSTRAINTS IN MODAL
    // ============================================================
    function applyPeriodToForm(periodo) {
        const config = periodConfig[periodo];
        if (!config) return;

        const hInicio = document.querySelector('#form-agendar-calendar input[name="horario_inicio"]');
        const hFim = document.querySelector('#form-agendar-calendar input[name="horario_fim"]');
        const horarioFields = document.getElementById('horario-fields');

        if (hInicio) { hInicio.value = config.inicio; hInicio.min = config.min; hInicio.max = config.max; }
        if (hFim) { hFim.value = config.fim; hFim.min = config.min; hFim.max = config.max; }
        if (horarioFields) horarioFields.style.display = '';
    }

    // Period select change in modal
    const periodSelect = document.getElementById('modal-cal-periodo');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const p = this.value;
            if (p && periodConfig[p]) {
                applyPeriodToForm(p);
            }
            enforceSaturdayNightConstraint();
        });
    }

    // Add listener to checkboxes for Saturday Night constraint
    document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]').forEach(cb => {
        cb.addEventListener('change', enforceSaturdayNightConstraint);
    });

    // ============================================================
    // ROOM RESTRICTION: Informática only for TI
    // ============================================================
    const formModal = document.getElementById('form-agendar-calendar');

    function filterRoomsByCourse() {
        if (!formModal) return;
        const cursoSelect = formModal.querySelector('select[name="curso_id"]');
        const selectedCourse = cursoSelect?.options[cursoSelect.selectedIndex];
        const area = (selectedCourse?.dataset?.area || '').toLowerCase();
        const isTI = area.includes('ti') || area.includes('software') || area.includes('hardware') || area.includes('computação');

        const ambienteSelect = formModal.querySelector('select[name="ambiente_id"]');
        if (!ambienteSelect) return;
        const options = ambienteSelect.querySelectorAll('option[data-tipo]');
        const groups = ambienteSelect.querySelectorAll('optgroup');
        let currentRoomStillValid = true;
        const currentRoomId = ambienteSelect.value;

        options.forEach(opt => {
            const isInf = opt.dataset.tipo === 'Informática';
            if (isInf && !isTI) {
                opt.disabled = true; opt.style.display = 'none';
                if (opt.value === currentRoomId) currentRoomStillValid = false;
            } else {
                opt.disabled = false; opt.style.display = '';
            }
        });

        groups.forEach(group => {
            const groupOptions = Array.from(group.querySelectorAll('option'));
            const hasVisible = groupOptions.some(opt => opt.style.display !== 'none');
            group.style.display = hasVisible ? '' : 'none';
        });

        if (!currentRoomStillValid) ambienteSelect.value = '';
    }

    if (formModal) {
        formModal.querySelector('select[name="curso_id"]')?.addEventListener('change', filterRoomsByCourse);
    }

    // ============================================================
    // MODAL CLOSE
    // ============================================================
    const modalCal = document.getElementById('modal-agendar-calendar');
    if (modalCal) {
        document.getElementById('modal-cal-close')?.addEventListener('click', () => modalCal.classList.remove('active'));
        modalCal.addEventListener('click', e => { if (e.target === modalCal) modalCal.classList.remove('active'); });
    }

    // ============================================================
    // FORM SUBMISSION
    // ============================================================
    if (formModal) {
        const ii = formModal.querySelector('input[name="data_inicio"]');
        const ifim = formModal.querySelector('input[name="data_fim"]');
        if (ii) ii.addEventListener('change', () => disableOccupiedDays(ii.value, ifim?.value));
        if (ifim) ifim.addEventListener('change', () => disableOccupiedDays(ii?.value, ifim.value));

        formModal.addEventListener('submit', function (e) {
            e.preventDefault();

            // Final check for Saturday Night constraint
            const periodValue = document.getElementById('modal-cal-periodo')?.value;
            const checkboxes = document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]');
            const isSabChecked = Array.from(checkboxes).some(cb => cb.value === 'Sábado' && cb.checked);

            if (periodValue === 'Noite' && isSabChecked) {
                alert('ERRO: Não é permitido agendar aulas no período "Noite" aos Sábados. Por favor, ajuste o período ou os dias selecionados.');
                return;
            }

            const fd = new FormData(this);
            const isSim = document.getElementById('simulacao-toggle')?.checked;
            const resDiv = document.getElementById('simulation-results');

            fd.append('action', 'salvar_horario');
            if (isSim) fd.append('is_simulation', '1');

            fetch(apiBase, { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                if (isSim) {
                    resDiv.style.display = 'block';
                    resDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    if (data.success) {
                        resDiv.innerHTML = `<div style="background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 10px; border: 2px solid #2e7d32; font-weight: 700; margin-bottom: 10px; animation: fadeIn 0.3s;">
                            <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-right: 8px;"></i> Simulação OK: Nenhum conflito encontrado.
                        </div>`;
                    } else {
                        const msgs = data.message.split(' | ');
                        resDiv.innerHTML = `<div style="background: #ffebee; color: var(--primary-red); padding: 15px; border-radius: 10px; border: 2px solid var(--primary-red); margin-bottom: 10px; animation: shake 0.4s;">
                            <strong style="display: block; margin-bottom: 10px; font-size: 1rem;"><i class="fas fa-exclamation-triangle"></i> Conflitos Identificados:</strong>
                            <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; line-height: 1.4;">
                                ${msgs.map(m => `<li style="margin-bottom: 5px;">${escapeHtml(m)}</li>`).join('')}
                            </ul>
                            <p style="margin-top: 10px; font-size: 0.8rem; font-style: italic; opacity: 0.8;">Ajuste as datas ou horários para prosseguir com o agendamento real.</p>
                        </div>`;
                    }
                    return;
                }

                if (data.success) {
                    modalCal.classList.remove('active');
                    const startVal = fd.get('data_inicio');
                    const did = docenteSelect.value;
                    showNotification(data.message, 'success');

                    // Clear confirmed reservations
                    reservationsConfirmed = false;
                    reservedSlots = [];

                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
                else showNotification(data.message, 'error');
            }).catch(() => showNotification('Erro de conexão', 'error'));
        });
    }

    // ============================================================
    // NOTIFICATIONS
    // ============================================================
    window.showNotification = function (msg, type = 'info') {
        document.querySelectorAll('.toast-notification').forEach(t => t.remove());
        const t = document.createElement('div');
        t.className = `toast-notification toast-${type}`;
        t.innerHTML = `<div class="toast-content"><i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i><span>${msg}</span><button class="toast-close" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>`;
        document.body.prepend(t);
        setTimeout(() => { t.classList.add('toast-exit'); setTimeout(() => t.remove(), 300); }, 5000);
    };

    // ============================================================
    // VIEW TOGGLE (Calendar / Gantt)
    // ============================================================
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const view = this.dataset.view;
            document.getElementById('calendar-view').style.display = view === 'calendar' ? '' : 'none';
            document.getElementById('gantt-view').style.display = view === 'gantt' ? '' : 'none';
        });
    });

    // Auto-load if value is already set (e.g. for Professors or from GET)
    if (docenteSelect && docenteSelect.value) {
        loadDocenteAgenda(docenteSelect.value);
    }

    renderCalendar();
});
