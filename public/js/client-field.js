/**
 * The Campaign Monitor settings' Client field.
 *
 * The CMS draws the client as a text field holding its ID, marked with data-campaign-monitor-client. This component
 * hides it behind a list of the clients the API key reaches, asked of the add-on's clients endpoint: on the page with
 * the saved key, and again each time the API key field changes, with the key typed. So the key and its client are
 * saved together, and a key Campaign Monitor refuses is said beside the field before saving.
 *
 * The typed key travels in a request header, never the address. Without the script, the field stays a text field
 * for the client's ID.
 *
 * The host's component registry loads this module when the marked field is on the page, and mounts the default
 * export's instance on each.
 */
export const KEY_HEADER = 'X-Campaign-Monitor-Key'

const ENDPOINT = 'extension/campaign-monitor/clients'

const KEY_FIELD = 'api_key'

/**
 * The clients endpoint, under the admin the page is in.
 *
 * @param {Location|URL} location
 * @returns {string}
 */
export function endpoint(location) {
    const path = location.pathname
    const at = path.indexOf('/admin/')

    return `${at < 0 ? '/admin/' : path.slice(0, at + 7)}${ENDPOINT}`
}

/**
 * The API key field in the same form. The CMS draws a password as its new-value box, api_key_chk, which it leaves
 * the saved key alone when blank; api_key, or a name ending [api_key] or [api_key_chk], is taken too.
 *
 * @param {HTMLFormElement|null} form
 * @returns {HTMLInputElement|null}
 */
export function keyField(form) {
    const names = [KEY_FIELD, `${KEY_FIELD}_chk`]
    const selector = names.flatMap((name) => [`input[name="${name}"]`, `input[name$="[${name}]"]`]).join(', ')

    return form?.querySelector(selector) ?? null
}

/**
 * Asks the endpoint for the clients a key reaches; with no key, the saved one.
 *
 * @param {string} url
 * @param {string} key
 * @param {AbortSignal} signal
 * @returns {Promise<{ok: boolean, clients?: Array<{id: string, name: string}>, error?: string}>}
 */
export async function lookUp(url, key, signal) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    if (key !== '') {
        headers[KEY_HEADER] = key
    }

    let response
    try {
        response = await fetch(url, { headers, credentials: 'same-origin', signal })
    } catch (error) {
        if (signal.aborted) {
            throw error
        }

        return { ok: false, error: "Campaign Monitor couldn't be asked about the key. Try again." }
    }

    try {
        const answer = await response.json()
        if (answer && typeof answer === 'object') {
            return answer
        }
    } catch {
        /* Not JSON: a sign-in page or an error page. */
    }

    return { ok: false, error: `The key couldn't be checked (the CMS answered ${response.status}).` }
}

export default class CampaignMonitorClientField {
    constructor() {
        this.elements = new Map()
    }

    /**
     * @param {HTMLInputElement} input
     */
    mount(input) {
        if (this.elements.has(input)) {
            return
        }

        const key = keyField(input.form)
        const select = document.createElement('select')
        select.className = 'select'
        select.id = input.id
        const describedBy = input.getAttribute('aria-describedby')
        if (describedBy) {
            select.setAttribute('aria-describedby', describedBy)
        }
        input.id = `${input.id}-value`
        input.type = 'hidden'
        input.after(select)

        const status = document.createElement('p')
        status.className = 'form__description campaign-monitor-key-status'
        status.setAttribute('role', 'status')
        status.setAttribute('aria-live', 'polite')
        status.hidden = true
        key?.after(status)

        const state = { key, select, status, request: null, id: select.id }
        state.onKey = () => this.load(input)
        state.onChoose = () => {
            input.value = select.value
        }
        key?.addEventListener('change', state.onKey)
        select.addEventListener('change', state.onChoose)
        this.elements.set(input, state)

        this.fill(input, [], 'Listing the clients…')

        return this.load(input)
    }

    update() {}

    /**
     * @param {HTMLInputElement} input
     */
    unmount(input) {
        const state = this.elements.get(input)
        if (!state) {
            return false
        }

        state.request?.abort()
        state.key?.removeEventListener('change', state.onKey)
        state.select.removeEventListener('change', state.onChoose)
        state.select.remove()
        state.status.remove()
        state.key?.removeAttribute('aria-invalid')
        input.type = 'text'
        input.id = state.id
        this.elements.delete(input)

        return true
    }

    destroy() {
        for (const input of [...this.elements.keys()]) {
            this.unmount(input)
        }
    }

    /**
     * Lists the clients for the key in the field, or the saved key when it is empty.
     *
     * @param {HTMLInputElement} input
     * @returns {Promise<void>}
     */
    async load(input) {
        const state = this.elements.get(input)
        if (!state) {
            return
        }

        state.request?.abort()
        const request = new AbortController()
        state.request = request
        const typed = (state.key?.value ?? '').trim()

        let answer
        try {
            answer = await lookUp(endpoint(window.location), typed, request.signal)
        } catch {
            return
        }

        if (state.request !== request) {
            return
        }

        state.request = null
        if (answer.ok && Array.isArray(answer.clients)) {
            this.fill(input, answer.clients, 'Choose a client')
            this.say(state, typed === '' ? '' : this.reached(answer.clients.length), false)

            return
        }

        if (typed === '') {
            this.fill(input, [], 'Enter the API key to list its clients')
            this.say(state, '', false)

            return
        }

        this.say(state, answer.error || "Campaign Monitor didn't accept the key.", true)
    }

    reached(count) {
        if (count === 0) {
            return 'Key accepted, but it reaches no clients.'
        }

        return count === 1 ? 'Key accepted: it reaches one client.' : `Key accepted: it reaches ${count} clients.`
    }

    /**
     * Shows a message beside the API key field, as an error when the key was refused.
     */
    say(state, text, refused) {
        state.status.textContent = text
        state.status.hidden = text === ''
        state.status.classList.toggle('form__description', !refused)
        state.status.classList.toggle('form__error', refused)
        if (refused) {
            state.key?.setAttribute('aria-invalid', 'true')
        } else {
            state.key?.removeAttribute('aria-invalid')
        }
    }

    /**
     * Lists the clients, keeping the chosen one, or choosing the only one there is.
     *
     * @param {HTMLInputElement} input
     * @param {Array<{id: string, name: string}>} clients
     * @param {string} prompt
     */
    fill(input, clients, prompt) {
        const { select } = this.elements.get(input)
        const chosen = input.value
        const options = [new Option(prompt, '')]
        for (const client of clients) {
            options.push(new Option(String(client.name), String(client.id)))
        }

        if (chosen !== '' && !clients.some((client) => String(client.id) === chosen)) {
            options.push(new Option(clients.length === 0 ? chosen : `${chosen} (not reached by this key)`, chosen))
        }

        select.replaceChildren(...options)
        select.value = chosen === '' && clients.length === 1 ? String(clients[0].id) : chosen
        input.value = select.value
    }
}
