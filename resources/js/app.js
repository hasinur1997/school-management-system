const parentsApp = document.querySelector('[data-parents-app]')

if (parentsApp) {
    const tokenKey = 'school.parents.token'

    const state = {
        token: localStorage.getItem(tokenKey) || '',
        view: 'active',
        search: '',
        page: 1,
        meta: {
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        parents: [],
        selected: new Set(),
        busy: false,
    }

    const authPanel = parentsApp.querySelector('[data-auth-panel]')
    const workspace = parentsApp.querySelector('[data-workspace]')
    const loginForm = parentsApp.querySelector('[data-login-form]')
    const tokenForm = parentsApp.querySelector('[data-token-form]')
    const authMessage = parentsApp.querySelector('[data-auth-message]')
    const listMessage = parentsApp.querySelector('[data-list-message]')
    const rows = parentsApp.querySelector('[data-parent-rows]')
    const empty = parentsApp.querySelector('[data-empty]')
    const loading = parentsApp.querySelector('[data-loading]')
    const viewTitle = parentsApp.querySelector('[data-view-title]')
    const searchForm = parentsApp.querySelector('[data-search-form]')
    const selectAll = parentsApp.querySelector('[data-select-all]')
    const bulkbar = parentsApp.querySelector('[data-bulkbar]')
    const selectedCount = parentsApp.querySelector('[data-selected-count]')
    const activeBulkActions = parentsApp.querySelector('[data-active-bulk-actions]')
    const trashBulkActions = parentsApp.querySelector('[data-trash-bulk-actions]')
    const trashHeading = parentsApp.querySelector('[data-trash-heading]')
    const pageSummary = parentsApp.querySelector('[data-page-summary]')
    const previousPage = parentsApp.querySelector('[data-prev-page]')
    const nextPage = parentsApp.querySelector('[data-next-page]')

    const setMessage = (target, message, tone = 'neutral') => {
        target.textContent = message
        target.dataset.tone = tone
    }

    const showWorkspace = () => {
        const authenticated = state.token.length > 0

        authPanel.hidden = authenticated
        workspace.hidden = !authenticated

        if (authenticated) {
            loadParents()
        }
    }

    const request = async (path, options = {}) => {
        const headers = {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(state.token ? { Authorization: `Bearer ${state.token}` } : {}),
            ...options.headers,
        }

        const response = await fetch(path, {
            ...options,
            headers,
        })

        const payload = await response.json().catch(() => ({}))

        if (!response.ok) {
            const message = payload.message || `Request failed with ${response.status}`
            throw new Error(message)
        }

        return payload
    }

    const formatDate = (value) => {
        if (!value) {
            return ''
        }

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value))
    }

    const studentSummary = (students = []) => {
        if (!students.length) {
            return '<span class="parents-muted">None</span>'
        }

        return students
            .map((student) => {
                const label = [student.name_en || student.name_bn || student.admission_no, student.class, student.section]
                    .filter(Boolean)
                    .join(' - ')

                return `<span class="parents-chip">${escapeHtml(label)}</span>`
            })
            .join('')
    }

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')

    const renderRows = () => {
        rows.innerHTML = state.parents.map((parent) => {
            const checked = state.selected.has(parent.id) ? 'checked' : ''
            const deletedAt = state.view === 'trash'
                ? `<td>${escapeHtml(formatDate(parent.deleted_at))}</td>`
                : ''
            const actions = state.view === 'trash'
                ? `
                    <button class="parents-link-button" type="button" data-row-restore="${escapeHtml(parent.id)}">Restore</button>
                    <button class="parents-link-button parents-link-danger" type="button" data-row-force-delete="${escapeHtml(parent.id)}">Delete permanently</button>
                `
                : `<button class="parents-link-button parents-link-danger" type="button" data-row-delete="${escapeHtml(parent.id)}">Delete</button>`

            return `
                <tr>
                    <td class="parents-select-cell">
                        <input type="checkbox" data-select-parent="${escapeHtml(parent.id)}" aria-label="Select ${escapeHtml(parent.name)}" ${checked}>
                    </td>
                    <td>
                        <div class="parents-name">${escapeHtml(parent.name)}</div>
                        <div class="parents-muted">${escapeHtml(parent.id)}</div>
                    </td>
                    <td>${escapeHtml(parent.phone)}</td>
                    <td>${escapeHtml(parent.email || '')}</td>
                    <td>${escapeHtml(parent.relation)}</td>
                    <td><div class="parents-students">${studentSummary(parent.students)}</div></td>
                    ${deletedAt}
                    <td class="parents-actions-cell">${actions}</td>
                </tr>
            `
        }).join('')

        empty.hidden = state.busy || state.parents.length > 0
        selectAll.checked = state.parents.length > 0 && state.parents.every((parent) => state.selected.has(parent.id))
        selectAll.indeterminate = !selectAll.checked && state.parents.some((parent) => state.selected.has(parent.id))
    }

    const renderChrome = () => {
        viewTitle.textContent = state.view === 'trash' ? 'Trash' : 'Active parents'
        trashHeading.hidden = state.view !== 'trash'

        parentsApp.querySelectorAll('[data-view]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.view === state.view)
        })

        const selectedTotal = state.selected.size
        bulkbar.hidden = selectedTotal === 0
        selectedCount.textContent = `${selectedTotal} selected`
        activeBulkActions.hidden = state.view !== 'active'
        trashBulkActions.hidden = state.view !== 'trash'

        pageSummary.textContent = `Page ${state.meta.current_page || 1} of ${state.meta.last_page || 1} (${state.meta.total || 0})`
        previousPage.disabled = state.busy || (state.meta.current_page || 1) <= 1
        nextPage.disabled = state.busy || (state.meta.current_page || 1) >= (state.meta.last_page || 1)
    }

    const render = () => {
        loading.hidden = !state.busy
        renderRows()
        renderChrome()
    }

    const listEndpoint = () => {
        const params = new URLSearchParams({
            page: state.page,
            per_page: 15,
        })

        if (state.search) {
            params.set('search', state.search)
        }

        const base = state.view === 'trash' ? '/api/v1/parents/trash' : '/api/v1/parents'

        return `${base}?${params.toString()}`
    }

    const loadParents = async () => {
        state.busy = true
        state.selected.clear()
        setMessage(listMessage, '')
        render()

        try {
            const payload = await request(listEndpoint())
            state.parents = payload.data || []
            state.meta = payload.meta || state.meta
        } catch (error) {
            state.parents = []
            setMessage(listMessage, error.message, 'danger')

            if (error.message === 'Unauthenticated.') {
                logout()
            }
        } finally {
            state.busy = false
            render()
        }
    }

    const login = async (formData) => {
        setMessage(authMessage, '')

        try {
            const payload = await request('/api/v1/auth/login', {
                method: 'POST',
                body: JSON.stringify({
                    login: formData.get('login'),
                    password: formData.get('password'),
                    device_name: 'parents-frontend',
                }),
            })

            state.token = payload.data.token
            localStorage.setItem(tokenKey, state.token)
            showWorkspace()
        } catch (error) {
            setMessage(authMessage, error.message, 'danger')
        }
    }

    const logout = () => {
        state.token = ''
        state.parents = []
        state.selected.clear()
        localStorage.removeItem(tokenKey)
        authPanel.hidden = false
        workspace.hidden = true
    }

    const selectedIds = () => Array.from(state.selected)

    const action = async (path, options, successMessage) => {
        state.busy = true
        setMessage(listMessage, '')
        render()

        try {
            await request(path, options)
            await loadParents()
            setMessage(listMessage, successMessage, 'success')
        } catch (error) {
            setMessage(listMessage, error.message, 'danger')
        } finally {
            state.busy = false
            render()
        }
    }

    const confirmAction = (message) => window.confirm(message)

    loginForm.addEventListener('submit', (event) => {
        event.preventDefault()
        login(new FormData(loginForm))
    })

    tokenForm.addEventListener('submit', (event) => {
        event.preventDefault()
        const token = new FormData(tokenForm).get('token')?.toString().trim()

        if (!token) {
            setMessage(authMessage, 'Enter a bearer token.', 'danger')
            return
        }

        state.token = token
        localStorage.setItem(tokenKey, token)
        showWorkspace()
    })

    parentsApp.querySelector('[data-refresh]').addEventListener('click', loadParents)
    parentsApp.querySelector('[data-logout]').addEventListener('click', logout)

    parentsApp.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => {
            state.view = button.dataset.view
            state.page = 1
            loadParents()
        })
    })

    searchForm.addEventListener('submit', (event) => {
        event.preventDefault()
        state.search = new FormData(searchForm).get('search')?.toString().trim() || ''
        state.page = 1
        loadParents()
    })

    selectAll.addEventListener('change', () => {
        if (selectAll.checked) {
            state.parents.forEach((parent) => state.selected.add(parent.id))
        } else {
            state.selected.clear()
        }

        render()
    })

    rows.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-select-parent]')

        if (!checkbox) {
            return
        }

        if (checkbox.checked) {
            state.selected.add(checkbox.dataset.selectParent)
        } else {
            state.selected.delete(checkbox.dataset.selectParent)
        }

        render()
    })

    rows.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-row-delete]')
        const restoreButton = event.target.closest('[data-row-restore]')
        const forceDeleteButton = event.target.closest('[data-row-force-delete]')

        if (deleteButton && confirmAction('Move this parent to trash?')) {
            action(`/api/v1/parents/${deleteButton.dataset.rowDelete}`, { method: 'DELETE' }, 'Parent moved to trash.')
        }

        if (restoreButton && confirmAction('Restore this parent?')) {
            action(`/api/v1/parents/${restoreButton.dataset.rowRestore}/restore`, { method: 'POST' }, 'Parent restored.')
        }

        if (forceDeleteButton && confirmAction('Permanently delete this parent?')) {
            action(`/api/v1/parents/${forceDeleteButton.dataset.rowForceDelete}/force`, { method: 'DELETE' }, 'Parent permanently deleted.')
        }
    })

    parentsApp.querySelector('[data-bulk-delete]').addEventListener('click', () => {
        if (selectedIds().length && confirmAction('Move selected parents to trash?')) {
            action('/api/v1/parents/bulk-delete', {
                method: 'POST',
                body: JSON.stringify({ ids: selectedIds() }),
            }, 'Parents moved to trash.')
        }
    })

    parentsApp.querySelector('[data-bulk-restore]').addEventListener('click', () => {
        if (selectedIds().length && confirmAction('Restore selected parents?')) {
            action('/api/v1/parents/bulk-restore', {
                method: 'POST',
                body: JSON.stringify({ ids: selectedIds() }),
            }, 'Parents restored.')
        }
    })

    parentsApp.querySelector('[data-bulk-force-delete]').addEventListener('click', () => {
        if (selectedIds().length && confirmAction('Permanently delete selected parents?')) {
            action('/api/v1/parents/bulk-force-delete', {
                method: 'POST',
                body: JSON.stringify({ ids: selectedIds() }),
            }, 'Parents permanently deleted.')
        }
    })

    previousPage.addEventListener('click', () => {
        if ((state.meta.current_page || 1) > 1) {
            state.page -= 1
            loadParents()
        }
    })

    nextPage.addEventListener('click', () => {
        if ((state.meta.current_page || 1) < (state.meta.last_page || 1)) {
            state.page += 1
            loadParents()
        }
    })

    showWorkspace()
}
